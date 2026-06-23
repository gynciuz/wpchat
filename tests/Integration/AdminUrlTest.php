<?php
/**
 * get_admin_url tool — verifies it returns a clickable WP admin URL for
 * each supported resource. Critical for the proactive-handoff pattern:
 * if this tool is broken, the LLM can't gracefully delegate to wp-admin.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Tools;
use WPChat\Tests\TestCase;

class AdminUrlTest extends TestCase {

    public function test_orders_list_returns_wc_orders_admin_url(): void {
        $result = Tools::get_admin_url(['resource' => 'orders_list']);
        $this->assertArrayHasKey('url', $result);
        $this->assertStringContainsString('admin.php?page=wc-orders', $result['url']);
    }

    public function test_pages_list_returns_pages_admin_url(): void {
        $result = Tools::get_admin_url(['resource' => 'pages_list']);
        $this->assertStringContainsString('edit.php?post_type=page', $result['url']);
    }

    public function test_users_list_returns_users_admin_url(): void {
        $result = Tools::get_admin_url(['resource' => 'users_list']);
        $this->assertStringContainsString('users.php', $result['url']);
    }

    public function test_post_returns_edit_url_for_real_post(): void {
        $post_id = $this->factory()->post->create();
        $result  = Tools::get_admin_url(['resource' => 'post', 'id' => $post_id]);
        $this->assertStringContainsString("post=$post_id", $result['url']);
        $this->assertStringContainsString('action=edit', $result['url']);
    }

    public function test_comments_returns_moderation_url(): void {
        $result = Tools::get_admin_url(['resource' => 'comments']);
        $this->assertStringContainsString('edit-comments.php', $result['url']);
    }

    public function test_comments_with_id_deep_links_to_one_comment(): void {
        $result = Tools::get_admin_url(['resource' => 'comments', 'id' => 42]);
        $this->assertStringContainsString('comment.php?action=editcomment&c=42', $result['url']);
    }

    public function test_site_health_returns_maintenance_url(): void {
        $result = Tools::get_admin_url(['resource' => 'site_health']);
        $this->assertStringContainsString('site-health.php', $result['url']);
    }

    public function test_missing_id_returns_error(): void {
        $result = Tools::get_admin_url(['resource' => 'order']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_unknown_resource_returns_allowed_list(): void {
        $result = Tools::get_admin_url(['resource' => 'bogus']);
        $this->assertArrayHasKey('error', $result);
        $this->assertContains('orders_list', $result['allowed']);
    }
}
