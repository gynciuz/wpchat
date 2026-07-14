<?php
/**
 * History repository tests — real DB, real WP_UnitTestCase.
 *
 * Verifies:
 *  - Migration creates the table
 *  - append() persists each message
 *  - start_or_continue() returns the existing conversation when recent,
 *    a new UUID after the idle gap
 *  - list_conversations() respects the user_id filter (cross-user isolation)
 *  - get_conversation() refuses to return another user's conversation
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\History;
use ChatAdmin\Tests\TestCase;

class HistoryTest extends TestCase {

    public function test_migration_creates_table(): void {
        global $wpdb;
        $table = History::table_name();
        $found = $wpdb->get_var("SHOW TABLES LIKE '$table'");
        $this->assertSame($table, $found, 'chatadmin_messages table should exist after migration.');
    }

    public function test_append_inserts_row(): void {
        $conv = History::start_or_continue($this->adminUserId);
        $id   = History::append($this->adminUserId, $conv, 'user', 'hello', []);
        $this->assertGreaterThan(0, $id);

        $convo = History::get_conversation($this->adminUserId, $conv);
        $this->assertCount(1, $convo);
        $this->assertSame('user', $convo[0]['role']);
        $this->assertSame('hello', $convo[0]['content']);
    }

    public function test_append_rejects_invalid_role(): void {
        $conv = History::start_or_continue($this->adminUserId);
        $id   = History::append($this->adminUserId, $conv, 'system', 'rogue', []);
        $this->assertSame(0, $id, 'Only user/assistant roles should be persistable.');
    }

    public function test_start_or_continue_reuses_recent_conversation(): void {
        $first = History::start_or_continue($this->adminUserId);
        History::append($this->adminUserId, $first, 'user', 'a', []);

        $second = History::start_or_continue($this->adminUserId);
        $this->assertSame($first, $second, 'A second call within the idle gap should return the same UUID.');
    }

    public function test_start_or_continue_mints_new_uuid_after_idle_gap(): void {
        global $wpdb;
        $table = History::table_name();
        $past  = gmdate('Y-m-d H:i:s', time() - (History::IDLE_GAP_SECS + 60));

        $stale = History::start_or_continue($this->adminUserId);
        $wpdb->insert($table, [
            'conversation' => $stale,
            'user_id'      => $this->adminUserId,
            'role'         => 'user',
            'content'      => 'old',
            'tool_calls'   => null,
            'created_at'   => $past,
        ]);

        $fresh = History::start_or_continue($this->adminUserId);
        $this->assertNotSame($stale, $fresh, 'After the idle gap, a new conversation UUID should be minted.');
    }

    public function test_get_conversation_blocks_cross_user_access(): void {
        $other_user = $this->factory()->user->create(['role' => 'administrator']);
        $other_conv = History::start_or_continue($other_user);
        History::append($other_user, $other_conv, 'user', 'private message', []);

        // The admin (different user) tries to read it — should return [].
        $rows = History::get_conversation($this->adminUserId, $other_conv);
        $this->assertSame([], $rows, 'A user must not see another user\'s conversation.');
    }

    public function test_list_conversations_only_returns_own(): void {
        $conv = History::start_or_continue($this->adminUserId);
        History::append($this->adminUserId, $conv, 'user', 'mine', []);

        $other = $this->factory()->user->create(['role' => 'administrator']);
        $other_conv = History::start_or_continue($other);
        History::append($other, $other_conv, 'user', 'theirs', []);

        $list = History::list_conversations($this->adminUserId, 10);
        $ids  = array_column($list, 'conversation');
        $this->assertContains($conv, $ids);
        $this->assertNotContains($other_conv, $ids);
    }
}
