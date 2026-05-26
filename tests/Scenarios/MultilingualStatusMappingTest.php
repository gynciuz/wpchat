<?php
/**
 * SCENARIO — the system prompt must give the LLM an explicit
 * "user word → canonical slug" mapping for the standard WC statuses
 * in Lithuanian / Russian / Polish / English. Without this, the LLM
 * tries to guess the slug and either fails silently or invents a
 * config-typo story (the 2026-05-26 "ancelled" hallucination).
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;

class MultilingualStatusMappingTest extends TestCase {

    public function test_prompt_contains_lithuanian_cancelled_mapping(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // Lithuanian word for "cancelled" must be explicitly mapped to the slug.
        $this->assertStringContainsString('atšauktas', strtolower($system));
        $this->assertStringContainsString('cancelled', $system);
    }

    public function test_prompt_contains_russian_status_mappings(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('отменён', $system, 'Russian "cancelled" must be listed.');
        $this->assertStringContainsString('выполнен', $system, 'Russian "completed" must be listed.');
    }

    public function test_prompt_forbids_inventing_status_explanations(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // Negative anchor: explicit "list available slugs back, do not guess".
        $this->assertStringContainsString('list the available slugs back', $system);
        $this->assertStringContainsString('DO NOT guess', $system);
    }

    public function test_update_order_status_returns_available_list_on_unknown(): void {
        // Bypass real wc_get_order — we just want to assert the contract: a
        // bad status name comes back with available_statuses, so the LLM can
        // surface them verbatim instead of hallucinating.
        $this->markTestSkipped('Requires WC fixture; covered by manual exploratory test in REST integration.');
    }
}
