<?php
/**
 * Tools::check_kind_access — site-disabled gate + role gate.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Onboarding;
use WPChat\Tools;
use WPChat\Tests\TestCase;

class ToolsRoleGateTest extends TestCase {

    public function test_kind_required_cap_for_core_kinds(): void {
        $this->assertSame('edit_posts', Tools::kind_required_cap('wp_post'));
        $this->assertSame('edit_posts', Tools::kind_required_cap('wp_page_slug'));
        $this->assertSame('edit_posts', Tools::kind_required_cap('wp_post_meta'));
        $this->assertSame('manage_categories', Tools::kind_required_cap('wp_term'));
    }

    public function test_admin_can_edit_all_core_kinds(): void {
        // Admin is set up in TestCase with the chat caps.
        $this->assertTrue(Tools::user_can_edit_kind('wp_post'));
        $this->assertTrue(Tools::user_can_edit_kind('wp_term'));
    }

    public function test_editor_cannot_edit_wp_term(): void {
        $editor = $this->factory()->user->create(['role' => 'editor']);
        \wp_set_current_user($editor);
        // Editors don't have manage_categories by default.
        $this->assertFalse(Tools::user_can_edit_kind('wp_term'));
    }

    public function test_dispatch_refused_when_site_disabled(): void {
        \update_option(Onboarding::DISABLED_KINDS_OPT, ['wp_post']);

        $result = Tools::preview_content_change([
            'target' => ['kind' => 'wp_post', 'id' => 1],
            'field'  => 'title',
            'value'  => 'New',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('kind_disabled_site', $result['code']);

        // Cleanup so it doesn't bleed into other tests.
        \delete_option(Onboarding::DISABLED_KINDS_OPT);
    }

    public function test_dispatch_refused_when_role_restricted(): void {
        $editor = $this->factory()->user->create(['role' => 'editor']);
        \wp_set_current_user($editor);

        $result = Tools::preview_content_change([
            'target' => ['kind' => 'wp_term', 'term_id' => 1, 'taxonomy' => 'category'],
            'field'  => 'name',
            'value'  => 'New',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('kind_role_restricted', $result['code']);
    }

    public function test_system_prompt_omits_disabled_kinds(): void {
        \update_option(Onboarding::DISABLED_KINDS_OPT, ['wp_term']);

        // Run a /chat request through the mock so we can inspect the
        // exact system prompt that gets shipped to Anthropic.
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringNotContainsString('**wp_term**', $system);
        $this->assertStringContainsString('**wp_post**', $system);

        \delete_option(Onboarding::DISABLED_KINDS_OPT);
    }

    public function test_system_prompt_omits_role_restricted_kinds(): void {
        $editor = $this->factory()->user->create(['role' => 'editor']);
        // Grant the chat-route caps so postChat doesn't 403; editors don't
        // get manage_woocommerce by default.
        $user = \get_user_by('id', $editor);
        $user->add_cap('manage_woocommerce');
        $user->add_cap('edit_shop_orders');
        \wp_set_current_user($editor);

        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // Editors don't have manage_categories → wp_term should be filtered out.
        $this->assertStringNotContainsString('**wp_term**', $system);
    }
}
