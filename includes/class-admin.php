<?php
/**
 * WPChat admin pages.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    const CAPABILITY = 'edit_shop_orders';
    const MENU_SLUG  = 'wpchat';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void {
        $capability = current_user_can('manage_woocommerce')
            ? 'manage_woocommerce'
            : self::CAPABILITY;

        add_menu_page(
            __('WPChat', 'wpchat'),
            __('WPChat', 'wpchat'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page'],
            'dashicons-format-chat',
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Chat', 'wpchat'),
            __('Chat', 'wpchat'),
            $capability,
            self::MENU_SLUG,
            [$this, 'render_chat_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Settings', 'wpchat'),
            __('Settings', 'wpchat'),
            'manage_options',
            self::MENU_SLUG . '-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_assets(string $hook): void {
        if (strpos($hook, self::MENU_SLUG) === false) {
            return;
        }

        $build_dir = WPCHAT_DIR . 'build/';
        $build_url = WPCHAT_URL . 'build/';

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
                wp_enqueue_style('wpchat-app-' . md5($css), $build_url . $css, [], WPCHAT_VERSION);
            }
        }

        wp_enqueue_script(
            'wpchat-app',
            $build_url . $entry['file'],
            [],
            WPCHAT_VERSION,
            true
        );

        wp_add_inline_script(
            'wpchat-app',
            'window.WPCHAT_BOOT = ' . wp_json_encode([
                'restUrl' => rest_url('wpchat/v1/'),
                'nonce'   => wp_create_nonce('wp_rest'),
                'userId'  => get_current_user_id(),
                'locale'  => substr(get_user_locale(), 0, 2),
            ]) . ';',
            'before'
        );
    }

    public function render_chat_page(): void {
        echo '<div class="wrap"><div id="wpchat-root">';
        if (!file_exists(WPCHAT_DIR . 'build/manifest.json')) {
            echo '<div style="padding:2rem;border:1px dashed #c3c4c7;background:#fff;margin-top:1rem;">';
            echo '<h2 style="margin-top:0;">' . esc_html__('WPChat — build assets missing', 'wpchat') . '</h2>';
            echo '<p>' . esc_html__('Run', 'wpchat') . ' <code>pnpm install &amp;&amp; pnpm build</code> ' . esc_html__('inside the plugin\'s', 'wpchat') . ' <code>app/</code> ' . esc_html__('directory to produce the chat UI bundle.', 'wpchat') . '</p>';
            echo '<p>' . esc_html__('Once', 'wpchat') . ' <code>build/manifest.json</code> ' . esc_html__('exists, this page will render the chat interface.', 'wpchat') . '</p>';
            echo '</div>';
        }
        echo '</div></div>';
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('WPChat Settings', 'wpchat') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('wpchat_settings_group');
        do_settings_sections('wpchat-settings');
        submit_button();
        echo '</form>';

        $onboarding_url = esc_url(home_url('/wpchat?onboarding=1'));
        echo '<p style="margin-top:2rem;border-top:1px solid #c3c4c7;padding-top:1rem;">';
        echo '<a href="' . $onboarding_url . '">' . esc_html__('Re-run onboarding wizard', 'wpchat') . '</a> ';
        echo '<span class="description">' . esc_html__('— walk through the capability check + settings again.', 'wpchat') . '</span>';
        echo '</p>';

        echo '</div>';
    }
}
