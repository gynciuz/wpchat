<?php
/**
 * Onboarding interactive endpoints — api-key save, model save, complete.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Onboarding;
use WPChat\Tests\TestCase;

class OnboardingPersistTest extends TestCase {

    public function test_api_key_save_updates_option(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['key' => 'sk-ant-fixture-abcd']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $options = \get_option('wpchat_settings');
        $this->assertSame('sk-ant-fixture-abcd', $options['anthropic_api_key']);
    }

    public function test_api_key_save_rejects_malformed(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['key' => 'not a real key']));
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_api_key_save_rejects_when_constant_defined(): void {
        // Constants can't be undefined; if it's not already defined, define it.
        // Other tests don't set it, so this is the first define.
        if (!defined('WPCHAT_ANTHROPIC_API_KEY')) {
            define('WPCHAT_ANTHROPIC_API_KEY', 'sk-ant-from-constant');
        }
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['key' => 'sk-ant-from-option']));
        $response = \rest_get_server()->dispatch($request);

        // 409 conflict — the option would be ignored anyway.
        $this->assertSame(409, $response->get_status());
    }

    public function test_model_save_updates_option(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/model');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['model' => 'claude-opus-4-7']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $options = \get_option('wpchat_settings');
        $this->assertSame('claude-opus-4-7', $options['model']);
    }

    public function test_model_save_rejects_unknown(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/model');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['model' => 'gpt-4']));
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_complete_sets_user_meta(): void {
        $request  = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/complete');
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('1', \get_user_meta($this->adminUserId, Onboarding::USER_META_KEY, true));
    }

    public function test_should_show_for_user_logic(): void {
        // No meta yet → should show.
        $this->assertTrue(Onboarding::should_show_for_user($this->adminUserId));

        // Mark done → should not show.
        \update_user_meta($this->adminUserId, Onboarding::USER_META_KEY, '1');
        $this->assertFalse(Onboarding::should_show_for_user($this->adminUserId));

        // ?onboarding=1 forces it regardless of meta.
        $_GET['onboarding'] = '1';
        $this->assertTrue(Onboarding::should_show_for_user($this->adminUserId));
        unset($_GET['onboarding']);
    }
}
