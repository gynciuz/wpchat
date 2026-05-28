<?php
/**
 * SCENARIO — content edits go through preview_content_change then
 * apply_content_change with a whitelisted confirmation phrase. The
 * apply tool must reject any phrase outside the whitelist, even if
 * the LLM tries to pass garbage. This is the structural gate on
 * LLM-callable writes.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tools;
use WPChat\Tests\TestCase;

class TwoStepContentEditTest extends TestCase {

    public function test_apply_rejects_non_whitelisted_confirmation(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Before']);

        // Phrase deliberately chosen to contain ZERO whitelisted affirmative
        // tokens. Don't reuse common natural phrases like "sure why not" —
        // the token-based matcher would treat "sure" as a confirmation.
        $result = Tools::apply_content_change([
            'target'       => ['kind' => 'wp_post', 'id' => $post_id],
            'field'        => 'title',
            'value'        => 'After',
            'confirmation' => 'maybe later',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('Before', get_post($post_id)->post_title, 'No write should happen without a real confirmation phrase.');
    }

    public function test_apply_accepts_lithuanian_confirmation(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Senas']);

        $result = Tools::apply_content_change([
            'target'       => ['kind' => 'wp_post', 'id' => $post_id],
            'field'        => 'title',
            'value'        => 'Naujas',
            'confirmation' => 'taip',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Naujas', get_post($post_id)->post_title);
    }

    public function test_preview_never_writes(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Untouched']);

        Tools::preview_content_change([
            'target' => ['kind' => 'wp_post', 'id' => $post_id],
            'field'  => 'title',
            'value'  => 'Should not be written',
        ]);

        $this->assertSame('Untouched', get_post($post_id)->post_title);
    }

    public function test_system_prompt_mandates_preview_before_apply(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');
        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        $this->assertStringContainsString('NEVER call apply', $system);
        $this->assertStringContainsString('preview_content_change', $system);
    }
}
