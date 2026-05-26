<?php
/**
 * Default WPContentBackend tests — exercise wp_post / wp_page_slug / wp_post_meta.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\WPContentBackend;
use WPChat\Tests\TestCase;

class WPContentBackendTest extends TestCase {

    public function test_handled_kinds_lists_core_wp_kinds(): void {
        $backend = new WPContentBackend();
        $this->assertEqualsCanonicalizing(
            ['wp_post', 'wp_page_slug', 'wp_post_meta', 'wp_term'],
            $backend->handled_kinds()
        );
    }

    public function test_preview_wp_post_returns_diff_without_writing(): void {
        $post_id = $this->factory()->post->create([
            'post_title'  => 'Original title',
            'post_status' => 'publish',
        ]);

        $backend = new WPContentBackend();
        $result  = $backend->preview(['kind' => 'wp_post', 'id' => $post_id], 'title', 'New title');

        $this->assertSame('Original title', $result['matches'][0]['old_value']);
        $this->assertSame('New title', $result['matches'][0]['new_value']);

        // No write should have happened.
        $this->assertSame('Original title', get_post($post_id)->post_title);
    }

    public function test_apply_wp_post_requires_confirmation(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Before']);
        $backend = new WPContentBackend();

        $denied = $backend->apply(['kind' => 'wp_post', 'id' => $post_id], 'title', 'After', 'maybe');
        $this->assertArrayHasKey('error', $denied);
        $this->assertSame('Before', get_post($post_id)->post_title, 'No write should happen without a confirmed phrase.');

        $accepted = $backend->apply(['kind' => 'wp_post', 'id' => $post_id], 'title', 'After', 'yes');
        $this->assertSame(true, $accepted['ok']);
        $this->assertSame('After', get_post($post_id)->post_title);
    }

    public function test_wp_page_slug_resolves_to_post_id(): void {
        $page_id = $this->factory()->post->create([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_name'   => 'apie-mus-test',
            'post_title'  => 'Slug page',
        ]);

        $backend = new WPContentBackend();
        $result  = $backend->preview(['kind' => 'wp_page_slug', 'slug' => 'apie-mus-test'], 'title', 'Renamed');
        $this->assertStringContainsString((string) $page_id, $result['matches'][0]['location']);
    }

    public function test_wp_post_meta_round_trip(): void {
        $post_id = $this->factory()->post->create();
        $backend = new WPContentBackend();

        $target = ['kind' => 'wp_post_meta', 'post_id' => $post_id, 'key' => 'wpchat_test_key'];
        $backend->apply($target, 'value', 'fresh-value', 'taip');

        $this->assertSame('fresh-value', get_post_meta($post_id, 'wpchat_test_key', true));
    }

    public function test_unknown_kind_returns_error(): void {
        $backend = new WPContentBackend();
        $result  = $backend->preview(['kind' => 'wp_unknown_kind'], 'title', 'x');
        $this->assertArrayHasKey('error', $result);
    }
}
