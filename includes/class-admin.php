<?php
/**
 * ChatAdmin admin pages.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    const CAPABILITY = 'edit_shop_orders';
    const MENU_SLUG  = 'chat-admin';

    /**
     * Single source of truth for the capability that governs ChatAdmin
     * administration — reaching the Settings page, saving the API key/model,
     * and the onboarding admin endpoints.
     *
     * `manage_options` (site administrators) by design: the API key is a
     * site-wide secret with real billing attached, so changing it is reserved
     * for admins — the deliberate v0.7.3 security boundary, locked in by
     * ProviderConfigTest::test_non_admin_cannot_set_api_key. Centralised here
     * so all four call sites agree; a site that wants to hand settings to shop
     * managers can lower this one line to `manage_woocommerce`.
     */
    const ADMIN_CAPABILITY = 'manage_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [$this, 'open_fullscreen_in_new_tab']);
    }

    /**
     * Make the "Open full screen ↗" admin-menu link open the /chatadmin app in
     * a new browser tab. WordPress renders a URL-slug submenu item as a plain
     * same-tab <a> and gives no menu-API way to set target/rel, so we tag that
     * one specific link client-side. Runs on every admin screen because the
     * menu lives in the sidebar everywhere. The href is matched exactly against
     * the same home_url() the menu item was built from.
     */
    public function open_fullscreen_in_new_tab(): void {
        $selector = '#adminmenu a[href="' . esc_url(home_url(Frontend::URL_PATH)) . '"]';
        wp_register_script('chatadmin-admin-menu', false, [], CHATADMIN_VERSION, true);
        wp_enqueue_script('chatadmin-admin-menu');
        // wp_json_encode safely embeds the selector (and any quotes) into JS.
        wp_add_inline_script(
            'chatadmin-admin-menu',
            '(function(){var l=document.querySelector(' . wp_json_encode($selector) . ');if(l){l.target="_blank";l.rel="noopener";}})();'
        );
    }

    public function register_menu(): void {
        $capability = current_user_can('manage_woocommerce')
            ? 'manage_woocommerce'
            : self::CAPABILITY;

        add_menu_page(
            __('ChatAdmin', 'chatadmin'),
            __('ChatAdmin', 'chatadmin'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page'],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Chat', 'chatadmin'),
            __('Chat', 'chatadmin'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'chatadmin'),
            __('Settings', 'chatadmin'),
            self::ADMIN_CAPABILITY,
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Diagnostics', 'chatadmin'),
            __('Diagnostics', 'chatadmin'),
            $capability,
            self::MENU_SLUG . '-diagnostics',
            [$this, 'render_diagnostics_page']
        );

        // Quick link to the dedicated full-screen chat at /chatadmin (no wp-admin
        // chrome). A URL as the menu slug renders as a plain link.
        global $submenu;
        if (isset($submenu[self::MENU_SLUG])) {
            $submenu[self::MENU_SLUG][] = [
                __('Open full screen ↗', 'chatadmin'),
                $capability,
                home_url(Frontend::URL_PATH),
            ];
        }
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        if ($hook === 'toplevel_page_' . self::MENU_SLUG) {
            $this->enqueue_shell_style();
        }
        if (str_ends_with($hook, '_page_' . self::MENU_SLUG . '-diagnostics')) {
            $this->enqueue_diagnostics_script();
        }

        // Build not present (running from source without `pnpm build`):
        // render_chat_page shows a notice instead.
        $assets = Frontend::register_app_assets();
        if (!$assets) {
            return;
        }
        foreach ($assets['styles'] as $handle) {
            wp_enqueue_style($handle);
        }
        wp_enqueue_script($assets['script']);

        $user = wp_get_current_user();
        wp_add_inline_script(
            $assets['script'],
            'window.CHATADMIN_BOOT = ' . wp_json_encode([
                // First-run admins see the onboarding wizard in the tab too.
                'mode'      => Onboarding::should_show_for_user(get_current_user_id()) ? 'onboarding' : 'chat',
                'version'   => CHATADMIN_VERSION,
                'restUrl'   => rest_url('chatadmin/v1/'),
                'nonce'     => wp_create_nonce('wp_rest'),
                'userId'    => get_current_user_id(),
                'userName'  => $user ? $user->display_name : '',
                'firstName' => $user ? $user->first_name : '',
                'locale'    => substr(get_user_locale(), 0, 2),
                'siteName'  => html_entity_decode((string) get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'siteUrl'   => home_url(),
            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            'before'
        );
    }

    /**
     * The chat is a dark, always-dark UI whose container is transparent — it
     * expects a dark surface. wp-admin's light chrome + form/heading styles
     * (`input[type=text]`, `.wrap h2`, …) outrank the app's utility classes,
     * which breaks the theme. So the chat page renders on its own dark panel
     * (NOT the `.wrap` class) and neutralizes wp-admin's form-control bleed
     * inside the root. (The dedicated /chatadmin page renders full-screen.)
     */
    private function enqueue_shell_style(): void {
        wp_register_style('chatadmin-admin-shell', false, [], CHATADMIN_VERSION);
        wp_enqueue_style('chatadmin-admin-shell');
        wp_add_inline_style(
            'chatadmin-admin-shell',
            '#chatadmin-shell{background:oklch(0.145 0 0);border-radius:12px;margin:10px 20px 0 0;min-height:calc(100vh - 60px);overflow:hidden;}'
            // Strip wp-admin's form-control styling so the app's own (borderless,
            // transparent, theme-coloured) styles show through.
            . '#chatadmin-root input:not([type=checkbox]):not([type=radio]),#chatadmin-root textarea,#chatadmin-root select{border:0 !important;background:transparent !important;box-shadow:none !important;outline:0 !important;min-height:0 !important;color:inherit !important;}'
            . '#chatadmin-root h1,#chatadmin-root h2,#chatadmin-root h3{color:inherit;}'
        );
    }

    /** Copy-to-clipboard + "send to developer" behaviour for the Diagnostics page. */
    private function enqueue_diagnostics_script(): void {
        wp_enqueue_script(
            'chatadmin-diagnostics',
            CHATADMIN_URL . 'includes/js/diagnostics.js',
            [],
            CHATADMIN_VERSION,
            true
        );
        wp_localize_script('chatadmin-diagnostics', 'chatadminDiagnostics', [
            'rest'     => rest_url('chatadmin/v1/support'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'sentOk'   => __('Sent — thank you!', 'chatadmin'),
            'sentFail' => __('Could not send. Try “Copy diagnostics” and email it.', 'chatadmin'),
            'copied'   => __('Copied.', 'chatadmin'),
        ]);
    }

    public function render_chat_page(): void {
        ?>
        <div id="chatadmin-shell"><div id="chatadmin-root" class="dark">
        <?php
        if (!file_exists(CHATADMIN_DIR . 'build/manifest.json')) {
            echo '<div style="padding:2rem;margin:1rem;border:1px dashed #555;color:#ddd;border-radius:10px;">';
            echo '<h2 style="margin-top:0;color:#fff;">' . esc_html__('ChatAdmin — build assets missing', 'chatadmin') . '</h2>';
            echo '<p>' . esc_html__('Run', 'chatadmin') . ' <code>pnpm install &amp;&amp; pnpm build</code> ' . esc_html__('inside the plugin\'s', 'chatadmin') . ' <code>app/</code> ' . esc_html__('directory to produce the chat UI bundle.', 'chatadmin') . '</p>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    public function render_settings_page(): void {
        if (!current_user_can(self::ADMIN_CAPABILITY)) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ChatAdmin Settings', 'chatadmin') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('chatadmin_settings_group');
        do_settings_sections('chatadmin-settings');
        submit_button();
        echo '</form>';

        $onboarding_url = esc_url(home_url(Frontend::URL_PATH . '?onboarding=1'));
        echo '<p style="margin-top:2rem;border-top:1px solid #c3c4c7;padding-top:1rem;">';
        echo '<a href="' . $onboarding_url . '">' . esc_html__('Re-run onboarding wizard', 'chatadmin') . '</a> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $onboarding_url is esc_url()'d above.
        echo '<span class="description">' . esc_html__('— walk through the capability check + settings again.', 'chatadmin') . '</span>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Diagnostics — recent errors + a "copy" blob + a "report to developer"
     * button. Makes help work even when no collector endpoint is configured.
     */
    public function render_diagnostics_page(): void {
        $recent = Telemetry::recent(50);
        $diag   = [
            'plugin'   => CHATADMIN_VERSION,
            'php'      => PHP_VERSION,
            'wp'       => get_bloginfo('version'),
            'provider' => Settings::get_provider(),
            'errors'   => $recent,
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ChatAdmin Diagnostics', 'chatadmin') . '</h1>';
        echo '<p class="description">' . esc_html__('Recent errors ChatAdmin recorded on this site. Use “Copy diagnostics” to paste into a support request, or send it straight to the developer.', 'chatadmin') . '</p>';

        // Recent errors table.
        echo '<table class="widefat striped" style="margin-top:1rem;max-width:900px;">';
        echo '<thead><tr><th>' . esc_html__('When (UTC)', 'chatadmin') . '</th><th>' . esc_html__('Event', 'chatadmin') . '</th><th>' . esc_html__('Tool', 'chatadmin') . '</th><th>' . esc_html__('Message', 'chatadmin') . '</th></tr></thead><tbody>';
        if (empty($recent)) {
            echo '<tr><td colspan="4">' . esc_html__('No errors recorded — nice.', 'chatadmin') . '</td></tr>';
        } else {
            foreach (array_reverse($recent) as $e) {
                printf(
                    '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                    esc_html((string) ($e['at'] ?? '')),
                    esc_html((string) ($e['event'] ?? '')),
                    esc_html((string) ($e['tool'] ?? '')),
                    esc_html((string) ($e['message'] ?? ''))
                );
            }
        }
        echo '</tbody></table>';

        $diag_json = wp_json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo '<h2 style="margin-top:2rem;">' . esc_html__('Send a report to the developer', 'chatadmin') . '</h2>';
        echo '<p><textarea id="chatadmin-diag-note" rows="3" style="width:100%;max-width:600px;" placeholder="' . esc_attr__('Optional: what were you doing when it broke?', 'chatadmin') . '"></textarea></p>';
        echo '<p>';
        echo '<button type="button" class="button" id="chatadmin-diag-copy">' . esc_html__('Copy diagnostics', 'chatadmin') . '</button> ';
        echo '<button type="button" class="button button-primary" id="chatadmin-diag-send">' . esc_html__('Send to developer', 'chatadmin') . '</button> ';
        echo '<span id="chatadmin-diag-status" style="margin-left:.5rem;"></span>';
        echo '</p>';
        echo '<details style="margin-top:1rem;"><summary>' . esc_html__('Show raw diagnostics', 'chatadmin') . '</summary><pre id="chatadmin-diag-json" style="background:#f6f7f7;border:1px solid #dcdcde;padding:1rem;overflow:auto;max-width:900px;">' . esc_html($diag_json) . '</pre></details>';

        // Copy/send behaviour: includes/js/diagnostics.js (enqueue_diagnostics_script).


        echo '</div>';
    }
}
