<?php
/**
 * SCENARIO — the in-product help chat + "Report a problem" channel.
 *
 * Help mode runs the same chat endpoint with `mode: 'support'`: NO tools,
 * a FAQ-grounded support prompt, and it is ephemeral (never persisted into
 * the order-management history). The report route delivers the user's note +
 * recent conversation to the developer (wp_mail fallback in the test env).
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Telemetry;
use WPChat\Tests\TestCase;

class SupportTest extends TestCase {

    private function postSupportChat(string $msg): array {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/chat');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'mode'     => 'support',
            'messages' => [['role' => 'user', 'content' => $msg]],
        ]));
        $response = \rest_get_server()->dispatch($request);
        return ['status' => $response->get_status(), 'data' => $response->get_data()];
    }

    public function test_support_mode_uses_no_tools_and_help_prompt(): void {
        $this->mockAnthropic->enqueueEndTurn('Go to console.anthropic.com → API Keys.');
        $res = $this->postSupportChat('how do I get an API key?');

        $this->assertSame(200, $res['status']);
        $request = $this->mockAnthropic->lastRequest();
        // No tools offered in support mode.
        $this->assertArrayNotHasKey('tools', $request, 'Support mode must not expose any tools.');
        // FAQ/help system prompt, not the order-management one.
        $this->assertStringContainsString('WPChat Help', $request['system'] ?? '');
    }

    public function test_support_chat_is_not_persisted_to_history(): void {
        $this->mockAnthropic->enqueueEndTurn('answer');
        $this->postSupportChat('what can WPChat do?');

        $convos = $this->getConversations();
        $this->assertSame(200, $convos['status']);
        $this->assertEmpty($convos['data']['conversations'] ?? [], 'Help chat must not pollute conversation history.');
    }

    public function test_report_route_delivers_via_email_fallback(): void {
        $captured = [];
        $filter = function ($null, $atts) use (&$captured) {
            $captured = $atts;
            return true;
        };
        \add_filter('pre_wp_mail', $filter, 10, 2);

        $request = new \WP_REST_Request('POST', '/wpchat/v1/support');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['note' => 'orders list is blank', 'error' => 'HTTP 500']));
        $response = \rest_get_server()->dispatch($request);

        \remove_filter('pre_wp_mail', $filter, 10);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['ok'] ?? false);
        $this->assertStringContainsString('orders list is blank', $captured['message'] ?? '');
        $this->assertSame(Telemetry::support_email(), is_array($captured['to'] ?? null) ? $captured['to'][0] : ($captured['to'] ?? ''));
    }
}
