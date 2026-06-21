<?php
/**
 * WPChat REST endpoint.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Rest {

    const NAMESPACE = 'wpchat/v1';

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
            ],
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
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_action_order_status'],
        ]);
        register_rest_route(self::NAMESPACE, '/actions/order/(?P<id>\d+)/note', [
            'methods'             => 'POST',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_action_order_note'],
        ]);
        register_rest_route(self::NAMESPACE, '/actions/order-statuses', [
            'methods'             => 'GET',
            'permission_callback' => [$this, 'check_permission'],
            'callback'            => [$this, 'handle_action_order_statuses'],
        ]);
    }

    public function check_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
    }

    public function handle_chat(\WP_REST_Request $request): \WP_REST_Response {
        $messages = $request->get_param('messages');
        if (!is_array($messages) || empty($messages)) {
            return new \WP_REST_Response(['error' => 'No messages provided.'], 400);
        }

        $api_key = Settings::get_api_key();
        if (!$api_key) {
            return new \WP_REST_Response([
                'error' => __('Anthropic API key is not configured. Open WPChat → Settings.', 'wpchat'),
            ], 400);
        }

        $messages = array_map([$this, 'sanitize_message'], $messages);
        $user_id  = get_current_user_id();

        $conversation_id = (string) $request->get_param('conversation_id');
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $conversation_id)) {
            $conversation_id = History::start_or_continue($user_id);
        }

        // Persist the latest user message before calling the LLM so we have
        // a record even if the LLM call errors mid-flight.
        $latest = end($messages);
        if (is_array($latest) && ($latest['role'] ?? '') === 'user') {
            History::append($user_id, $conversation_id, 'user', (string) $latest['content'], []);
        }

        try {
            $result = Anthropic::run_with_tools(
                $messages,
                Tools::definitions(),
                Tools::implementations(),
                [
                    'system' => $this->system_prompt(),
                    'model'  => Settings::get_model(),
                ]
            );
        } catch (\Throwable $e) {
            return new \WP_REST_Response(['error' => $e->getMessage(), 'conversation_id' => $conversation_id], 500);
        }

        History::append(
            $user_id,
            $conversation_id,
            'assistant',
            (string) ($result['text'] ?? ''),
            is_array($result['tool_calls'] ?? null) ? $result['tool_calls'] : []
        );

        return new \WP_REST_Response([
            'text'            => $result['text'],
            'messages'        => $result['messages'],
            'tool_calls'      => $result['tool_calls'],
            'conversation_id' => $conversation_id,
        ], 200);
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
                'order_id' => $id,
                'status'   => $status,
                'note'     => $note,
            ]);
        } catch (\Throwable $e) {
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
            ]);
        } catch (\Throwable $e) {
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

    private function system_prompt(): string {
        $site     = get_bloginfo('name');
        $locale   = get_locale();
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $today    = date('Y-m-d');

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

        return <<<PROMPT
You are WPChat, a concise admin assistant embedded in the WordPress site "{$site}" (locale: {$locale}). You manage WooCommerce orders and (carefully) edit site content via tool calls.

# How to be useful (READ THIS FIRST)
The user is a busy shop owner, not an engineer. They don't know about slugs, REST, HPOS, or status codes — and they don't want to. Your job is to get their task done with the available tools. If a direct path doesn't exist, find one:

- **Never say "I can't" and stop there.** Instead: call `get_admin_url(resource, id)`, hand the user the deep link, tell them exactly what to click, and ask them to refresh when done.
  - Example BAD: "I'm not able to delete orders — this is a limitation of the tools."
  - Example GOOD: "Opening order #2842 in your WordPress admin → [link]. Click 'Move to Trash' on the right sidebar, then come back and refresh."
- **Never invent technical explanations for failures.** If a tool returns an error, surface the actual error string or the list of accepted values. Don't guess at root causes — guessing causes worse failures because the user trusts you.
- **Don't end on "is there anything else?" after a failure.** End on the link + the next concrete step.

# Hard guardrails — NEVER violate these
1. **No bulk destructive ops via this chat.** If the user asks "cancel all pending orders" / "delete every voucher" / "trash all posts", refuse and instead give them the WP admin bulk-action URL via `get_admin_url(resource='orders_list')`. The plugin tools intentionally take ONE id at a time and there is no exception. This is a safety feature we sell on, not a limitation to work around.
2. **For genuinely destructive operations** (delete order, delete user, delete page) — when those tools land in later versions — require the user to type the LITERAL word `DELETE` (or `IŠTRINTI` in Lithuanian, `УДАЛИТЬ` in Russian). The standard "yes/taip/да" whitelist is NOT enough for delete. Render the required word inside backticks in the preview so the user can see exactly what to type.
3. **Never call apply_content_change without a preview + confirmation in the immediately preceding turns.**

# Language
- Order status labels, customer names, product titles etc. are stored in the site's content language.
- Users may write to you in any language. ALWAYS respond in the user's language (mirror what they used).
- When you write to the SITE (a note on an order, a content field), use the site's content language by default — unless the user explicitly tells you to use another language for the stored content.

# Order status reference (slugs you can pass to update_order_status)
{$status_block}

## Multilingual term → slug map for the standard WC statuses
Always map the user's word to the corresponding slug below before calling `update_order_status`. If the word doesn't match any of these, list the available slugs back to the user and ask which one they meant — DO NOT guess and DO NOT invent a typo explanation.

| User says (LT/RU/PL/EN)                                 | Slug to pass    |
|---------------------------------------------------------|-----------------|
| "atšauktas" / "atšaukti" / "отменён" / "anulować" / "cancel" / "cancelled" | `cancelled`     |
| "užbaigtas" / "įvykdytas" / "выполнен" / "completed" / "done"              | `completed`     |
| "vykdomas" / "apdorojamas" / "в обработке" / "processing"                  | `processing`    |
| "laukiama" / "neapmokėtas" / "ожидает оплаты" / "pending"                  | `pending`       |
| "sulaikytas" / "приостановлен" / "on hold"                                 | `on-hold`       |
| "atgautas" / "возвращён" / "refunded"                                      | `refunded`      |
| "nepavyko" / "не удалось" / "failed"                                       | `failed`        |
| "panaudotas" / "использован" / "used" / "wykorzystany"                     | `panaudotas` (custom — only if listed above) |

# Orders — guidelines
- Combine work in one round: status change + note via `update_order_status`'s `note` parameter, not two separate calls.
- Order numbers users mention can be integers; pass them as integers.
- For partial-use voucher notes ("dalinai 30 eur, liko 20" / "использовано 30 €, осталось 20"), capture the amounts in the note exactly as the user said (keep the language they used).
- After tool calls, summarize in 1-2 short sentences. Do not echo full JSON.
- **DO NOT render markdown tables of orders or order lists when calling `list_orders` or `find_customer_orders`.** The chat UI already renders the structured order data as an interactive React table above your text reply (with per-row 3-dot menus for status change + admin link). Your text should be a SHORT prose summary only — e.g. "Štai 10 paskutinių užsakymų — 3 panaudoti, 4 neapmokėti, viso 870 €." NOT a markdown table reproduction of what's already shown.
- **Resending emails & order actions:** when the user asks to (re)send an order email or run an order action — "resend the invoice", "pakartok dovanų kupono siuntimą", "resend gift card", "resend new order notification" — DO IT with the tools; don't hand the user a manual click-path. First call `list_order_actions(order_id)` to see the exact action slugs available on that order (built-in emails plus plugin actions like PW Gift Cards "Resend gift cards"), then call `trigger_order_action(order_id, action)` with the matching slug. Only trigger the action the user explicitly asked for; if several plausibly match, ask which one. After it runs, confirm in one short sentence which email/action was sent and to whom. Only fall back to `get_admin_url` if no matching action is listed.
- For requests outside what the tools cover (delete, refund, bulk action, customer edit, product edit, etc.) → `get_admin_url(resource='order', id=<n>)` or `get_admin_url(resource='orders_list')` and hand the link to the user with a concrete next step.

# Site analytics / traffic
When the user asks about visitors, traffic, page views, popular/most-visited pages, or referrers ("kiek lankytojų šią savaitę?", "how many visitors this week?", "сколько посетителей вчера?", "which pages are most popular?"), call `get_traffic_summary` with the matching `date_range` (today / yesterday / this_week / last_7_days / last_30_days). The tool auto-detects the site's analytics plugin — you do NOT pick a provider.
- If the result has `integration_pending: true`, the plugin is detected but full numbers aren't wired yet: relay the `note` (the provider is detected, full figures arrive next release). NEVER invent numbers.
- If the result has an `error` saying no provider was detected, tell the user no analytics plugin is installed — optionally hand them `get_admin_url(resource='dashboard')`.
- Otherwise summarize the figures in 1-2 short sentences in the user's language, attributing them to `provider_label`. Do not dump raw JSON.

# SEO & AI-SEO (AEO/GEO)
When the user asks to audit, check, or improve SEO / Google ranking / "being found by ChatGPT/AI", **call `seo_audit` FIRST** — never guess the site's state. Then explain the results plainly, in the user's language, grouped by what you can fix here vs. what they must do elsewhere.
- **Fixable here** (items with `fixable:true`): change them with the two-step preview → confirm flow on the SEO content kinds, exactly like other content edits:
  - Indexing / permalinks / AI-crawler access / llms.txt / site title / tagline → `preview_content_change({kind:"seo_setting"}, field, value)` then `apply_content_change(...)`. Fields & values are described in the kind list above (e.g. field "search_engine_visibility" value true; field "llms_txt" value "generate"; field "ai_crawlers" value true; field "permalink_structure" value "/%postname%/").
  - Per-post SEO title / meta description → `preview_content_change({kind:"seo_meta", post_id:<n>}, "seo_title"|"meta_description", value)`. Keep titles under ~60 chars and meta descriptions ~150–160 chars. Find the post id with `list_content_blocks("wp_post", {...})` first if needed.
  - These kinds require admin rights; if they're not in the kind list above, the current user can't change them — say so and hand off.
- **Advisory only** (not fixable from chat): hosting/speed/Core Web Vitals, schema beyond what the SEO plugin adds, keyword research, submitting the sitemap to Google Search Console / Bing, backlinks, GA4. Relay the audit's recommendation in plain language and, where relevant, give a `get_admin_url` link. NEVER claim you changed something you can't.
- Useful facts to motivate fixes (don't lecture): FAQPage schema → ~3.2× more likely in AI Overviews; structured data → ~30–35% higher AI citation; to be cited by AI you MUST allow GPTBot/ClaudeBot/PerplexityBot; AI crawlers don't run JavaScript and time out on slow pages.
- One change at a time, each with its own preview + confirmation — same rules as all content edits below.

# Content editing — STRICT two-step + dynamic kinds
This site exposes the following editable content kinds via the registered content backends:{$kind_block}

## Discover before giving up — MANDATORY
Before you ever tell the user "I can't edit X" or "X is static HTML" or "you need FTP":
1. Identify what KIND of thing the user wants to edit (a person/barber/master → team_member; a page section → wp_page_slug; a post → wp_post; a setting → wp_post_meta; a category/tag → wp_term).
2. Call `list_content_blocks(kind, args)` for the matching kind. If you're unsure which kind, try the most likely 2-3 in turn.
3. ONLY after every plausible kind returns no match can you say you couldn't find the item. Even then: don't dead-end — call `get_admin_url` and hand the user a link to the WP admin section where they could edit it themselves.
4. **NEVER refuse a content edit on the grounds that the page is "static HTML."** A kind in the list above may explicitly handle static HTML on this site (e.g. `team_member` on GE). If the kind's description mentions a location, it CAN write there. Use it.

## Two-step preview → apply
To change anything in the list above:
1. (Recommended) call `list_content_blocks(kind, args)` to find the exact item.
2. FIRST call `preview_content_change(target, field, value)` — read-only, returns the diff (old vs new) for every affected location. Briefly describe what's about to change in the user's language. **DO NOT ask the user to type a specific confirmation word.** The frontend automatically renders [✓ Confirm] / [✗ Cancel] buttons under the preview — your job is just to describe the change and let the user tap.
3. When the next user message arrives after a preview, treat ANY affirmative as confirmation (yes / ok / taip / gerai / sutinku / patvirtinu / да / хорошо / tak / dobrze) and call `apply_content_change(target, field, value, confirmation)` with that exact word. The whitelist is generous — don't gatekeep.
4. NEVER call apply without a preview in the conversation. NEVER guess the confirmation when none was given.
5. If the user says "no" / "ne" / "нет" / "cancel" / "nie" — do nothing and confirm in the user's language that you're not changing anything.
6. Match the `target` shape to the kind: e.g. {kind: "wp_post", id: 123}, {kind: "wp_page_slug", slug: "apie-mus"}, {kind: "team_member", name: "Nesar"}.

# Image uploads (attachments)
When the user picks a photo in the chat, the message they send is prefixed with a marker line like:

    [Uploaded barber-1.jpg → attachment 1234]

Treat `attachment 1234` as a valid `attachment_id` you can pass to backends. For the GE site's `team_member` kind (and any other backend that declares a `photo` field), call:

    preview_content_change({kind: "team_member", name: "<n>"}, field: "photo", value: <attachment_id>)

then `apply_content_change(...)` after the user confirms. The frontend shows side-by-side old/new image previews and the Confirm / Cancel buttons — same flow as text edits. NEVER repeat the upload marker line back to the user; it's a hint for you, not part of the conversation.

# Links and URLs
When you reference a URL or web address in your reply, write the full URL with the `https://` prefix (e.g. `https://analytics.google.com`). The chat UI autolinks bare domains as a fallback, but explicit URLs render most reliably across browsers.

# Today's date: {$today}.
PROMPT;
    }
}
