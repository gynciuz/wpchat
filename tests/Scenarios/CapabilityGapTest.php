<?php
/**
 * SCENARIO — a chat turn that dead-ends in a wp-admin handoff (the model
 * couldn't do it in-app) must record a `capability_gap` signal carrying only
 * the ANONYMISED request, so the product's top unmet requests can be spotted
 * and prioritised. Locks in the auto-detector added for the trx_team feedback.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Telemetry;
use ChatAdmin\Tests\TestCase;

class CapabilityGapTest extends TestCase {

    public function test_handoff_with_no_mutation_logs_redacted_capability_gap(): void {
        // Assistant can't edit the item and hands off with an admin link.
        $this->mockAnthropic
            ->enqueueToolUse('get_admin_url', ['resource' => 'post', 'id' => 2645])
            ->enqueueEndTurn('Atidarau puslapį WP administracijoje → pataisykite ten ir grįžkite.');

        // Request carries PII (email + long number) that must be scrubbed.
        $this->postChat('pataisyk meistrą, klientas jonas@example.com uzsakymas 123456');

        $gap = null;
        foreach (Telemetry::recent(50) as $e) {
            if (($e['event'] ?? '') === 'capability_gap') {
                $gap = $e;
            }
        }

        $this->assertNotNull($gap, 'A handoff with no in-app mutation must record a capability_gap.');
        $this->assertSame('post', $gap['code'] ?? '', 'The handed-off resource is recorded.');
        $this->assertStringContainsString('[email]', (string) ($gap['message'] ?? ''));
        $this->assertStringContainsString('[number]', (string) ($gap['message'] ?? ''));
        $this->assertStringNotContainsString('jonas@example.com', (string) ($gap['message'] ?? ''));
        // The intent text survives so the signal is actually useful.
        $this->assertStringContainsString('pataisyk', (string) ($gap['message'] ?? ''));
    }

    public function test_no_handoff_means_no_capability_gap(): void {
        $this->mockAnthropic->enqueueEndTurn('Sveiki! Kuo galiu padėti?');
        $this->postChat('labas');

        $events = array_column(Telemetry::recent(50), 'event');
        $this->assertNotContains('capability_gap', $events, 'A normal reply must not log a gap.');
    }
}
