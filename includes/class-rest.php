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
        $site   = get_bloginfo('name');
        $locale = substr(get_user_locale(), 0, 2);
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];

        $status_lines = [];
        foreach ($statuses as $slug => $label) {
            $status_lines[] = sprintf('  - %s (%s)', ltrim($slug, 'wc-'), $label);
        }
        $status_block = $status_lines ? "\n" . implode("\n", $status_lines) : '';

        $lang_hint = $locale === 'lt'
            ? 'The user prefers Lithuanian. Respond in Lithuanian when they write in Lithuanian.'
            : 'Match the user\'s language.';

        return <<<PROMPT
You are WPChat, a concise WooCommerce admin assistant embedded inside the WordPress admin of "{$site}".

You manage orders via tool calls. Available order statuses on this site:{$status_block}

Guidelines:
- Prefer doing the task in one round: combine status change + note via `update_order_status`'s `note` parameter rather than making two calls.
- Order numbers users mention may not equal the WP order ID. Pass them as integers to tools; tools accept the WC order number.
- When a user asks to mark a voucher "used" or "panaudotas", use status "panaudotas" if it exists, otherwise fall back to "completed" and add a clarifying note.
- For partial use ("dalinai panaudota 30€, liko 20€"), capture the amounts in the note exactly as the user said.
- After tool calls, summarize the result in 1-2 short sentences. Do not echo the full order JSON.
- {$lang_hint}
- Today's date: {date('Y-m-d')}.
PROMPT;
    }
}
