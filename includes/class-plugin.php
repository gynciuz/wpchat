<?php
/**
 * WPChat plugin bootstrap.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        new Admin();
        new Settings();
        new Rest();
        new Frontend();
    }

    public static function activate(): void {
        if (!get_option('wpchat_settings')) {
            add_option('wpchat_settings', [
                'anthropic_api_key' => '',
                'model'             => 'claude-sonnet-4-6',
            ]);
        }
        History::migrate();
    }

    public static function deactivate(): void {
        // Intentionally leave wpchat_settings in place — user may reactivate later.
    }
}
