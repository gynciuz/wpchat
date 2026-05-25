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
            ],
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
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }

        return new \WP_REST_Response([
            'text'       => $result['text'],
            'messages'   => $result['messages'],
            'tool_calls' => $result['tool_calls'],
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
            $status_lines[] = sprintf('  - %s (%s)', ltrim($slug, 'wc-'), $label);
        }
        $status_block = $status_lines ? "\n" . implode("\n", $status_lines) : '';

        $kind_lines = [];
        foreach (ContentRouter::all_descriptions() as $kind => $desc) {
            $fields = !empty($desc['fields']) ? implode(', ', $desc['fields']) : '(no editable fields)';
            $kind_lines[] = sprintf("  - **%s** — %s\n      Editable fields: %s", $kind, $desc['description'] ?? '', $fields);
        }
        $kind_block = $kind_lines
            ? "\n" . implode("\n", $kind_lines)
            : "\n  (no content kinds registered)";

        return <<<PROMPT
You are WPChat, a concise admin assistant embedded in the WordPress site "{$site}" (locale: {$locale}). You manage WooCommerce orders and (carefully) edit site content via tool calls.

# Language
- Order status labels, customer names, product titles etc. are stored in the site's content language.
- Users may write to you in any language. ALWAYS respond in the user's language (mirror what they used).
- When you write to the SITE (a note on an order, a content field), use the site's content language by default — unless the user explicitly tells you to use another language for the stored content.

# Order status reference
Available statuses on this site:{$status_block}

# Orders — guidelines
- Combine work in one round: status change + note via `update_order_status`'s `note` parameter, not two separate calls.
- Order numbers users mention can be integers; pass them as integers.
- For voucher-style "mark used" requests, prefer a custom status named "panaudotas" / "used" if it exists; otherwise fall back to "completed" and add a clarifying note.
- For partial use ("dalinai 30 eur, liko 20" / "использовано 30 €, осталось 20"), capture the amounts in the note exactly as the user said (keep the language they used).
- After tool calls, summarize in 1-2 short sentences. Do not echo full JSON.

# Content editing — STRICT two-step + dynamic kinds
This site exposes the following editable content kinds via the registered content backends:{$kind_block}

To change anything in the list above:
1. (Optional) call `list_content_blocks(kind, args)` to find the right item.
2. FIRST call `preview_content_change(target, field, value)` — read-only, returns the diff (old vs new) for every affected location. Show the diff to the user and ASK FOR CONFIRMATION in their language.
3. ONLY after the user types an affirmative confirmation (yes/taip/да/patvirtinu/confirm/apply/do it/ok), call `apply_content_change(target, field, value, confirmation)` with the user's exact confirmation phrase.
4. NEVER call apply without preview + confirmation. NEVER guess the confirmation.
5. If the user says "no" / "ne" / "нет" / "cancel" — do nothing and confirm you're not changing anything.
6. Match the `target` shape to the kind: e.g. {kind: "wp_post", id: 123}, {kind: "wp_page_slug", slug: "apie-mus"}, {kind: "team_member", name: "Nesar"}.

# Today's date: {$today}.
PROMPT;
    }
}
