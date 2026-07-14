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

    /** Injection: publish + confirmation in ONE turn, no earlier turn → refused. */
    public function test_llm_publish_without_earlier_turn_is_blocked(): void {
        $post_id = $this->factory()->post->create(['post_status' => 'draft', 'post_title' => 'Draft']);

        $this->mockAnthropic
            ->enqueueToolUse('publish_content', ['post_id' => $post_id, 'confirmation' => 'taip'])
            ->enqueueEndTurn('done');

        $this->postChat('what drafts do I have?');

        $this->assertSame(
            'draft',
            get_post_status($post_id),
            'A publish confirmed in the same turn, with no earlier confirmation turn, must not publish.'
        );
    }

    /** Normal: model surfaces the draft (needs_confirmation), user confirms next turn → published. */
    public function test_publish_after_confirm_in_a_later_turn_publishes(): void {
        $post_id = $this->factory()->post->create(['post_status' => 'draft', 'post_title' => 'Draft']);

        $this->mockAnthropic
            ->enqueueToolUse('publish_content', ['post_id' => $post_id])
            ->enqueueEndTurn('Publish this draft? Please confirm.');
        $r1   = $this->postChat('publish my draft');
        $conv = $r1['data']['conversation_id'] ?? '';
        $this->assertSame('draft', get_post_status($post_id), 'First call must not publish.');

        $this->mockAnthropic
            ->enqueueToolUse('publish_content', ['post_id' => $post_id, 'confirmation' => 'taip'])
            ->enqueueEndTurn('Published.');
        $this->postChat('taip', $conv);

        $this->assertSame('publish', get_post_status($post_id), 'Confirm in a later turn must publish.');
    }
}
