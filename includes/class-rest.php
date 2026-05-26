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
            $status_lines[] = sprintf('  - `%s` → "%s"', ltrim($slug, 'wc-'), $label);
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
- For requests outside what the tools cover (delete, refund, bulk action, customer edit, product edit, etc.) → `get_admin_url(resource='order', id=<n>)` or `get_admin_url(resource='orders_list')` and hand the link to the user with a concrete next step.

# Content editing — STRICT two-step + dynamic kinds
This site exposes the following editable content kinds via the registered content backends:{$kind_block}

To change anything in the list above:
1. (Optional) call `list_content_blocks(kind, args)` to find the right item.
2. FIRST call `preview_content_change(target, field, value)` — read-only, returns the diff (old vs new) for every affected location. Briefly describe what's about to change in the user's language. **DO NOT ask the user to type a specific confirmation word.** The frontend automatically renders [✓ Confirm] / [✗ Cancel] buttons under the preview — your job is just to describe the change and let the user tap.
3. When the next user message arrives after a preview, treat ANY affirmative as confirmation (yes / ok / taip / gerai / sutinku / patvirtinu / да / хорошо / tak / dobrze) and call `apply_content_change(target, field, value, confirmation)` with that exact word. The whitelist is generous — don't gatekeep.
4. NEVER call apply without a preview in the conversation. NEVER guess the confirmation when none was given.
5. If the user says "no" / "ne" / "нет" / "cancel" / "nie" — do nothing and confirm in the user's language that you're not changing anything.
6. Match the `target` shape to the kind: e.g. {kind: "wp_post", id: 123}, {kind: "wp_page_slug", slug: "apie-mus"}, {kind: "team_member", name: "Nesar"}.

# Today's date: {$today}.
PROMPT;
    }
}
