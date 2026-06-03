<?php
/**
 * Provider step REST routes — set + persist + waitlist capture.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Onboarding;
use WPChat\Tests\TestCase;

class OnboardingProviderTest extends TestCase {

    public function test_default_provider_is_byo(): void {
        \delete_option(Onboarding::PROVIDER_OPT);
        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $data     = $response->get_data();
        $this->assertSame('byo', $data['provider']['current']);
        $this->assertTrue($data['provider']['cloudWaitlistOpen']);
        $this->assertFalse($data['provider']['cloudAvailable']);
    }

    public function test_set_provider_to_cloud_waitlist_persists(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'cloud-waitlist']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('cloud-waitlist', \get_option(Onboarding::PROVIDER_OPT));
    }

    public function test_set_provider_unknown_returns_400(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'openai']));
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_cloud_waitlist_with_email_captures_signup(): void {
        \delete_option(Onboarding::WAITLIST_OPT);
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'provider' => 'cloud-waitlist',
            'email'    => 'vlad@example.com',
        ]));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $waitlist = (array) \get_option(Onboarding::WAITLIST_OPT, []);
        $this->assertNotEmpty($waitlist);
        $this->assertSame('vlad@example.com', $waitlist[0]['email']);
        $this->assertArrayHasKey('user_id', $waitlist[0]);
        $this->assertArrayHasKey('at', $waitlist[0]);
    }

    public function test_byo_does_not_touch_waitlist(): void {
        \delete_option(Onboarding::WAITLIST_OPT);
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'byo', 'email' => 'should@ignore.me']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $waitlist = (array) \get_option(Onboarding::WAITLIST_OPT, []);
        $this->assertEmpty($waitlist);
    }
}
