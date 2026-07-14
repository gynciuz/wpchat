<?php
/**
 * The server-side pending-confirmation record that binds a mutating apply to a
 * real, later user turn (audit finding #2, approach B). Consuming a record
 * requires a matching target AND a strictly later turn than the one that
 * created it — so a preview and an apply in the SAME turn (a prompt-injection
 * doing both at once) cannot satisfy it.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\PendingConfirmation;
use ChatAdmin\Tests\TestCase;

class PendingConfirmationTest extends TestCase {

    public function test_consume_requires_a_strictly_later_turn(): void {
        PendingConfirmation::record('conv-1', 'content:abc', 3);

        // Same turn as the record → not consumable (blocks same-turn injection).
        $this->assertFalse(PendingConfirmation::consume('conv-1', 'content:abc', 3));
        // Earlier turn → not consumable.
        $this->assertFalse(PendingConfirmation::consume('conv-1', 'content:abc', 2));
        // Later turn → consumable.
        $this->assertTrue(PendingConfirmation::consume('conv-1', 'content:abc', 4));
    }

    public function test_consume_is_single_use(): void {
        PendingConfirmation::record('conv-1', 'content:abc', 1);
        $this->assertTrue(PendingConfirmation::consume('conv-1', 'content:abc', 2));
        // Already consumed → gone.
        $this->assertFalse(PendingConfirmation::consume('conv-1', 'content:abc', 3));
    }

    public function test_consume_rejects_a_different_target(): void {
        PendingConfirmation::record('conv-1', 'order:2833:status', 1);
        // A confirmation minted for one target must not apply to another.
        $this->assertFalse(PendingConfirmation::consume('conv-1', 'content:abc', 2));
    }

    public function test_consume_is_scoped_per_conversation(): void {
        PendingConfirmation::record('conv-1', 'content:abc', 1);
        $this->assertFalse(PendingConfirmation::consume('conv-2', 'content:abc', 2));
    }

    public function test_absent_record_is_not_consumable(): void {
        $this->assertFalse(PendingConfirmation::consume('nope', 'content:abc', 9));
    }
}
