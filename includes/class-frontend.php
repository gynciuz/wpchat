<?php
/**
 * Public-facing /chatadmin route — full-screen chat (no wp-admin chrome).
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend {

    /** Canonical public path for the full-screen chat. */
    const URL_PATH = '/chatadmin';

    /**
     * Every path (without slashes) that resolves to the full-screen chat.
     * `chatadmin` is canonical (matches the brand + REST namespace); `wpchat`
     * and `chat-admin` are kept as aliases so older bookmarks / links (and
     * earlier plugin versions) keep working.
     */
    const PATHS = ['chatadmin', 'wpchat', 'chat-admin'];

    public function __construct() {
        add_action('template_redirect', [$this, 'maybe_render']);
    }

    public function maybe_render(): void {
        $path = trim((string) wp_parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '')), PHP_URL_PATH), '/');
        if (!in_array($path, self::PATHS, true)) {
            return;
        }

        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url(self::URL_PATH)));
            exit;
        }

        if (!current_user_can('edit_posts')) {
            status_header(403);
            wp_die(
                esc_html__('Reikalingos redaktoriaus arba administratoriaus teisės. (Editor or administrator role required.)', 'chatadmin'),
                esc_html__('ChatAdmin — Access denied', 'chatadmin'),
                ['response' => 403]
            );
        }

        // `/chatadmin` is a virtual route with no matching WP post/page, so the main
        // query flagged it as a 404 and queued a 404 status header. Clear that and
        // send a real 200 before streaming the SPA — otherwise the page renders but
        // caches/CDNs/search engines see it as "Not Found" (the reported bug).
        global $wp_query;
        if ($wp_query instanceof \WP_Query) {
            $wp_query->is_404 = false;
        }
        status_header(200);
        nocache_headers();

        $this->render();
        exit;
    }

    /**
     * Register the built React app with WordPress: one style handle per CSS
     * file in the Vite manifest plus the `chatadmin-app` script. Shared by
     * this route and the wp-admin chat page.
     *
     * @return array{styles: string[], script: string}|null Null when the build is missing.
     */
    public static function register_app_assets(): ?array {
        $manifest_path = CHATADMIN_DIR . 'build/manifest.json';
        if (!file_exists($manifest_path)) {
            return null;
        }
        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        $entry    = is_array($manifest) ? ($manifest['src/main.tsx'] ?? null) : null;
        if (!$entry || empty($entry['file'])) {
            return null;
        }

        $build_url = CHATADMIN_URL . 'build/';
        $styles    = [];
        foreach ($entry['css'] ?? [] as $css) {
            $handle = 'chatadmin-app-' . md5($css);
            wp_register_style($handle, $build_url . $css, [], CHATADMIN_VERSION);
            $styles[] = $handle;
        }
        // The bundle is a classic script (no import/export), so a plain
        // registration is all it needs.
        wp_register_script('chatadmin-app', $build_url . $entry['file'], [], CHATADMIN_VERSION, true);

        return ['styles' => $styles, 'script' => 'chatadmin-app'];
    }

    /**
     * Enqueue the app for the full-screen route and serve the bare template.
     * The theme's template and wp_head()/wp_footer() are skipped on purpose:
     * the app is a full-screen dark surface that theme and third-party assets
     * must not touch. The template prints only this plugin's handles.
     */
    private function render(): void {
        $assets = self::register_app_assets();
        if (!$assets) {
            wp_die(esc_html__('ChatAdmin build assets are missing. Run pnpm build in the plugin\'s app/ directory.', 'chatadmin'), 'ChatAdmin', ['response' => 500]);
        }

        // Some WP installs store `blogname` already HTML-entity-encoded (e.g. a
        // name with an apostrophe stored as "Tom&#039;s Store"). esc_html'ing
        // that again produces "Tom&amp;#039;s Store", which the browser only
        // half-decodes, leaving "&#039;" visible. Decode entities first, then
        // escape once (in the template).
        $site_name = html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $boot = [
            'mode'      => Onboarding::should_show_for_user(get_current_user_id()) ? 'onboarding' : 'chat',
            'version'   => CHATADMIN_VERSION,
            'restUrl'   => rest_url('chatadmin/v1/'),
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
        // break out of the inline script.
        wp_add_inline_script(
            $assets['script'],
            'window.CHATADMIN_BOOT = ' . wp_json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );

        // Page surface: match shadcn's dark --background token so there's no
        // light gutter, and bump the rem reference 16px → 18px so every
        // Tailwind text-* utility scales up for phone readability.
        wp_register_style('chatadmin-page', false, $assets['styles'], CHATADMIN_VERSION);
        wp_add_inline_style(
            'chatadmin-page',
            'html,body{margin:0;padding:0;height:100%;background:oklch(0.145 0 0);color:oklch(0.985 0 0);}'
            . 'html{font-size:18px;}'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;}'
            . '#chatadmin-root{min-height:100vh;}'
        );

        $chatadmin_title         = sprintf('%s — %s', __('ChatAdmin', 'chatadmin'), $site_name);
        $chatadmin_style_handles = array_merge($assets['styles'], ['chatadmin-page']);
        $chatadmin_script_handle = $assets['script'];
        include CHATADMIN_DIR . 'includes/templates/app.php';
    }
}
