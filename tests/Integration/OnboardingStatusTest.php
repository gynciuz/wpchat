<?php
/**
 * Onboarding status REST endpoint — asserts the matrix shape per fixture.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Tests\TestCase;

class OnboardingStatusTest extends TestCase {

    public function test_status_returns_full_matrix_shape(): void {
        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $data = $response->get_data();

        foreach (['apiKey', 'model', 'permissions', 'wc', 'analytics', 'backends', 'integrations', 'user', 'site'] as $key) {
            $this->assertArrayHasKey($key, $data, "Status matrix missing key: $key");
        }
        $this->assertArrayHasKey('ok', $data['apiKey']);
        $this->assertArrayHasKey('current', $data['model']);
        $this->assertArrayHasKey('options', $data['model']);
        $this->assertIsArray($data['backends']);
    }

    public function test_status_reports_api_key_set_via_option(): void {
        // OnboardingPersistTest::test_api_key_save_rejects_when_constant_defined
        // defines WPCHAT_ANTHROPIC_API_KEY for the rest of the process; once
        // defined we can't unset it, so the apiKey.source becomes 'constant'
        // regardless of the option value. That's the correct runtime behavior;
        // skip this assertion in that case.
        if (defined('WPCHAT_ANTHROPIC_API_KEY') && WPCHAT_ANTHROPIC_API_KEY) {
            $this->markTestSkipped('WPCHAT_ANTHROPIC_API_KEY already defined by another test in this process.');
        }
        \update_option('wpchat_settings', ['anthropic_api_key' => 'sk-ant-abc1234567890', 'model' => 'claude-sonnet-4-6']);

        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        $this->assertTrue($data['apiKey']['ok']);
        $this->assertSame('option', $data['apiKey']['source']);
        $this->assertSame('••••7890', $data['apiKey']['masked']);
        $this->assertTrue($data['apiKey']['editable']);
    }

    public function test_status_reports_user_first_name(): void {
        $user = \get_user_by('id', $this->adminUserId);
        $user->first_name = 'Vlad';
        \wp_update_user($user);

        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        $this->assertSame('Vlad', $data['user']['first_name']);
    }

    public function test_status_requires_permission(): void {
        $sub = $this->factory()->user->create(['role' => 'subscriber']);
        \wp_set_current_user($sub);

        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $this->assertContains($response->get_status(), [401, 403]);
    }
}
