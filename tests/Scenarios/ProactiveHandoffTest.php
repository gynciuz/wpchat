<?php
/**
 * SCENARIO — when a user asks for something the tools can't directly
 * accomplish (e.g. delete an order), the assistant must NOT dead-end on
 * "I can't". It must call get_admin_url and hand the user a link.
 *
 * This locks in the proactivity rule from 2026-05-26 feedback.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;

class ProactiveHandoffTest extends TestCase {

    public function test_delete_order_request_triggers_get_admin_url(): void {
        // Script: assistant immediately calls get_admin_url, then ends with a handoff.
        $this->mockAnthropic
            ->enqueueToolUse('get_admin_url', ['resource' => 'order', 'id' => 2842])
            ->enqueueEndTurn('Opening order #2842 in WP admin → click "Move to Trash" then refresh here.');

        $response = $this->postChat('Delete order 2842');

        $this->assertSame(200, $response['status']);
        $calls = $this->mockAnthropic->scriptedToolCalls();
        $this->assertSame('get_admin_url', $calls[0]['name'], 'Assistant must reach for the admin URL when blocked.');

        $this->assertStringContainsString('refresh', strtolower($response['data']['text']));
    }

    public function test_system_prompt_includes_proactivity_rule(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        // The prompt MUST teach the LLM to never end on "I can't".
        $this->assertStringContainsString('Never say "I can\'t" and stop there', $system);
        // It MUST teach the LLM to call get_admin_url for blocked paths.
        $this->assertStringContainsString('get_admin_url', $system);
        // It MUST forbid hallucinated technical excuses.
        $this->assertStringContainsString('Never invent technical explanations', $system);
    }
}
