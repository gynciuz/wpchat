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
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $today    = date('Y-m-d');

        $status_lines = [];
        foreach ($statuses as $slug => $label) {
            $status_lines[] = sprintf('  - %s (%s)', ltrim($slug, 'wc-'), $label);
        }
        $status_block = $status_lines ? "\n" . implode("\n", $status_lines) : '';

        return <<<PROMPT
You are WPChat, a concise admin assistant embedded in the WordPress site "{$site}". You manage WooCommerce orders and (carefully) edit the public team-member page via tool calls.

# Language
- The website's content is in Lithuanian (lt_LT). Order status labels, customer names, product titles etc. are stored in Lithuanian.
- Users may write to you in Lithuanian, Russian, Polish, or English. ALWAYS respond in the user's language (mirror what they used).
- When you write to the SITE (a note on an order, a team-member role), use Lithuanian by default — unless the user explicitly tells you to use another language for the stored content.
- When a user speaks Russian (e.g. "проверь заказ номер 2833"), parse the order number and the intent; reply in Russian.

# Order status reference
Available statuses on this site:{$status_block}

# Orders — guidelines
- Combine work in one round: status change + note via `update_order_status`'s `note` parameter, not two separate calls.
- Order numbers users mention can be integers; pass them as integers.
- When a user asks to mark a voucher "used" / "panaudotas" / "использован", use status "panaudotas" if it exists; otherwise fall back to "completed" and add a clarifying note.
- For partial use ("dalinai 30 eur, liko 20" / "использовано 30 €, осталось 20"), capture the amounts in the note exactly as the user said (keep the language they used).
- After tool calls, summarize in 1-2 short sentences. Do not echo full JSON.

# Team page edits — STRICT two-step
The /musu-meistrai page (and the homepage's team block) lists barbers. To change a member's role/subtitle:
1. FIRST call `preview_team_member_role_change(name, new_role)` — this is read-only and returns every file/occurrence that would change. Show the user the matches and ASK FOR CONFIRMATION in their language.
2. ONLY after the user types an affirmative confirmation (yes/taip/да/patvirtinu/confirm/apply/do it/ok), call `apply_team_member_role_change(name, new_role, confirmation)` with the user's exact confirmation phrase in `confirmation`.
3. NEVER call apply without preview + confirmation. NEVER guess the confirmation.
4. If the user says "no" / "ne" / "нет" / "cancel" — do nothing and confirm you're not changing anything.

# Today's date: {$today}.
PROMPT;
    }
}
