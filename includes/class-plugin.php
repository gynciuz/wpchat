<?php
/**
 * ChatAdmin plugin bootstrap.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

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
        new Upload();
        new Onboarding();
        new Seo();
        new Frontend();
    }

    public static function activate(): void {
        if (!get_option('chatadmin_settings')) {
            add_option('chatadmin_settings', [
                'anthropic_api_key' => '',
                'model'             => 'claude-sonnet-4-6',
            ]);
        }
        History::migrate();
    }

    public static function deactivate(): void {
        // Intentionally leave chatadmin_settings in place — user may reactivate later.
    }
}
