<?php
/**
 * SCENARIO — audit finding #2 (approach B): on the LLM path, a mutating apply
 * is only allowed if a preview / needs_confirmation ran in an EARLIER user turn
 * of the same conversation. This defeats prompt-injection where content the
 * model reads tells it to apply-and-self-confirm in a single turn.
 *
 * Uses the content path (wp_post edits) so no WooCommerce is required.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tests\TestCase;

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

    /**
     * Injection across TWO turns: a preview ran in turn 1, but in turn 2 the
     * user's real message is NOT a confirmation — the model supplies a
     * `confirmation` phrase on its own (e.g. steered by injected order/content
     * text still in context). Consent must come from the user's actual message,
     * not a model-authored argument, so this must NOT write.
     */
    public function test_apply_with_model_confirmation_but_no_user_consent_is_blocked(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Original']);

        // Turn 1 — a legitimate preview, so a pending record is minted.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $post_id],
                'field'  => 'title',
                'value'  => 'Hacked',
            ])
            ->enqueueEndTurn('Rename to "Hacked"? Please confirm.');
        $r1   = $this->postChat('summarize the latest order');
        $conv = $r1['data']['conversation_id'] ?? '';
        $this->assertNotSame('', $conv);

        // Turn 2 — the user does NOT confirm ("thanks"), but the model calls
        // apply anyway with a self-supplied confirmation phrase.
        $this->mockAnthropic
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $post_id],
                'field'        => 'title',
                'value'        => 'Hacked',
                'confirmation' => 'taip',
            ])
            ->enqueueEndTurn('done');
        $this->postChat('thanks for the summary', $conv);

        $this->assertSame(
            'Original',
            get_post($post_id)->post_title,
            'Apply must require the real user message to be a confirmation, not a model-supplied phrase.'
        );
    }

    /**
     * The same consent binding for order mutations: with an earlier-turn
     * preview present, a model-supplied confirmation and a non-confirming user
     * message must still be refused.
     */
    public function test_order_status_with_model_confirmation_but_no_user_consent_is_blocked(): void {
        if (!function_exists('wc_get_order')) {
            $this->markTestSkipped('WooCommerce not loaded.');
        }
        $order = wc_create_order();
        $order->set_status('processing');
        $order->save();
        $oid = $order->get_id();

        // Turn 1 — needs_confirmation records a pending entry for this target.
        $this->mockAnthropic
            ->enqueueToolUse('update_order_status', ['order_id' => $oid, 'status' => 'completed'])
            ->enqueueEndTurn('Mark it completed? Please confirm.');
        $r1   = $this->postChat('what is the latest order');
        $conv = $r1['data']['conversation_id'] ?? '';

        // Turn 2 — user does not consent; model supplies "taip" itself.
        $this->mockAnthropic
            ->enqueueToolUse('update_order_status', ['order_id' => $oid, 'status' => 'completed', 'confirmation' => 'taip'])
            ->enqueueEndTurn('done');
        $this->postChat('ok cool, anything else new?', $conv);

        $this->assertSame(
            'processing',
            wc_get_order($oid)->get_status(),
            'Order status change must require a real user confirmation message.'
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
