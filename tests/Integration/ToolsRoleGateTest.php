<?php
/**
 * Tools::check_kind_access — site-disabled gate + role gate.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Onboarding;
use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

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

    public function test_author_cannot_edit_wp_term(): void {
        // Editors do have manage_categories by default. Authors don't —
        // they can publish their own posts but can't manage taxonomies.
        $author = $this->factory()->user->create(['role' => 'author']);
        \wp_set_current_user($author);
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
        // Use author role — lacks manage_categories.
        $author = $this->factory()->user->create(['role' => 'author']);
        \wp_set_current_user($author);

        $result = Tools::preview_content_change([
            'target' => ['kind' => 'wp_term', 'term_id' => 1, 'taxonomy' => 'category'],
            'field'  => 'name',
            'value'  => 'New',
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('kind_role_restricted', $result['code']);
    }

    public function test_list_content_blocks_refuses_meta_of_post_user_cannot_edit(): void {
        // Admin owns a post carrying private (_-prefixed) meta.
        $post_id = $this->factory()->post->create(['post_author' => $this->adminUserId]);
        \update_post_meta($post_id, '_private_token', 'secret-123');

        // An author cannot edit others' posts, so must not be able to read
        // that post's meta via the list path either.
        $author = $this->factory()->user->create(['role' => 'author']);
        \wp_set_current_user($author);

        $result = Tools::list_content_blocks([
            'kind' => 'wp_post_meta',
            'args' => ['post_id' => $post_id],
        ]);

        $this->assertArrayHasKey('error', $result, 'Listing meta of a non-editable post must be refused.');
        $this->assertSame('kind_role_restricted', $result['code']);
    }

    public function test_list_content_blocks_allows_meta_of_editable_post(): void {
        // Admin (current user) can edit the post, so listing its meta is fine.
        $post_id = $this->factory()->post->create(['post_author' => $this->adminUserId]);
        \update_post_meta($post_id, 'color', 'blue');

        $result = Tools::list_content_blocks([
            'kind' => 'wp_post_meta',
            'args' => ['post_id' => $post_id],
        ]);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame('wp_post_meta', $result['kind']);
    }

    public function test_seo_meta_requires_per_post_edit_cap(): void {
        $post_id = $this->factory()->post->create(['post_author' => $this->adminUserId]);

        // The required cap must be object-scoped (edit_post), not the
        // role-level edit_posts, so it binds to the specific post.
        $this->assertSame('edit_post', Tools::kind_required_cap('seo_meta', ['post_id' => $post_id]));

        // An author cannot edit someone else's post, so cannot set its SEO meta.
        $author = $this->factory()->user->create(['role' => 'author']);
        \wp_set_current_user($author);
        $this->assertFalse(Tools::user_can_edit_kind('seo_meta', ['post_id' => $post_id]));
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
        // Authors lack manage_categories. Grant the chat-route caps so
        // postChat doesn't 403 at the gate.
        $author = $this->factory()->user->create(['role' => 'author']);
        $user = \get_user_by('id', $author);
        $user->add_cap('manage_woocommerce');
        $user->add_cap('edit_shop_orders');
        \wp_set_current_user($author);

        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringNotContainsString('**wp_term**', $system);
    }
}
