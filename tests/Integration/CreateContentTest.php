<?php
/**
 * INTEGRATION — create_content / publish_content / list_taxonomy_terms:
 * draft creation, taxonomy create-if-missing, featured + inline images,
 * SEO note, and the confirmed publish step.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class CreateContentTest extends TestCase {

    public function test_tools_defined_and_mapped(): void {
        $names = array_column(Tools::definitions(), 'name');
        foreach (['create_content', 'publish_content', 'list_taxonomy_terms'] as $t) {
            $this->assertContains($t, $names);
            $this->assertTrue(is_callable(Tools::implementations()[$t] ?? null), "{$t} not callable");
        }
    }

    public function test_create_draft_post_with_taxonomy(): void {
        $res = Tools::create_content([
            'title'      => 'Hello world from ChatAdmin',
            'content'    => "First paragraph.\n\nSecond paragraph.",
            'categories' => ['News'],
            'tags'       => ['welcome', 'intro'],
        ]);

        $this->assertTrue($res['ok'] ?? false, json_encode($res));
        $post_id = $res['post_id'];
        $this->assertSame('draft', get_post_status($post_id));
        $this->assertSame('Hello world from ChatAdmin', get_the_title($post_id));
        $this->assertStringContainsString('wp:paragraph', get_post_field('post_content', $post_id));

        // Category created + assigned; tags created + assigned.
        $cats = wp_get_post_categories($post_id, ['fields' => 'names']);
        $this->assertContains('News', $cats);
        $tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
        $this->assertContains('welcome', $tags);
        $this->assertContains('intro', $tags);
        $this->assertContains('category:News', $res['applied']['created_terms']);
    }

    public function test_featured_and_inline_images(): void {
        $featured = self::make_attachment();
        $inline   = self::make_attachment();

        $res = Tools::create_content([
            'title'          => 'Post with images',
            'content'        => 'Body.',
            'featured_image' => $featured,
            'image_ids'      => [$inline],
        ]);
        $post_id = $res['post_id'];

        $this->assertSame($featured, (int) get_post_thumbnail_id($post_id));
        $content = get_post_field('post_content', $post_id);
        $this->assertStringContainsString('wp:image', $content);
        $this->assertStringContainsString('wp-image-' . $inline, $content);
    }

    public function test_page_has_no_taxonomy(): void {
        $res = Tools::create_content([
            'post_type'  => 'page',
            'title'      => 'About us',
            'content'    => 'Who we are.',
            'categories' => ['Ignored'],
        ]);
        $this->assertSame('page', get_post_type($res['post_id']));
        $this->assertSame([], $res['applied']['categories']);
    }

    public function test_seo_note_when_no_plugin(): void {
        $res = Tools::create_content([
            'title'     => 'SEO test',
            'content'   => 'x',
            'seo_title' => 'Custom SEO title',
        ]);
        // No SEO plugin in the scaffold → a note, not a hard failure.
        $this->assertNotNull($res['seo_note']);
        $this->assertTrue($res['ok']);
    }

    public function test_publish_requires_confirmation(): void {
        $res = Tools::create_content(['title' => 'To publish', 'content' => 'x']);
        $post_id = $res['post_id'];

        $bad = Tools::publish_content(['post_id' => $post_id, 'confirmation' => 'not yet']);
        $this->assertArrayHasKey('error', $bad);
        $this->assertSame('draft', get_post_status($post_id));

        $ok = Tools::publish_content(['post_id' => $post_id, 'confirmation' => 'taip']);
        $this->assertTrue($ok['ok'] ?? false);
        $this->assertSame('publish', get_post_status($post_id));
    }

    public function test_list_taxonomy_terms_shape(): void {
        wp_create_category('Reviews');
        $out = Tools::list_taxonomy_terms([]);
        $this->assertArrayHasKey('taxonomies', $out);
        $this->assertArrayHasKey('category', $out['taxonomies']);
        $this->assertArrayHasKey('is_empty', $out['taxonomies']['category']);
        $names = array_column($out['taxonomies']['category']['terms'], 'name');
        $this->assertContains('Reviews', $names);
    }

    /** A real 1×1 PNG attachment with generated metadata, so set_post_thumbnail() accepts it. */
    private static function make_attachment(): int {
        $upload = wp_upload_dir();
        $file   = $upload['path'] . '/chatadmin-fixture-' . uniqid() . '.png';
        $png    = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($file, $png);

        $id = (int) wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title'     => 'fixture',
            'post_status'    => 'inherit',
        ], $file);

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
        return $id;
    }
}
