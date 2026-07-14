<?php
/**
 * SCENARIO — ChatAdmin can TRIGGER order emails / order actions instead of
 * dead-ending with a manual "go click this in wp-admin" handoff.
 *
 * Motivating case (Gentleman's Empire): "pakartok dovanų kupono siuntimą
 * užsakymui 2847" — the assistant should discover the order's actions
 * (PW Gift Cards "Resend gift cards" lives among them) and trigger the
 * matching one, rather than describing where to click.
 *
 * The test scaffold has no WooCommerce loaded, so the tools resolve and
 * dispatch but bottom out in require_wc()'s "WooCommerce is not active."
 * — which is enough to prove the routing + system-prompt wiring.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class OrderActionsTest extends TestCase {

    public function test_both_order_action_tools_are_defined_and_mapped(): void {
        $names = array_column(Tools::definitions(), 'name');
        $this->assertContains('list_order_actions', $names);
        $this->assertContains('trigger_order_action', $names);

        $impls = Tools::implementations();
        $this->assertTrue(is_callable($impls['list_order_actions'] ?? null));
        $this->assertTrue(is_callable($impls['trigger_order_action'] ?? null));
    }

    public function test_trigger_requires_order_id_and_action(): void {
        foreach (Tools::definitions() as $def) {
            if ($def['name'] === 'trigger_order_action') {
                $this->assertSame(['order_id', 'action'], $def['input_schema']['required']);
            }
            if ($def['name'] === 'list_order_actions') {
                $this->assertSame(['order_id'], $def['input_schema']['required']);
            }
        }
    }

    public function test_no_bulk_field_on_action_tools(): void {
        // Same guarantee as the bulk-action ban: one order at a time.
        foreach (Tools::definitions() as $def) {
            if (!in_array($def['name'], ['list_order_actions', 'trigger_order_action'], true)) {
                continue;
            }
            $props = $def['input_schema']['properties'] ?? [];
            foreach (['order_ids', 'ids', 'bulk', 'apply_to_all'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $props, "{$def['name']} must not expose `$forbidden`.");
            }
        }
    }

    public function test_system_prompt_instructs_resend_via_tools(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('list_order_actions', $system);
        $this->assertStringContainsString('trigger_order_action', $system);
        // Must steer AWAY from the old manual-handoff behaviour for resends.
        $this->assertStringContainsString('Resending emails', $system);
    }

    public function test_chat_routes_to_list_order_actions_tool(): void {
        // The assistant asks for the order's actions, then we end the turn.
        $this->mockAnthropic
            ->enqueueToolUse('list_order_actions', ['order_id' => 2847])
            ->enqueueEndTurn('Užsakymas 2847 — galimi veiksmai pateikti.');

        $res = $this->postChat('pakartok dovanų kupono siuntimą užsakymui 2847');

        $this->assertSame(200, $res['status']);
        $calls = $res['data']['tool_calls'] ?? [];
        $this->assertNotEmpty($calls);
        $this->assertSame('list_order_actions', $calls[0]['name']);
        // No WC in the scaffold → graceful error, not a fatal.
        $this->assertArrayHasKey('error', $calls[0]['output']);
    }

    public function test_trigger_guards_on_woocommerce_absence(): void {
        // Called directly (not via the runner), require_wc() throws — the
        // tool never silently no-ops when WC is missing. The runner wraps
        // this in try/catch and surfaces it as {error: ...} to the model.
        $this->expectException(\RuntimeException::class);
        Tools::trigger_order_action(['order_id' => 2847, 'action' => 'send_order_details']);
    }
}
