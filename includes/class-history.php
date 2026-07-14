<?php
/**
 * ChatAdmin conversation history.
 *
 * Stores every user + assistant message keyed by conversation UUID and
 * WP user id. Conversations are auto-grouped by a 30-minute idle gap:
 * the next message after a 30-min pause starts a new UUID. Each user
 * sees only their own conversations.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class History {

    const TABLE         = 'chatadmin_messages';
    const IDLE_GAP_SECS = 1800; // 30 min

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /** Run on plugin activation; idempotent via dbDelta. */
    public static function migrate(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation CHAR(36) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL,
            content LONGTEXT NOT NULL,
            tool_calls LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY user_recent (user_id, created_at),
            KEY conversation (conversation)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Return the conversation id this user should be using right now.
     * If their most recent message was < IDLE_GAP_SECS ago, continue it;
     * otherwise mint a new UUID.
     */
    public static function start_or_continue(int $user_id): string {
        global $wpdb;
        $table = self::table_name();
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::IDLE_GAP_SECS);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT conversation, created_at FROM $table WHERE user_id = %d AND created_at >= %s ORDER BY id DESC LIMIT 1",
                $user_id,
                $cutoff
            )
        );
        if ($row && !empty($row->conversation)) {
            return $row->conversation;
        }
        return self::uuid4();
    }

    /**
     * Count the user-role messages in a conversation. Used as a monotonic
     * per-conversation "turn" index (one user message is appended per chat
     * request) to bind mutation confirmations to a real, earlier user turn.
     */
    public static function user_message_count(int $user_id, string $conversation): int {
        global $wpdb;
        $table = self::table_name();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND conversation = %s AND role = %s",
            $user_id,
            $conversation,
            'user'
        ));
    }

    /** Append one message. Returns the inserted row id or 0 on failure. */
    public static function append(int $user_id, string $conversation, string $role, string $content, array $tool_calls = []): int {
        global $wpdb;
        if (!in_array($role, ['user', 'assistant'], true)) {
            return 0;
        }
        $tool_json = empty($tool_calls) ? null : wp_json_encode($tool_calls);
        $ok = $wpdb->insert(
            self::table_name(),
            [
                'conversation' => $conversation,
                'user_id'      => $user_id,
                'role'         => $role,
                'content'      => $content,
                'tool_calls'   => $tool_json,
                'created_at'   => current_time('mysql', true),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );
        return $ok ? (int) $wpdb->insert_id : 0;
    }

    /**
     * List recent conversations for a user. Returns one row per conversation
     * with its first user message as a label and its latest activity.
     *
     * @return array<int, array{conversation:string, label:string, last_activity:string, message_count:int}>
     */
    public static function list_conversations(int $user_id, int $limit = 30): array {
        global $wpdb;
        $table = self::table_name();
        $limit = max(1, min($limit, 100));

        // Two-step query: (1) get the most recent N conversations and their last activity;
        // (2) fetch the first user message of each as a label. Plain SQL is portable.
        $convs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT conversation, MAX(created_at) AS last_activity, COUNT(*) AS message_count
                 FROM $table
                 WHERE user_id = %d
                 GROUP BY conversation
                 ORDER BY last_activity DESC
                 LIMIT %d",
                $user_id,
                $limit
            )
        );
        if (!$convs) {
            return [];
        }

        $ids = array_map(static fn($r) => $r->conversation, $convs);
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));

        $first_msgs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.conversation, m.content
                 FROM $table m
                 INNER JOIN (
                   SELECT conversation, MIN(id) AS first_id
                   FROM $table
                   WHERE user_id = %d AND role = 'user' AND conversation IN ($placeholders)
                   GROUP BY conversation
                 ) f ON f.first_id = m.id",
                array_merge([$user_id], $ids)
            )
        );
        $label_by_conv = [];
        foreach ($first_msgs as $f) {
            $label_by_conv[$f->conversation] = $f->content;
        }

        $out = [];
        foreach ($convs as $c) {
            $label = $label_by_conv[$c->conversation] ?? '';
            $out[] = [
                'conversation'  => $c->conversation,
                'label'         => self::shorten($label, 80),
                'last_activity' => $c->last_activity,
                'message_count' => (int) $c->message_count,
            ];
        }
        return $out;
    }

    /**
     * Full message list of one conversation, oldest first. Returns [] if the
     * conversation doesn't belong to this user (no leaks across users).
     *
     * @return array<int, array{role:string, content:string, tool_calls:array, created_at:string}>
     */
    public static function get_conversation(int $user_id, string $conversation): array {
        global $wpdb;
        $table = self::table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, tool_calls, created_at FROM $table WHERE user_id = %d AND conversation = %s ORDER BY id ASC",
                $user_id,
                $conversation
            )
        );
        if (!$rows) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $tool_calls = [];
            if (!empty($r->tool_calls)) {
                $decoded = json_decode($r->tool_calls, true);
                if (is_array($decoded)) {
                    $tool_calls = $decoded;
                }
            }
            $out[] = [
                'role'       => $r->role,
                'content'    => $r->content,
                'tool_calls' => $tool_calls,
                'created_at' => $r->created_at,
            ];
        }
        return $out;
    }

    private static function shorten(string $s, int $max): string {
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        if (mb_strlen($s, 'UTF-8') <= $max) {
            return $s;
        }
        return rtrim(mb_substr($s, 0, $max - 1, 'UTF-8')) . '…';
    }

    /** RFC 4122 v4 UUID using wp_generate_uuid4() when available. */
    private static function uuid4(): string {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
