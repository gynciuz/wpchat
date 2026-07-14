<?php
/**
 * Telemetry — local error ring buffer, opt-in flag, and explicit report
 * delivery (endpoint POST with wp_mail fallback).
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Telemetry;
use ChatAdmin\Settings;
use ChatAdmin\Tests\TestCase;

class TelemetryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        \delete_option(Telemetry::LOG_OPTION);
    }

    public function test_log_appends_to_local_ring_buffer(): void {
        Telemetry::log('chat_failed', ['message' => 'boom', 'tool' => 'list_orders']);
        $recent = Telemetry::recent();
        $this->assertCount(1, $recent);
        $this->assertSame('chat_failed', $recent[0]['event']);
        $this->assertSame('boom', $recent[0]['message']);
        $this->assertSame('list_orders', $recent[0]['tool']);
    }

    public function test_ring_buffer_is_capped(): void {
        for ($i = 0; $i < Telemetry::MAX_ENTRIES + 10; $i++) {
            Telemetry::log('e', ['message' => "m$i"]);
        }
        $recent = Telemetry::recent(1000);
        $this->assertCount(Telemetry::MAX_ENTRIES, $recent);
        // Oldest dropped: the last entry is the most recent.
        $this->assertSame('m' . (Telemetry::MAX_ENTRIES + 9), end($recent)['message']);
    }

    public function test_telemetry_enabled_defaults_true_then_respects_optout(): void {
        \delete_option(Settings::OPTION);
        $this->assertTrue(Telemetry::telemetry_enabled(), 'Absent setting = default on.');

        \update_option(Settings::OPTION, ['telemetry' => false]);
        $this->assertFalse(Telemetry::telemetry_enabled());

        \update_option(Settings::OPTION, ['telemetry' => true]);
        $this->assertTrue(Telemetry::telemetry_enabled());
    }

    public function test_log_never_throws(): void {
        // Even with a totally broken option store shape, log() must swallow.
        \update_option(Telemetry::LOG_OPTION, 'not-an-array');
        Telemetry::log('weird', ['message' => 'x']);
        $this->assertIsArray(Telemetry::recent());
    }

    public function test_send_report_falls_back_to_email_when_no_endpoint(): void {
        // No CHATADMIN_SUPPORT_ENDPOINT constant in the test env → email path.
        $captured = [];
        $filter = function ($null, $atts) use (&$captured) {
            $captured = $atts;
            return true; // short-circuit actual sending
        };
        \add_filter('pre_wp_mail', $filter, 10, 2);

        $ok = Telemetry::send_report([
            'summary'  => 'It broke',
            'messages' => [['role' => 'user', 'content' => 'help']],
        ]);

        \remove_filter('pre_wp_mail', $filter, 10);

        $this->assertTrue($ok, 'Report should be delivered via wp_mail fallback.');
        $this->assertSame(Telemetry::support_email(), is_array($captured['to']) ? $captured['to'][0] : $captured['to']);
        $this->assertStringContainsString('ChatAdmin', $captured['subject']);
        $this->assertStringContainsString('It broke', $captured['message']);
    }

    public function test_report_to_endpoint_is_hmac_signed_when_secret_set(): void {
        $endpoint = function () { return 'https://collector.example/hook'; };
        $secret   = function () { return 'test-secret-123'; };
        \add_filter('chatadmin_support_endpoint', $endpoint);
        \add_filter('chatadmin_support_secret', $secret);

        $captured = [];
        $pre = function ($preempt, $args, $url) use (&$captured) {
            $captured = ['args' => $args, 'url' => $url];
            return ['response' => ['code' => 200], 'body' => '{"ok":true}', 'headers' => []];
        };
        \add_filter('pre_http_request', $pre, 10, 3);

        $ok = Telemetry::send_report(['kind' => 'support_report', 'note' => 'hi']);

        \remove_filter('pre_http_request', $pre, 10);
        \remove_filter('chatadmin_support_endpoint', $endpoint);
        \remove_filter('chatadmin_support_secret', $secret);

        $this->assertTrue($ok, 'Report should deliver to the endpoint.');
        $this->assertSame('https://collector.example/hook', $captured['url']);
        $expected = 'sha256=' . hash_hmac('sha256', $captured['args']['body'], 'test-secret-123');
        $this->assertSame($expected, $captured['args']['headers']['X-ChatAdmin-Signature'] ?? '');
    }

    public function test_report_to_endpoint_has_no_signature_without_secret(): void {
        $endpoint = function () { return 'https://collector.example/hook'; };
        \add_filter('chatadmin_support_endpoint', $endpoint);

        $captured = [];
        $pre = function ($preempt, $args) use (&$captured) {
            $captured = $args;
            return ['response' => ['code' => 200], 'body' => '{"ok":true}', 'headers' => []];
        };
        \add_filter('pre_http_request', $pre, 10, 3);

        Telemetry::send_report(['kind' => 'support_report']);

        \remove_filter('pre_http_request', $pre, 10);
        \remove_filter('chatadmin_support_endpoint', $endpoint);

        $this->assertArrayNotHasKey('X-ChatAdmin-Signature', $captured['headers'] ?? []);
    }
}
