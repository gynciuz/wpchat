<?php
/**
 * SCENARIO — regression for the "confirmation loop that burns API budget".
 *
 * Two real-world flows the earlier-turn confirmation guard (finding #2) got
 * wrong, sending the model into an apply→needs_confirmation loop until the
 * Anthropic credit balance ran out:
 *
 *  A. Editing TWO targets at once (e.g. two pages in one request). The pending
 *     store kept a single slot per conversation, so the second preview clobbered
 *     the first; on confirm, each apply's target mismatched the stored one and
 *     BOTH were refused forever — each refusal re-recording over the other.
 *  B. The model re-previewing on the confirmation turn before applying (common,
 *     and what the system prompt nudges it toward). The fresh preview stamped
 *     the current turn, so the "preview ran in an EARLIER turn" check could
 *     never pass and the apply looped.
 *
 * Both must now converge: the security property (a preview in a strictly
 * earlier user turn + the user's own confirmation) is preserved, but a
 * legitimate confirm actually writes.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tests\TestCase;

class MultiTargetConfirmTest extends TestCase {

    /** Two pages previewed in turn 1, confirmed in turn 2 → BOTH apply. */
    public function test_two_targets_previewed_together_both_apply_on_confirm(): void {
        $p1 = $this->factory()->post->create(['post_title' => 'Original 1']);
        $p2 = $this->factory()->post->create(['post_title' => 'Original 2']);

        // Turn 1 — the model previews both changes, then asks for confirmation.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $p1],
                'field'  => 'title',
                'value'  => 'Updated 1',
            ])
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $p2],
                'field'  => 'title',
                'value'  => 'Updated 2',
            ])
            ->enqueueEndTurn('Rename both pages? Please confirm.');
        $r1   = $this->postChat('rename both pages');
        $conv = $r1['data']['conversation_id'] ?? '';
        $this->assertNotSame('', $conv);
        $this->assertSame('Original 1', get_post($p1)->post_title, 'Preview must not write.');
        $this->assertSame('Original 2', get_post($p2)->post_title, 'Preview must not write.');

        // Turn 2 — user confirms; the model applies both.
        $this->mockAnthropic
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $p1],
                'field'        => 'title',
                'value'        => 'Updated 1',
                'confirmation' => 'taip',
            ])
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $p2],
                'field'        => 'title',
                'value'        => 'Updated 2',
                'confirmation' => 'taip',
            ])
            ->enqueueEndTurn('Both done.');
        $this->postChat('taip', $conv);

        $this->assertSame('Updated 1', get_post($p1)->post_title, 'First target must apply.');
        $this->assertSame('Updated 2', get_post($p2)->post_title, 'Second target must apply.');
    }

    /** Re-previewing on the confirm turn must NOT block the apply (loop bug). */
    public function test_repreview_on_confirm_turn_still_applies(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Original']);

        // Turn 1 — preview + ask to confirm.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $post_id],
                'field'  => 'title',
                'value'  => 'Updated',
            ])
            ->enqueueEndTurn('Rename it? Please confirm.');
        $r1   = $this->postChat('rename the post to Updated');
        $conv = $r1['data']['conversation_id'] ?? '';

        // Turn 2 — the model previews AGAIN (as it tends to), then applies.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $post_id],
                'field'  => 'title',
                'value'  => 'Updated',
            ])
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
            'A re-preview on the confirm turn must not push the recorded turn forward and block the apply.'
        );
    }

    /**
     * The security guard survives the multi-slot rewrite: an apply with NO
     * earlier preview (injection steering apply-and-self-confirm in one turn)
     * is still refused even while other targets have legitimate pending records.
     */
    public function test_unpreviewed_target_still_blocked_alongside_a_previewed_one(): void {
        $good = $this->factory()->post->create(['post_title' => 'Good']);
        $evil = $this->factory()->post->create(['post_title' => 'Evil']);

        // Turn 1 — legitimate preview for `good` only.
        $this->mockAnthropic
            ->enqueueToolUse('preview_content_change', [
                'target' => ['kind' => 'wp_post', 'id' => $good],
                'field'  => 'title',
                'value'  => 'Good Updated',
            ])
            ->enqueueEndTurn('Confirm?');
        $r1   = $this->postChat('rename good');
        $conv = $r1['data']['conversation_id'] ?? '';

        // Turn 2 — user confirms; model applies `good` (legit) AND tries to
        // sneak in `evil`, which never had a preview.
        $this->mockAnthropic
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $good],
                'field'        => 'title',
                'value'        => 'Good Updated',
                'confirmation' => 'taip',
            ])
            ->enqueueToolUse('apply_content_change', [
                'target'       => ['kind' => 'wp_post', 'id' => $evil],
                'field'        => 'title',
                'value'        => 'Evil Updated',
                'confirmation' => 'taip',
            ])
            ->enqueueEndTurn('done');
        $this->postChat('taip', $conv);

        $this->assertSame('Good Updated', get_post($good)->post_title, 'Previewed target applies.');
        $this->assertSame('Evil', get_post($evil)->post_title, 'Un-previewed target must stay blocked.');
    }
}
