<?php
/**
 * Tests for the site-level disabled-kinds option + admin-gated REST route.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Onboarding;
use ChatAdmin\Tests\TestCase;

class OnboardingDisabledKindsTest extends TestCase {

    public function test_default_is_empty_array(): void {
        \delete_option(Onboarding::DISABLED_KINDS_OPT);
        $this->assertSame([], Onboarding::get_site_disabled_kinds());
    }

    public function test_admin_can_persist_disabled_kinds(): void {
        $request = new \WP_REST_Request('POST', '/chatadmin/v1/onboarding/disabled-kinds');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['disabled' => ['wp_post_meta', 'wp_term']]));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $this->assertEqualsCanonicalizing(
            ['wp_post_meta', 'wp_term'],
            Onboarding::get_site_disabled_kinds()
        );
    }

    public function test_invalid_payload_is_sanitised_not_rejected(): void {
        $request = new \WP_REST_Request('POST', '/chatadmin/v1/onboarding/disabled-kinds');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['disabled' => ['wp_post', null, '', 123, 'WP_TERM!!!']]));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $persisted = Onboarding::get_site_disabled_kinds();
        // sanitize_key forces lowercase; non-strings dropped.
        $this->assertContains('wp_post', $persisted);
        $this->assertContains('wp_term', $persisted);
        $this->assertNotContains('', $persisted);
        $this->assertNotContains(null, $persisted);
    }

    public function test_non_admin_user_gets_403(): void {
        $editor = $this->factory()->user->create(['role' => 'editor']);
        \wp_set_current_user($editor);

        $request = new \WP_REST_Request('POST', '/chatadmin/v1/onboarding/disabled-kinds');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['disabled' => ['wp_post']]));
        $response = \rest_get_server()->dispatch($request);

        $this->assertContains($response->get_status(), [401, 403]);
    }

    public function test_status_surfaces_disabled_kinds_and_isAdmin(): void {
        \update_option(Onboarding::DISABLED_KINDS_OPT, ['wp_term']);

        $request  = new \WP_REST_Request('GET', '/chatadmin/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        $this->assertSame(['wp_term'], $data['disabled_kinds']);
        $this->assertTrue($data['isAdmin']);
        // backends array entries should now carry per-kind flags.
        if (!empty($data['backends'])) {
            $first = $data['backends'][0];
            $this->assertArrayHasKey('userCanEdit', $first);
            $this->assertArrayHasKey('siteDisabled', $first);
            $this->assertArrayHasKey('requiredCap', $first);
        }
    }
}
