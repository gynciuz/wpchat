<?php
/**
 * SCENARIO — the order-table inline actions (3-dot menu in the chat UI)
 * MUST bypass the LLM entirely. Vlad's wife tapping "change status →
 * Panaudotas" should produce zero Anthropic API spend and zero
 * hallucination surface — just a direct REST call to wc_get_order.
 *
 * Locks in the architecture of B4 (task #14): the direct-action
 * endpoints work without an Anthropic key being configured, and never
 * invoke the Anthropic mock during a flow.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;

class DirectActionsTest extends TestCase {

    public function test_order_statuses_endpoint_works_without_anthropic_key(): void {
        // Clear the API key — direct actions don't need it.
        \update_option('wpchat_settings', [
            'anthropic_api_key' => '',
            'model'             => 'mock-claude',
        ]);

        $request  = new \WP_REST_Request('GET', '/wpchat/v1/actions/order-statuses');
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('statuses', $data);
        $this->assertIsArray($data['statuses']);
    }

    public function test_status_change_endpoint_does_not_invoke_anthropic(): void {
        // Even with the mock registered, a direct action must NOT pop anything off the queue.
        $this->mockAnthropic->enqueueEndTurn('this should never be returned');

        // No real WC order in the test scaffold, so we expect a 400 from
        // update_order_status with "Order not found." — that's fine. The
        // point is the request didn't go through the LLM.
        $request = new \WP_REST_Request('POST', '/wpchat/v1/actions/order/9999/status');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['status' => 'completed']));
        \rest_get_server()->dispatch($request);

        // The mock should have ZERO recorded outgoing requests — proof
        // the LLM was not touched.
        $this->assertCount(0, $this->mockAnthropic->recordedRequests(), 'Direct status-change action must not invoke Anthropic.');
    }

    public function test_note_endpoint_does_not_invoke_anthropic(): void {
        $this->mockAnthropic->enqueueEndTurn('unused');

        $request = new \WP_REST_Request('POST', '/wpchat/v1/actions/order/9999/note');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['note' => 'a note', 'customer_visible' => false]));
        \rest_get_server()->dispatch($request);

        $this->assertCount(0, $this->mockAnthropic->recordedRequests(), 'Direct note-add action must not invoke Anthropic.');
    }

    public function test_direct_action_routes_require_same_permission_as_chat(): void {
        // Drop the test user back to a no-cap subscriber.
        $sub = $this->factory()->user->create(['role' => 'subscriber']);
        \wp_set_current_user($sub);

        $request = new \WP_REST_Request('POST', '/wpchat/v1/actions/order/1/status');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['status' => 'completed']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertContains($response->get_status(), [401, 403], 'Subscribers must not reach the direct-action endpoints.');
    }
}
