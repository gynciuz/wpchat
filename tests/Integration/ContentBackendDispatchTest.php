<?php
/**
 * Verifies the wpchat_content_backends filter actually dispatches to
 * site-registered backends, and that ContentRouter merges descriptions
 * from every backend so the LLM's system prompt sees both core and
 * site-specific kinds.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\ContentBackend;
use WPChat\ContentRouter;
use WPChat\Tools;
use WPChat\Tests\TestCase;

class ContentBackendDispatchTest extends TestCase {

    public function test_filter_appends_custom_backend(): void {
        $custom = new FakeContentBackend();
        add_filter('wpchat_content_backends', static function ($backends) use ($custom) {
            $backends[] = $custom;
            return $backends;
        });

        $kinds = ContentRouter::all_kinds();
        $this->assertContains('fake_kind', $kinds);
        $this->assertContains('wp_post', $kinds, 'Default backend must still be present.');

        $resolved = ContentRouter::for_kind('fake_kind');
        $this->assertSame($custom, $resolved);
    }

    public function test_apply_content_change_routes_to_registered_backend(): void {
        $custom = new FakeContentBackend();
        add_filter('wpchat_content_backends', static function ($backends) use ($custom) {
            $backends[] = $custom;
            return $backends;
        });

        Tools::apply_content_change([
            'target'       => ['kind' => 'fake_kind', 'name' => 'thing'],
            'field'        => 'role',
            'value'        => 'updated',
            'confirmation' => 'taip',
        ]);

        $this->assertSame(1, $custom->applyCount, 'Tools::apply_content_change should route to the registered backend.');
    }

    public function test_describe_kinds_appears_in_router_descriptions(): void {
        add_filter('wpchat_content_backends', static function ($backends) {
            $backends[] = new FakeContentBackend();
            return $backends;
        });

        $desc = ContentRouter::all_descriptions();
        $this->assertArrayHasKey('fake_kind', $desc);
        $this->assertSame(['role'], $desc['fake_kind']['fields']);
    }
}

class FakeContentBackend implements ContentBackend {
    public int $applyCount = 0;
    public function handled_kinds(): array { return ['fake_kind']; }
    public function describe_kinds(): array {
        return ['fake_kind' => ['description' => 'A test-only kind.', 'fields' => ['role']]];
    }
    public function list_items(string $kind, array $args = []): array {
        return ['items' => [['name' => 'thing', 'role' => 'old']]];
    }
    public function preview(array $target, string $field, $value): array {
        return ['matches' => [['name' => $target['name'] ?? '', 'old_value' => 'old', 'new_value' => $value]]];
    }
    public function apply(array $target, string $field, $value, string $confirmation): array {
        $this->applyCount++;
        return ['ok' => true];
    }
}
