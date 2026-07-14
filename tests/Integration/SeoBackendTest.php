<?php
/**
 * INTEGRATION — the SEO fixes flow through the content-backend pattern
 * (preview_content_change / apply_content_change) on the seo_setting and
 * seo_meta kinds, and the robots.txt / llms.txt infrastructure responds to
 * the stored options.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Tools;
use ChatAdmin\Seo;
use ChatAdmin\ContentRouter;
use ChatAdmin\Tests\TestCase;

class SeoBackendTest extends TestCase {

    public function test_seo_kinds_are_registered(): void {
        $this->assertNotNull(ContentRouter::for_kind('seo_setting'));
        $this->assertNotNull(ContentRouter::for_kind('seo_meta'));
        $descriptions = ContentRouter::all_descriptions();
        $this->assertArrayHasKey('seo_setting', $descriptions);
        $this->assertArrayHasKey('seo_meta', $descriptions);
    }

    public function test_toggle_search_engine_visibility(): void {
        update_option('blog_public', 0);

        $preview = Tools::preview_content_change([
            'target' => ['kind' => 'seo_setting'],
            'field'  => 'search_engine_visibility',
            'value'  => true,
        ]);
        $this->assertArrayHasKey('changes', $preview);
        $this->assertSame('discouraged', $preview['changes'][0]['old']);
        $this->assertSame('allowed', $preview['changes'][0]['new']);

        $apply = Tools::apply_content_change([
            'target'       => ['kind' => 'seo_setting'],
            'field'        => 'search_engine_visibility',
            'value'        => true,
            'confirmation' => 'yes',
        ]);
        $this->assertTrue($apply['ok'] ?? false);
        $this->assertSame(1, (int) get_option('blog_public'));
    }

    public function test_apply_requires_confirmation(): void {
        $apply = Tools::apply_content_change([
            'target'       => ['kind' => 'seo_setting'],
            'field'        => 'site_title',
            'value'        => 'Should Not Apply',
            'confirmation' => 'maybe later',
        ]);
        $this->assertArrayHasKey('error', $apply);
    }

    public function test_ai_crawlers_toggle_opens_robots_txt(): void {
        $this->assertFalse(Seo::ai_crawlers_enabled());
        // robots.txt has no AI allow rules yet.
        $before = apply_filters('robots_txt', "User-agent: *\nDisallow:\n", true);
        $this->assertStringNotContainsString('GPTBot', $before);

        Tools::apply_content_change([
            'target'       => ['kind' => 'seo_setting'],
            'field'        => 'ai_crawlers',
            'value'        => true,
            'confirmation' => 'taip',
        ]);
        $this->assertTrue(Seo::ai_crawlers_enabled());

        $after = apply_filters('robots_txt', "User-agent: *\nDisallow:\n", true);
        foreach (['GPTBot', 'ClaudeBot', 'PerplexityBot'] as $bot) {
            $this->assertStringContainsString($bot, $after);
        }
    }

    public function test_llms_txt_generate_and_publish(): void {
        $this->assertSame('', Seo::llms_txt());

        $apply = Tools::apply_content_change([
            'target'       => ['kind' => 'seo_setting'],
            'field'        => 'llms_txt',
            'value'        => 'generate',
            'confirmation' => 'yes',
        ]);
        $this->assertTrue($apply['ok'] ?? false);
        $published = Seo::llms_txt();
        $this->assertNotSame('', $published);
        $this->assertStringContainsString('#', $published); // markdown heading
    }

    public function test_set_site_title_and_tagline(): void {
        // Avoid an apostrophe — WordPress entity-encodes blogname on save
        // (normal sanitize_option behavior the plugin handles for display).
        Tools::apply_content_change([
            'target' => ['kind' => 'seo_setting'], 'field' => 'site_title',
            'value' => 'Gentleman Empire Barbershop', 'confirmation' => 'yes',
        ]);
        Tools::apply_content_change([
            'target' => ['kind' => 'seo_setting'], 'field' => 'tagline',
            'value' => 'Premium barbershop', 'confirmation' => 'yes',
        ]);
        $this->assertSame('Gentleman Empire Barbershop', get_option('blogname'));
        $this->assertSame('Premium barbershop', get_option('blogdescription'));
    }

    public function test_permalink_structure_fix(): void {
        update_option('permalink_structure', '');
        $apply = Tools::apply_content_change([
            'target'       => ['kind' => 'seo_setting'],
            'field'        => 'permalink_structure',
            'value'        => '/%postname%/',
            'confirmation' => 'yes',
        ]);
        $this->assertTrue($apply['ok'] ?? false);
        $this->assertSame('/%postname%/', get_option('permalink_structure'));
    }

    public function test_seo_meta_preview_and_no_plugin_handoff(): void {
        $post_id = $this->factory()->post->create(['post_title' => 'Test post']);

        // Preview works regardless of which plugin is active.
        $preview = Tools::preview_content_change([
            'target' => ['kind' => 'seo_meta', 'post_id' => $post_id],
            'field'  => 'seo_title',
            'value'  => 'Best barbershop in town | Gentleman\'s Empire',
        ]);
        $this->assertArrayHasKey('changes', $preview);
        $this->assertSame('Best barbershop in town | Gentleman\'s Empire', $preview['changes'][0]['new']);

        // No SEO plugin in the test scaffold → apply returns a graceful error.
        $apply = Tools::apply_content_change([
            'target'       => ['kind' => 'seo_meta', 'post_id' => $post_id],
            'field'        => 'seo_title',
            'value'        => 'X',
            'confirmation' => 'yes',
        ]);
        $this->assertArrayHasKey('error', $apply);
    }
}
