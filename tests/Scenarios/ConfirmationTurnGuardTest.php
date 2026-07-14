<?php
/**
 * SCENARIO — audit finding #2 (approach B): on the LLM path, a mutating apply
 * is only allowed if a preview / needs_confirmation ran in an EARLIER user turn
 * of the same conversation. This defeats prompt-injection where content the
 * model reads tells it to apply-and-self-confirm in a single turn.
 *
 * Uses the content path (wp_post edits) so no WooCommerce is required.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;

class ConfirmationTurnGuardTest extends TestCase {

    /** Injection: apply + confirmation in ONE turn, no earlier preview → refused. */
    public function test_llm_apply_without_earlier_preview_is_blocked(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Original']);

        $this->mockAnthropic
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $post_id],
                'field'        => 'title',
                'value'        => 'Hacked',
                'confirmation' => 'taip',
            ])
            ->enqueueEndTurn('done');

        $this->postChat('summarize recent activity');

        $this->assertSame(
            'Original',
            get_post($post_id)->post_title,
            'An apply confirmed in the same turn with no earlier preview must not write.'
        );
    }

    /** Normal flow: preview in turn 1, user confirms in turn 2 → applies. */
    public function test_preview_then_confirm_in_a_later_turn_applies(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Original']);

        // Turn 1 — the model previews the change and asks for confirmation.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $post_id],
                'field'  => 'title',
                'value'  => 'Updated',
            ])
            ->enqueueEndTurn('Rename the post to "Updated"? Please confirm.');
        $r1   = $this->postChat('rename the post to Updated');
        $conv = $r1['data']['conversation_id'] ?? '';

        $this->assertNotSame('', $conv, 'Expected a conversation id.');
        $this->assertSame('Original', get_post($post_id)->post_title, 'Preview must not write.');

        // Turn 2 (same conversation) — the user confirms.
        $this->mockAnthropic
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $post_id],
                'field'        => 'title',
                'value'        => 'Updated',
                'confirmation' => 'taip',
            ])
            ->enqueueEndTurn('Done.');
        $this->postChat('taip', $conv);

        $this->assertSame(
            'Updated',
            get_post($post_id)->post_title,
            'Apply after a preview in an earlier turn, with confirmation, must write.'
        );
    }
}
