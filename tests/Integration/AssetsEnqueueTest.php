<?php
/**
 * INTEGRATION — the /chatadmin document and the admin pages load their
 * assets through the WordPress enqueue API (WordPress.org review: no
 * hand-written script/style/link tags, no heredoc output).
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\Admin;
use ChatAdmin\Frontend;
use ChatAdmin\Tests\TestCase;

class AssetsEnqueueTest extends TestCase {

    public function tear_down() {
        foreach (['chatadmin-app', 'chatadmin-diagnostics', 'chatadmin-admin-menu'] as $h) {
            wp_deregister_script($h);
        }
        foreach (['chatadmin-page', 'chatadmin-admin-shell'] as $h) {
            wp_deregister_style($h);
        }
        parent::tear_down();
    }

    private function renderFrontend(): string {
        $method = new \ReflectionMethod(Frontend::class, 'render');
        $method->setAccessible(true);
        ob_start();
        $method->invoke(new Frontend());
        return (string) ob_get_clean();
    }

    public function test_frontend_document_prints_enqueued_app_assets(): void {
        update_option('blogname', 'Tom&#039;s </script> Store');
        $html = $this->renderFrontend();

        $manifest = json_decode((string) file_get_contents(CHATADMIN_DIR . 'build/manifest.json'), true);
        $entry    = $manifest['src/main.tsx'];

        $this->assertStringContainsString('<div id="chatadmin-root" class="dark"></div>', $html);
        $this->assertStringContainsString('build/' . $entry['file'], $html, 'App bundle must be printed by wp_print_scripts.');
        foreach ($entry['css'] as $css) {
            $this->assertStringContainsString('build/' . $css, $html, 'App CSS must be printed by wp_print_styles.');
        }
        $this->assertStringContainsString('window.CHATADMIN_BOOT = ', $html);
        $this->assertStringContainsString('font-size:18px', $html, 'Page surface CSS must be attached via wp_add_inline_style.');

        // Boot data comes before the bundle, and a site name can't break out of it.
        $this->assertLessThan(strpos($html, 'build/' . $entry['file']), strpos($html, 'window.CHATADMIN_BOOT'));
        $this->assertStringNotContainsString('</script> Store', $html);
        $this->assertStringContainsString("<title>ChatAdmin — Tom&#039;s &lt;/script&gt; Store</title>", $html);
    }

    public function test_frontend_document_excludes_other_enqueued_assets(): void {
        wp_enqueue_script('some-theme-script', 'https://example.test/theme.js');
        wp_enqueue_style('some-theme-style', 'https://example.test/theme.css');

        $html = $this->renderFrontend();

        $this->assertStringNotContainsString('example.test/theme', $html);
        wp_dequeue_script('some-theme-script');
        wp_dequeue_style('some-theme-style');
    }

    public function test_admin_chat_page_enqueues_app_and_shell_style(): void {
        (new Admin())->enqueue_assets('toplevel_page_' . Admin::MENU_SLUG);

        $this->assertTrue(wp_script_is('chatadmin-app', 'enqueued'));
        $this->assertTrue(wp_style_is('chatadmin-admin-shell', 'enqueued'));
        $this->assertStringContainsString('#chatadmin-shell', implode('', wp_styles()->get_data('chatadmin-admin-shell', 'after') ?: []));
        $this->assertStringContainsString('CHATADMIN_BOOT', implode('', wp_scripts()->get_data('chatadmin-app', 'before') ?: []));
    }

    public function test_admin_diagnostics_page_enqueues_its_script(): void {
        (new Admin())->enqueue_assets('chatadmin_page_' . Admin::MENU_SLUG . '-diagnostics');

        $this->assertTrue(wp_script_is('chatadmin-diagnostics', 'enqueued'));
        $this->assertStringContainsString('chatadminDiagnostics', (string) wp_scripts()->get_data('chatadmin-diagnostics', 'data'));
        $this->assertFileExists(CHATADMIN_DIR . 'includes/js/diagnostics.js');
    }

    public function test_menu_new_tab_script_is_an_inline_enqueued_script(): void {
        (new Admin())->open_fullscreen_in_new_tab();

        $this->assertTrue(wp_script_is('chatadmin-admin-menu', 'enqueued'));
        $this->assertStringContainsString('#adminmenu a[href=', implode('', wp_scripts()->get_data('chatadmin-admin-menu', 'after') ?: []));
    }
}
