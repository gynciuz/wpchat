<?php
/**
 * Base test case for WPChat tests that need a booted WordPress.
 *
 * Provides:
 *  - a fresh admin user (matches the editor+ capability check in REST)
 *  - a helper to dispatch the /chat REST endpoint as that user
 *  - a MockAnthropic instance registered + torn down per test
 *
 * Extends WP_UnitTestCase so DB state is rolled back between tests via
 * WordPress's setUp/tearDown transactions.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests;

abstract class TestCase extends \WP_UnitTestCase {

    protected MockAnthropic $mockAnthropic;
    protected int $adminUserId;

    public function set_up() {
        parent::set_up();
        $this->mockAnthropic = new MockAnthropic();
        $this->mockAnthropic->register();

        // Ensure an Anthropic key exists so the early-return in REST doesn't
        // short-circuit. The mock filter intercepts before the key is used.
        \update_option('wpchat_settings', [
            'anthropic_api_key' => 'sk-ant-test-fixture',
            'model'             => 'mock-claude',
        ]);

        $this->adminUserId = $this->factory()->user->create([
            'role'       => 'administrator',
            'user_login' => 'wpchat-test-admin-' . uniqid(),
        ]);
        \wp_set_current_user($this->adminUserId);
    }

    public function tear_down() {
        $this->mockAnthropic->unregister();
        parent::tear_down();
    }

    /**
     * Dispatch POST /wpchat/v1/chat with the given message text.
     * Returns the decoded JSON response body.
     */
    protected function postChat(string $userMessage, ?string $conversationId = null): array {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/chat');
        $request->set_header('Content-Type', 'application/json');
        $body = [
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];
        if ($conversationId !== null) {
            $body['conversation_id'] = $conversationId;
        }
        $request->set_body(json_encode($body));
        $response = \rest_get_server()->dispatch($request);
        return [
            'status' => $response->get_status(),
            'data'   => $response->get_data(),
        ];
    }

    protected function getConversations(): array {
        $request  = new \WP_REST_Request('GET', '/wpchat/v1/conversations');
        $response = \rest_get_server()->dispatch($request);
        return ['status' => $response->get_status(), 'data' => $response->get_data()];
    }

    protected function getConversation(string $id): array {
        $request  = new \WP_REST_Request('GET', '/wpchat/v1/conversations/' . $id);
        $response = \rest_get_server()->dispatch($request);
        return ['status' => $response->get_status(), 'data' => $response->get_data()];
    }
}
