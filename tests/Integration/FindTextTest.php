<?php
/**
 * Tools::find_text — locate a string across posts/CPTs, meta, terms, options,
 * each hit flagged with whether it's editable from chat. This is the
 * "detect static/theme content" layer.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class FindTextTest extends TestCase {

    public function test_find_text_locates_across_surfaces_with_editable_flags(): void {
        $needle = 'barzdaskucys_unique_zx';

        $post_id = $this->factory()->post->create([
            'post_author'  => $this->adminUserId,
            'post_title'   => 'Meistras',
            'post_content' => "Pozicija: {$needle}",
        ]);
        \update_post_meta($post_id, 'subtitle', "role {$needle}");      // non-protected → editable
        \update_post_meta($post_id, '_theme_field', "role {$needle}");  // protected → not editable

        $this->factory()->term->create([
            'taxonomy' => 'category',
            'name'     => "Label {$needle}",
        ]);

        $res = Tools::find_text(['query' => $needle]);

        $this->assertArrayNotHasKey('error', $res);
        $this->assertGreaterThanOrEqual(3, $res['count']);

        $by_kind = [];
        foreach ($res['hits'] as $h) {
            $by_kind[$h['kind']][] = $h;
        }

        // Post content — editable for admin.
        $this->assertArrayHasKey('wp_post', $by_kind);
        $this->assertTrue($by_kind['wp_post'][0]['editable']);

        // Taxonomy term — editable + flagged shared (one edit fixes all users of it).
        $this->assertArrayHasKey('wp_term', $by_kind);
        $this->assertTrue($by_kind['wp_term'][0]['editable']);
        $this->assertTrue(!empty($by_kind['wp_term'][0]['shared']));

        // Meta — non-protected editable, protected not; no values are returned.
        $this->assertArrayHasKey('wp_post_meta', $by_kind);
        $editable_by_key = [];
        foreach ($by_kind['wp_post_meta'] as $h) {
            $editable_by_key[$h['target']['key']] = $h['editable'];
            $this->assertArrayNotHasKey('value', $h, 'find_text must never return meta values.');
        }
        $this->assertTrue($editable_by_key['subtitle'] ?? false, 'non-protected meta is editable');
        $this->assertFalse($editable_by_key['_theme_field'] ?? true, 'protected meta is not editable');
    }

    public function test_find_text_requires_min_length(): void {
        $res = Tools::find_text(['query' => 'a']);
        $this->assertArrayHasKey('error', $res);
    }

    public function test_find_text_hides_meta_of_posts_user_cannot_edit(): void {
        $needle = 'secret_meta_needle_qq';
        $post_id = $this->factory()->post->create(['post_author' => $this->adminUserId]);
        \update_post_meta($post_id, 'note', "x {$needle}");

        // An author can't edit others' posts, so must not see that post's meta.
        $author = $this->factory()->user->create(['role' => 'author']);
        \wp_set_current_user($author);

        $res  = Tools::find_text(['query' => $needle]);
        $keys = array_column(array_column($res['hits'], 'target'), 'key');
        $this->assertNotContains('note', $keys, 'Meta of a non-editable post must not be disclosed.');
    }
}
