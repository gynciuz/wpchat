<?php
/**
 * Public-facing /wpchat route — full-screen chat (no wp-admin chrome).
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    const URL_PATH = '/wpchat';

    public function __construct() {
        add_action('template_redirect', [$this, 'maybe_render']);
    }

    public function maybe_render(): void {
        $path = trim((string) wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH), '/');
        if ($path !== 'wpchat') {
            return;
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url(self::URL_PATH)));
            exit;
        }

        if (!current_user_can('edit_posts')) {
            status_header(403);
            wp_die(
                esc_html__('Reikalingos redaktoriaus arba administratoriaus teisės. (Editor or administrator role required.)', 'wpchat'),
                esc_html__('WPChat — Access denied', 'wpchat'),
                ['response' => 403]
            );
        }

        $this->render();
        exit;
    }

    private function render(): void {
        $manifest_path = WPCHAT_DIR . 'build/manifest.json';
        if (!file_exists($manifest_path)) {
            wp_die('WPChat build assets are missing. Run pnpm build in the plugin\'s app/ directory.', 'WPChat', ['response' => 500]);
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry    = $manifest['src/main.tsx'] ?? null;
        $build_url = WPCHAT_URL . 'build/';

        $css_tags = '';
        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $css) {
                $css_tags .= sprintf('<link rel="stylesheet" href="%s">', esc_url($build_url . $css));
            }
        }
        $js_src = esc_url($build_url . ($entry['file'] ?? ''));

        // Some WP installs store `blogname` already HTML-entity-encoded (e.g.
        // "Gentleman&#039;s Empire"). esc_html'ing that again produces
        // "Gentleman&amp;#039;s Empire" which the browser only half-decodes,
        // leaving "&#039;" visible. Decode entities first, then escape once.
        $site_name = html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $mode = Onboarding::should_show_for_user(get_current_user_id()) ? 'onboarding' : 'chat';

        $boot = [
            'mode'      => $mode,
            'restUrl'   => rest_url('wpchat/v1/'),
            'nonce'     => wp_create_nonce('wp_rest'),
            'userId'    => get_current_user_id(),
            'userName'  => html_entity_decode((string) wp_get_current_user()->display_name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'firstName' => (string) wp_get_current_user()->first_name,
            'locale'    => substr(get_user_locale(), 0, 2),
            'siteName'  => $site_name,
            'siteUrl'   => untrailingslashit(home_url()),
            'logoutUrl' => wp_logout_url(home_url(self::URL_PATH)),
        ];
        // JSON_HEX_* so a "</script>" (or quotes) inside site/user names can't
        // break out of the inline <script> below.
        $boot_json = wp_json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $title = sprintf('%s — %s', esc_html__('WPChat', 'wpchat'), esc_html($site_name));

        echo <<<HTML
<!DOCTYPE html>
<html lang="lt" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="color-scheme" content="dark">
<title>{$title}</title>
{$css_tags}
<style>
  /* Match shadcn's dark --background token so there's no light gutter
     around the chat root on wider viewports. */
  html, body { margin: 0; padding: 0; height: 100%; background: oklch(0.145 0 0); color: oklch(0.985 0 0); }
  /* Bump rem reference 16px → 18px so every Tailwind text-* utility scales
     proportionally — more readable on phones between salon clients. */
  html { font-size: 18px; }
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
  #wpchat-root { min-height: 100vh; }
</style>
</head>
<body class="dark">
<div id="wpchat-root" class="dark"></div>
<script>window.WPCHAT_BOOT = {$boot_json};</script>
<script type="module" src="{$js_src}"></script>
</body>
</html>
HTML;
    }
}
