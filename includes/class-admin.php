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

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void {
        $capability = current_user_can('manage_woocommerce')
            ? 'manage_woocommerce'
            : self::CAPABILITY;

        add_menu_page(
            __('ChatAdmin', 'chat-admin'),
            __('ChatAdmin', 'chat-admin'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page'],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Chat', 'chat-admin'),
            __('Chat', 'chat-admin'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'chat-admin'),
            __('Settings', 'chat-admin'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Diagnostics', 'chat-admin'),
            __('Diagnostics', 'chat-admin'),
            $capability,
            self::MENU_SLUG . '-diagnostics',
            [$this, 'render_diagnostics_page']
        );

        // Quick link to the dedicated full-screen chat at /wpchat (no wp-admin
        // chrome). A URL as the menu slug renders as a plain link.
        global $submenu;
        if (isset($submenu[self::MENU_SLUG])) {
            $submenu[self::MENU_SLUG][] = [
                __('Open full screen ↗', 'chat-admin'),
                $capability,
                home_url('/chat-admin'),
            ];
        }
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        $build_dir = CHATADMIN_DIR . 'build/';
        $build_url = CHATADMIN_URL . 'build/';

        $manifest_path = $build_dir . 'manifest.json';
        if (!file_exists($manifest_path)) {
            // Build not present (running from source without `pnpm build`).
            return;
        }

        $manifest = json_decode(file_get_contents($manifest_path), true);
        $entry    = $manifest['src/main.tsx'] ?? null;
        if (!$entry) {
            return;
        }

        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $css) {
                wp_enqueue_style('chatadmin-app-' . md5($css), $build_url . $css, [], CHATADMIN_VERSION);
            }
        }

        wp_enqueue_script(
            'chatadmin-app',
            $build_url . $entry['file'],
            [],
            CHATADMIN_VERSION,
            true
        );

        $user = wp_get_current_user();
        wp_add_inline_script(
            'chatadmin-app',
            'window.CHATADMIN_BOOT = ' . wp_json_encode([
                // First-run admins see the onboarding wizard in the tab too.
                'mode'      => Onboarding::should_show_for_user(get_current_user_id()) ? 'onboarding' : 'chat',
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

    public function render_chat_page(): void {
        // The chat is a dark, always-dark UI whose container is transparent — it
        // expects a dark surface. wp-admin's light chrome + form/heading styles
        // (`input[type=text]`, `.wrap h2`, …) outrank the app's utility classes,
        // which breaks the theme. So we render on our own dark panel (NOT the
        // `.wrap` class) and neutralize wp-admin's form-control bleed inside the
        // root. (The dedicated /wpchat page renders full-screen with no chrome.)
        ?>
        <style>
            #chatadmin-shell {
                background: oklch(0.145 0 0);
                border-radius: 12px;
                margin: 10px 20px 0 0;
                min-height: calc(100vh - 60px);
                overflow: hidden;
            }
            /* Strip wp-admin's form-control styling so the app's own (borderless,
               transparent, theme-coloured) styles show through. */
            #chatadmin-root input:not([type=checkbox]):not([type=radio]),
            #chatadmin-root textarea,
            #chatadmin-root select {
                border: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
                outline: 0 !important;
                min-height: 0 !important;
                color: inherit !important;
            }
            #chatadmin-root h1, #chatadmin-root h2, #chatadmin-root h3 { color: inherit; }
        </style>
        <div id="chatadmin-shell"><div id="chatadmin-root" class="dark">
        <?php
        if (!file_exists(CHATADMIN_DIR . 'build/manifest.json')) {
            echo '<div style="padding:2rem;margin:1rem;border:1px dashed #555;color:#ddd;border-radius:10px;">';
            echo '<h2 style="margin-top:0;color:#fff;">' . esc_html__('ChatAdmin — build assets missing', 'chat-admin') . '</h2>';
            echo '<p>' . esc_html__('Run', 'chat-admin') . ' <code>pnpm install &amp;&amp; pnpm build</code> ' . esc_html__('inside the plugin\'s', 'chat-admin') . ' <code>app/</code> ' . esc_html__('directory to produce the chat UI bundle.', 'chat-admin') . '</p>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('ChatAdmin Settings', 'chat-admin') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('chatadmin_settings_group');
        do_settings_sections('chatadmin-settings');
        submit_button();
        echo '</form>';

        $onboarding_url = esc_url(home_url('/chat-admin?onboarding=1'));
        echo '<p style="margin-top:2rem;border-top:1px solid #c3c4c7;padding-top:1rem;">';
        echo '<a href="' . $onboarding_url . '">' . esc_html__('Re-run onboarding wizard', 'chat-admin') . '</a> '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $onboarding_url is esc_url()'d above.
        echo '<span class="description">' . esc_html__('— walk through the capability check + settings again.', 'chat-admin') . '</span>';
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
        echo '<h1>' . esc_html__('ChatAdmin Diagnostics', 'chat-admin') . '</h1>';
        echo '<p class="description">' . esc_html__('Recent errors ChatAdmin recorded on this site. Use “Copy diagnostics” to paste into a support request, or send it straight to the developer.', 'chat-admin') . '</p>';

        // Recent errors table.
        echo '<table class="widefat striped" style="margin-top:1rem;max-width:900px;">';
        echo '<thead><tr><th>' . esc_html__('When (UTC)', 'chat-admin') . '</th><th>' . esc_html__('Event', 'chat-admin') . '</th><th>' . esc_html__('Tool', 'chat-admin') . '</th><th>' . esc_html__('Message', 'chat-admin') . '</th></tr></thead><tbody>';
        if (empty($recent)) {
            echo '<tr><td colspan="4">' . esc_html__('No errors recorded — nice.', 'chat-admin') . '</td></tr>';
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
        echo '<h2 style="margin-top:2rem;">' . esc_html__('Send a report to the developer', 'chat-admin') . '</h2>';
        echo '<p><textarea id="chatadmin-diag-note" rows="3" style="width:100%;max-width:600px;" placeholder="' . esc_attr__('Optional: what were you doing when it broke?', 'chat-admin') . '"></textarea></p>';
        echo '<p>';
        echo '<button type="button" class="button" id="chatadmin-diag-copy">' . esc_html__('Copy diagnostics', 'chat-admin') . '</button> ';
        echo '<button type="button" class="button button-primary" id="chatadmin-diag-send">' . esc_html__('Send to developer', 'chat-admin') . '</button> ';
        echo '<span id="chatadmin-diag-status" style="margin-left:.5rem;"></span>';
        echo '</p>';
        echo '<details style="margin-top:1rem;"><summary>' . esc_html__('Show raw diagnostics', 'chat-admin') . '</summary><pre id="chatadmin-diag-json" style="background:#f6f7f7;border:1px solid #dcdcde;padding:1rem;overflow:auto;max-width:900px;">' . esc_html($diag_json) . '</pre></details>';

        // Inline behavior: copy to clipboard + POST the report via the REST route.
        $boot = wp_json_encode([
            'rest'  => rest_url('chatadmin/v1/support'),
            'nonce' => wp_create_nonce('wp_rest'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $sent_ok   = esc_js(__('Sent — thank you!', 'chat-admin'));
        $sent_fail = esc_js(__('Could not send. Try “Copy diagnostics” and email it.', 'chat-admin'));
        $copied    = esc_js(__('Copied.', 'chat-admin'));
        // Inline admin script: $boot is wp_json_encode(JSON_HEX_*)'d and the
        // status strings are esc_js()'d, so the interpolated values are escaped.
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo "<script>(function(){var b={$boot};"
            . "var s=document.getElementById('chatadmin-diag-status');"
            . "document.getElementById('chatadmin-diag-copy').addEventListener('click',function(){"
            . "navigator.clipboard.writeText(document.getElementById('chatadmin-diag-json').textContent).then(function(){s.textContent='{$copied}';});});"
            . "document.getElementById('chatadmin-diag-send').addEventListener('click',function(){"
            . "s.textContent='…';"
            . "fetch(b.rest,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':b.nonce},credentials:'same-origin',"
            . "body:JSON.stringify({note:document.getElementById('chatadmin-diag-note').value,error:'admin-diagnostics'})})"
            . ".then(function(r){return r.json().catch(function(){return{};}).then(function(d){s.textContent=(r.ok&&d.ok)?'{$sent_ok}':'{$sent_fail}';});})"
            . ".catch(function(){s.textContent='{$sent_fail}';});});})();</script>";
        // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

        echo '</div>';
    }
}
