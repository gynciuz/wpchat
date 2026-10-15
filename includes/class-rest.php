<?php
/**
 * ChatAdmin REST endpoint.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Rest {

    const NAMESPACE = 'chatadmin/v1';

    /** Per-user chat rate limit (bounds API cost from a runaway/abusive account). */
    const RATE_LIMIT_MAX    = 30; // requests…
    const RATE_LIMIT_WINDOW = 60; // …per this many seconds.

    public function __construct() {
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register(): void {
        register_rest_route(self::NAMESPACE, '/chat', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_chat'],
            'args' => [
                'messages' => [
                    'required' => true,
                    'type'     => 'array',
                ],
                'conversation_id' => [
                    'required' => false,
                    'type'     => 'string',
                ],
                'mode' => [
                    'required' => false,
                    'type'     => 'string',
                ],
            ],
        ]);
        // Explicit "Report a problem" — packages the conversation + error and
        // routes it to the developer (collector endpoint or wp_mail).
        register_rest_route(self::NAMESPACE, '/support', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_support_report'],
        ]);
        register_rest_route(self::NAMESPACE, '/conversations', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_list_conversations'],
        ]);
        register_rest_route(self::NAMESPACE, '/conversations/(?P<id>[a-f0-9-]{36})', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_get_conversation'],
        ]);
        // Direct-action endpoints — these BYPASS the LLM entirely so the
        // chat UI's 3-dot menus on order tables can mutate state without
        // burning API credits or risking tool-call hallucinations.
        register_rest_route(self::NAMESPACE, '/actions/order/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_order_permission'],
            'callback'            => [$this, 'handle_action_order_status'],
        ]);
        register_rest_route(self::NAMESPACE, '/actions/order/(?P<id>\d+)/note', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_order_permission'],
            'callback'            => [$this, 'handle_action_order_note'],
        ]);
        register_rest_route(self::NAMESPACE, '/actions/order-statuses', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'check_order_permission'],
            'callback'            => [$this, 'handle_action_order_statuses'],
        ]);
    }

    /**
     * Who may open ChatAdmin at all. Anyone who can manage orders
     * (`manage_woocommerce` / `edit_shop_orders`) OR edit content
     * (`edit_posts`) qualifies — the same floor as the /chatadmin page. What
     * they can actually DO once inside is gated per-tool against their own WP
     * capabilities (Tools::require_order_access for orders, user_can_edit_kind
     * for content), so an editor gets content editing only while an admin gets
     * everything. The power comes from the user's role, not from ChatAdmin.
     */
    public function check_permission(): bool {
        return current_user_can('manage_woocommerce')
            || current_user_can('edit_shop_orders')
            || current_user_can('edit_posts');
    }

    /**
     * Order management (the LLM-free 3-dot-menu routes) requires WooCommerce
     * order rights specifically — a content-only user (editor) can open the
     * chat but not touch orders, matching Tools::require_order_access.
     */
    public function check_order_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
    }

    /**
     * Auto-detect "unmet requests." When a chat turn resolves with a
     * `get_admin_url` handoff (the model's "no tool can do this, here's the
     * wp-admin link" fallback) and NO in-app mutation succeeded, log a
     * `capability_gap` event carrying only the anonymised request text plus the
     * handed-off resource. These are the product's top improvement signals —
     * e.g. "user wanted to edit a team-member's label, we couldn't." Routed
     * through Telemetry, so they appear in Diagnostics locally and, when the
     * admin opted in and a collector is configured, phone home.
     *
     * @param array<int,array{name?:string,input?:mixed,output?:mixed}> $tool_calls
     */
    private function maybe_log_capability_gap(string $user_message, array $tool_calls): void {
        if ($user_message === '' || empty($tool_calls)) {
            return;
        }

        $mutators = ['apply_content_change', 'create_content', 'publish_content', 'update_order_status', 'add_order_note', 'trigger_order_action'];
        $handoff  = null;
        $mutated  = false;

        foreach ($tool_calls as $call) {
            $name = (string) ($call['name'] ?? '');
            if ($name === 'get_admin_url') {
                $handoff = $call;
            } elseif (in_array($name, $mutators, true)) {
                $out = $call['output'] ?? null;
                if (is_array($out) && empty($out['error']) && (!empty($out['ok']) || !empty($out['post_id']) || !empty($out['status']))) {
                    $mutated = true;
                }
            }
        }

        // A handoff that FOLLOWED a successful edit isn't a gap (e.g. "done —
        // here's the page"). Only a handoff with nothing accomplished is.
        if ($handoff === null || $mutated) {
            return;
        }

        $resource = is_array($handoff['input'] ?? null) ? (string) ($handoff['input']['resource'] ?? '') : '';
        Telemetry::log('capability_gap', [
            'message' => Telemetry::redact_pii($user_message),
            'tool'    => 'get_admin_url',
            'code'    => $resource,
        ]);
    }

    public function handle_chat(\WP_REST_Request $request): \WP_REST_Response {
        $messages = $request->get_param('messages');
        if (!is_array($messages) || empty($messages)) {
            return new \WP_REST_Response(['error' => 'No messages provided.'], 400);
        }

        $api_key = Settings::get_api_key();
        if (!$api_key) {
            return new \WP_REST_Response([
                'error' => sprintf(
                    /* translators: %s = provider label, e.g. Anthropic */
                    __('%s API key is not configured. Open ChatAdmin → Settings.', 'chatadmin'),
                    LLM::active()->label()
                ),
            ], 400);
        }

        $messages = array_map([$this, 'sanitize_message'], $messages);
        $user_id  = get_current_user_id();

        // Per-user rate limit — caps API spend if an account is compromised or
        // a client loops. Tunable via the chatadmin_rate_limit_max filter.
        $max = (int) apply_filters('chatadmin_rate_limit_max', self::RATE_LIMIT_MAX);
        if ($max > 0) {
            $rl_key = 'chatadmin_rl_' . $user_id;
            $count  = get_transient($rl_key);
            if ($count === false) {
                set_transient($rl_key, 1, self::RATE_LIMIT_WINDOW);
            } elseif ((int) $count >= $max) {
                return new \WP_REST_Response([
                    'error' => __('You are sending requests too quickly — please wait a moment and try again.', 'chatadmin'),
                ], 429);
            } else {
                set_transient($rl_key, (int) $count + 1, self::RATE_LIMIT_WINDOW);
            }
        }

        // Support mode: a free help assistant. NO tools (it only answers
        // "how do I…/why isn't X working" from the bundled FAQ), and it is
        // ephemeral — not persisted into the order-management history.
        $is_support = ($request->get_param('mode') === 'support');

        $conversation_id = (string) $request->get_param('conversation_id');
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $conversation_id)) {
            $conversation_id = $is_support ? '' : History::start_or_continue($user_id);
        }

        // Persist the latest user message before calling the LLM so we have
        // a record even if the LLM call errors mid-flight. (Skipped in support
        // mode so help chatter never pollutes the conversation history.)
        $latest_user_message = '';
        if (!$is_support) {
            $latest = end($messages);
            if (is_array($latest) && ($latest['role'] ?? '') === 'user') {
                $latest_user_message = (string) $latest['content'];
                History::append($user_id, $conversation_id, 'user', $latest_user_message, []);
            }
        }

        // Bind mutation confirmations to a real user turn (audit finding #2):
        // the turn index is the count of user messages in this conversation, so
        // an apply can require a preview from a strictly earlier turn. The
        // user's actual latest message is also carried so a mutating apply can
        // require genuine consent (a whitelisted confirmation the *user* typed),
        // not a `confirmation` argument the model authored — which prompt
        // injection could otherwise supply.
        if (!$is_support) {
            Tools::set_request_context([
                'conversation_id' => $conversation_id,
                'turn'            => History::user_message_count($user_id, $conversation_id),
                'user_message'    => $latest_user_message,
            ]);
        }

        try {
            $result = LLM::run_with_tools(
                $messages,
                $is_support ? [] : Tools::definitions(),
                $is_support ? [] : Tools::implementations(),
                [
                    'system' => $is_support ? $this->support_prompt() : $this->system_prompt(),
                    'model'  => Settings::get_model(),
                ]
            );
        } catch (\Throwable $e) {
            Telemetry::log($is_support ? 'support_chat_failed' : 'chat_failed', ['message' => $e->getMessage()]);
            return new \WP_REST_Response(['error' => $e->getMessage(), 'conversation_id' => $conversation_id], 500);
        } finally {
            Tools::clear_request_context();
        }

        if (!$is_support) {
            History::append(
                $user_id,
                $conversation_id,
                'assistant',
                (string) ($result['text'] ?? ''),
                is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : []
            );
            // Learn from what we couldn't do: if this turn ended in a wp-admin
            // handoff with no successful in-app mutation, record it as a
            // capability gap (anonymised request only) so the top unmet
            // requests surface in Diagnostics / the collector.
            $this->maybe_log_capability_gap(
                $latest_user_message,
                is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : []
            );
        }

        return new \WP_REST_Response([
            'text'            => $result['text'],
            'messages'        => $result['messages'],
            'tool_calls'      => $result['tool_calls'],
            'conversation_id' => $conversation_id,
        ], 200);
    }

    /**
     * Deliver a "Report a problem" submission to the developer. Packages the
     * user's note, the last error they hit, and the recent conversation (which
     * the user is explicitly choosing to send) plus environment info.
     */
    public function handle_support_report(\WP_REST_Request $request): \WP_REST_Response {
        $body    = $request->get_json_params();
        $note    = isset($body['note']) ? sanitize_textarea_field((string) $body['note']) : '';
        $error   = isset($body['error']) ? sanitize_text_field((string) $body['error']) : '';
        $user_id = get_current_user_id();

        // Prefer server-side history (authoritative) over client-sent text.
        $convo = '';
        $recent = [];
        if (!empty($body['conversation_id']) && is_string($body['conversation_id'])
            && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $body['conversation_id'])) {
            $convo = $body['conversation_id'];
            $full  = History::get_conversation($user_id, $convo);
            // Last 10 turns is plenty of context without dumping everything.
            $recent = array_slice($full, -10);
        }

        $user = wp_get_current_user();
        $ok = Telemetry::send_report([
            'note'            => $note,
            'error'           => $error,
            'conversation_id' => $convo,
            'messages'        => $recent,
            'recent_errors'   => Telemetry::recent(10),
            'provider'        => Settings::get_provider(),
            'reporter'        => [
                'login' => $user ? $user->user_login : '',
                'email' => $user ? $user->user_email : '',
            ],
        ]);

        return new \WP_REST_Response(['ok' => $ok], $ok ? 200 : 502);
    }

    public function handle_list_conversations(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        $limit   = (int) ($request->get_param('limit') ?? 30);
        return new \WP_REST_Response([
            'conversations' => History::list_conversations($user_id, $limit),
        ], 200);
    }

    /**
     * Direct status change — no LLM. Called by the chat UI's 3-dot menu
     * on an order row. Returns the updated order summary so the table can
     * re-render in place.
     */
    public function handle_action_order_status(\WP_REST_Request $request): \WP_REST_Response {
        $id     = (int) $request['id'];
        $status = (string) ($request->get_json_params()['status'] ?? '');
        $note   = (string) ($request->get_json_params()['note'] ?? '');

        try {
            $result = Tools::update_order_status([
                'order_id'   => $id,
                'status'     => $status,
                'note'       => $note,
                '_confirmed' => true, // the 3-dot-menu click is the confirmation.
            ]);
        } catch (\Throwable $e) {
            Telemetry::log('order_status_action_failed', ['message' => $e->getMessage(), 'tool' => 'update_order_status']);
            return new \WP_REST_Response(['error' => $e->getMessage()], 503);
        }

        if (!empty($result['error'])) {
            return new \WP_REST_Response($result, 400);
        }

        // Hand back the fresh summary so the UI doesn't need a follow-up GET.
        $order = function_exists('wc_get_order') ? \wc_get_order($id) : null;
        $summary = $order ? Tools::summarize($order) : null;

        return new \WP_REST_Response([
            'ok'      => true,
            'order'   => $summary,
            'result'  => $result,
        ], 200);
    }

    public function handle_action_order_note(\WP_REST_Request $request): \WP_REST_Response {
        $id      = (int) $request['id'];
        $body    = $request->get_json_params();
        $note    = (string) ($body['note'] ?? '');
        $visible = !empty($body['customer_visible']);

        try {
            $result = Tools::add_order_note([
                'order_id'         => $id,
                'note'             => $note,
                'customer_visible' => $visible,
                '_confirmed'       => true, // the 3-dot-menu click is the confirmation.
            ]);
        } catch (\Throwable $e) {
            Telemetry::log('order_note_action_failed', ['message' => $e->getMessage(), 'tool' => 'add_order_note']);
            return new \WP_REST_Response(['error' => $e->getMessage()], 503);
        }

        $status = empty($result['error']) ? 200 : 400;
        return new \WP_REST_Response($result, $status);
    }

    public function handle_action_order_statuses(\WP_REST_Request $request): \WP_REST_Response {
        $statuses = function_exists('wc_get_order_statuses') ? \wc_get_order_statuses() : [];
        $out = [];
        foreach ($statuses as $slug => $label) {
            $out[] = ['slug' => Tools::unprefixed_status($slug), 'label' => $label];
        }
        return new \WP_REST_Response(['statuses' => $out], 200);
    }

    public function handle_get_conversation(\WP_REST_Request $request): \WP_REST_Response {
        $user_id         = get_current_user_id();
        $conversation_id = (string) $request['id'];
        $messages = History::get_conversation($user_id, $conversation_id);
        if (empty($messages)) {
            return new \WP_REST_Response(['error' => 'Conversation not found.'], 404);
        }
        return new \WP_REST_Response([
            'conversation_id' => $conversation_id,
            'messages'        => $messages,
        ], 200);
    }

    private function sanitize_message($message): array {
        if (!is_array($message)) {
            return ['role' => 'user', 'content' => ''];
        }
        $role = in_array($message['role'] ?? '', ['user', 'assistant'], true) ? $message['role'] : 'user';
        return [
            'role'    => $role,
            'content' => $message['content'] ?? '',
        ];
    }

    /**
     * Load a prompt body from includes/prompts/{$name}.txt and fill its
     * {{placeholder}} tokens. Prompts live in plain text files so they stay
     * legible and diffable; strtr() substitutes in a single pass, so a value
     * containing "{{…}}" is never re-expanded.
     */
    private static function prompt_template(string $name, array $vars): string {
        $tpl = (string) file_get_contents(CHATADMIN_DIR . 'includes/prompts/' . $name . '.txt');
        $map = [];
        foreach ($vars as $key => $value) {
            $map['{{' . $key . '}}'] = (string) $value;
        }
        return rtrim(strtr($tpl, $map), "\n");
    }

    /**
     * Support/help assistant prompt. Used in `mode: 'support'` with NO tools —
     * it only answers questions about ChatAdmin itself from the FAQ below. When it
     * can't resolve something, it points the user at "Report a problem".
     */
    private function support_prompt(): string {
        $site   = get_bloginfo('name');
        $locale = get_locale();
        $wc      = function_exists('wc_get_order_statuses') ? 'active' : 'not active';
        $has_key = Settings::get_api_key() ? 'configured' : 'NOT configured';

        return self::prompt_template('support', [
            'site'    => $site,
            'locale'  => $locale,
            'has_key' => $has_key,
            'wc'      => $wc,
        ]);
    }

    private function system_prompt(): string {
        $site     = get_bloginfo('name');
        $locale   = get_locale();
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $today    = gmdate('Y-m-d');

        $status_lines = [];
        foreach ($statuses as $slug => $label) {
            $status_lines[] = sprintf('  - `%s` → "%s"', Tools::unprefixed_status($slug), $label);
        }
        $status_block = $status_lines ? "\n" . implode("\n", $status_lines) : '';

        // Filter the kinds list before the LLM ever sees it:
        //  - Site admin may have disabled specific kinds (Onboarding option).
        //  - The current user's WP role may not permit editing some kinds
        //    (e.g. an editor doesn't have manage_categories for wp_term).
        // Hiding restricted kinds from the prompt is belt-and-suspenders;
        // Tools::apply_content_change() also refuses on dispatch.
        $disabled = Onboarding::get_site_disabled_kinds();
        $kind_lines = [];
        foreach (ContentRouter::all_descriptions() as $kind => $desc) {
            if (in_array($kind, $disabled, true)) {
                continue;
            }
            if (!Tools::user_can_edit_kind($kind)) {
                continue;
            }
            $fields = !empty($desc['fields']) ? implode(', ', $desc['fields']) : '(no editable fields)';
            $kind_lines[] = sprintf("  - **%s** — %s\n      Editable fields: %s", $kind, $desc['description'] ?? '', $fields);
        }
        $kind_block = $kind_lines
            ? "\n" . implode("\n", $kind_lines)
            : "\n  (no content kinds registered)";

        // Enumerate the site's custom post types (beyond post/page) the current
        // user may edit. Theme/plugin content — team members, portfolio,
        // services, testimonials — lives in these, and wp_post / wp_post_meta
        // already edit ANY post type by id. Listing them here is what stops the
        // model dead-ending on "that's stored separately" (the reported
        // trx_team "meistrai" handoff): now it knows the type exists and can
        // target it.
        $skip_types = ['post', 'page', 'attachment', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'nav_menu_item', 'custom_css', 'customize_changeset', 'revision', 'oembed_cache', 'user_request', 'shop_order', 'shop_order_refund', 'shop_coupon', 'shop_subscription', 'product_variation'];
        $cpt_lines  = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $pt) {
            if (in_array($pt->name, $skip_types, true)) {
                continue;
            }
            $edit_cap = (is_object($pt->cap) && !empty($pt->cap->edit_posts)) ? $pt->cap->edit_posts : 'edit_posts';
            if (!current_user_can($edit_cap)) {
                continue;
            }
            $label = (is_object($pt->labels) && !empty($pt->labels->name)) ? $pt->labels->name : $pt->name;
            $cpt_lines[] = sprintf('  - `%s` — %s', $pt->name, $label);
        }
        $cpt_block = $cpt_lines
            ? "\n" . implode("\n", $cpt_lines)
            : "\n  (none beyond posts and pages)";

        // The current user's WooCommerce order rights. Everything ChatAdmin
        // lets someone do is scoped to their WP role, so the prompt tells the
        // model up-front whether THIS user may touch orders — an admin can, a
        // content-only editor can't. Enforcement is in Tools::require_order_access;
        // this just stops the model offering an action it will be refused.
        $can_orders  = current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
        $orders_perm = $can_orders
            ? '- **Orders:** ALLOWED — you may list/search orders and, with confirmation, change status, add notes, and run order actions.'
            : "- **Orders:** NOT ALLOWED for this user — their WordPress role has no WooCommerce order permission. Do NOT call the order tools (they return a permission error). If they ask about orders or customers, say their role can't access orders here and, if useful, hand off with `get_admin_url`.";

        return self::prompt_template('system', [
            'site'         => $site,
            'locale'       => $locale,
            'orders_perm'  => $orders_perm,
            'status_block' => $status_block,
            'kind_block'   => $kind_block,
            'cpt_block'    => $cpt_block,
            'today'        => $today,
        ]);
    }
}
