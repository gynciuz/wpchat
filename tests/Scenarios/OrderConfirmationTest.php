<?php
/**
 * SCENARIO — order mutations are gated behind an explicit confirmation step,
 * mirroring the content preview→confirm pipeline. A status change, an order
 * action (resend email), or a customer-visible note can email the customer or
 * fire plugin side-effects, so the chat/LLM path must pause and get the user's
 * go-ahead before acting (WordPress.com's AI agent does the same).
 *
 * WooCommerce is not loaded in the scaffold (require_wc() throws before the
 * gate runs), so — like OrderActionsTest — this locks in the SCHEMA and
 * SYSTEM-PROMPT wiring rather than executing a live mutation. The runtime gate
 * itself is Tools::needs_confirmation(), exercised end-to-end in manual/QA.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tools;
use WPChat\Tests\TestCase;

class OrderConfirmationTest extends TestCase {

    /** The three mutating order tools expose an optional `confirmation` param. */
    public function test_mutating_order_tools_accept_confirmation(): void {
        $gated = ['update_order_status', 'add_order_note', 'trigger_order_action'];
        $byName = [];
        foreach (Tools::definitions() as $def) {
            $byName[$def['name']] = $def;
        }

        foreach ($gated as $name) {
            $this->assertArrayHasKey($name, $byName, "$name must be defined.");
            $props = $byName[$name]['input_schema']['properties'] ?? [];
            $this->assertArrayHasKey('confirmation', $props, "$name must accept a confirmation phrase.");
            // Must stay OPTIONAL so the first (prompting) call can omit it.
            $this->assertNotContains(
                'confirmation',
                $byName[$name]['input_schema']['required'] ?? [],
                "$name.confirmation must be optional so the model can call once to trigger the prompt."
            );
        }
    }

    /** Tool descriptions steer the model into the call-twice confirm flow. */
    public function test_tool_descriptions_mention_confirmation(): void {
        foreach (Tools::definitions() as $def) {
            if (in_array($def['name'], ['update_order_status', 'trigger_order_action'], true)) {
                $this->assertStringContainsStringIgnoringCase(
                    'confirm',
                    $def['description'],
                    "{$def['name']} description must explain the confirmation step."
                );
            }
        }
    }

    /** The system prompt carries the confirm-before-mutating guardrail. */
    public function test_system_prompt_requires_order_confirmation(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('Confirm before mutating an order', $system);
        $this->assertStringContainsString('needs_confirmation', $system);
    }
}
