<?php
/**
 * SCENARIO — WPChat can answer site-traffic questions ("how many visitors
 * this week?") by routing to the auto-detected analytics plugin via the
 * `get_traffic_summary` tool, instead of dead-ending.
 *
 * The test scaffold has no analytics plugin installed, so AnalyticsRouter
 * detects nothing and the tool returns a graceful no-provider error —
 * enough to prove the tool + system-prompt wiring and the routing
 * contract without standing up Jetpack/WP Statistics/etc.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tools;
use WPChat\Tests\TestCase;

class TrafficSummaryTest extends TestCase {

    public function test_tool_is_defined_and_mapped(): void {
        $names = array_column(Tools::definitions(), 'name');
        $this->assertContains('get_traffic_summary', $names);

        $impls = Tools::implementations();
        $this->assertTrue(is_callable($impls['get_traffic_summary'] ?? null));
    }

    public function test_date_range_is_an_enum_and_no_bulk_field(): void {
        foreach (Tools::definitions() as $def) {
            if ($def['name'] !== 'get_traffic_summary') {
                continue;
            }
            $props = $def['input_schema']['properties'] ?? [];
            $this->assertArrayHasKey('date_range', $props);
            $this->assertSame(
                ['today', 'yesterday', 'this_week', 'last_7_days', 'last_30_days'],
                $props['date_range']['enum'] ?? []
            );
            // date_range is optional — the impl defaults to this_week.
            $this->assertArrayNotHasKey('required', $def['input_schema']);
        }
    }

    public function test_system_prompt_instructs_traffic_via_tool(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('get_traffic_summary', $system);
        $this->assertStringContainsString('Site analytics', $system);
    }

    public function test_chat_routes_to_traffic_summary_tool(): void {
        $this->mockAnthropic
            ->enqueueToolUse('get_traffic_summary', ['date_range' => 'this_week'])
            ->enqueueEndTurn('Šią savaitę lankytojų skaičiaus nepavyko gauti — analitikos įskiepis neaptiktas.');

        $res = $this->postChat('kiek lankytojų šią savaitę?');

        $this->assertSame(200, $res['status']);
        $calls = $res['data']['tool_calls'] ?? [];
        $this->assertNotEmpty($calls);
        $this->assertSame('get_traffic_summary', $calls[0]['name']);
    }

    public function test_no_provider_returns_graceful_error_not_fatal(): void {
        // No analytics plugin in the scaffold → pick() is null → the tool
        // returns an error-shaped array listing what was detected (none),
        // never a fatal.
        $out = Tools::get_traffic_summary(['date_range' => 'this_week']);
        $this->assertArrayHasKey('error', $out);
        $this->assertArrayHasKey('detected', $out);
        $this->assertSame([], $out['detected']);
    }
}
