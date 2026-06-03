<?php
/**
 * Plugin Name:       WPChat
 * Plugin URI:        https://github.com/gynciuz/wpchat
 * Description:       Chat-based admin for WooCommerce orders. Type "mark order 2833 used" — the assistant calls the right WP/WC functions and renders rich UI inline.
 * Version:           0.5.12
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Gintaras Lukoševičius
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wpchat
 * Update URI:        https://github.com/gynciuz/wpchat
 *
 * @package WPChat
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPCHAT_VERSION', '0.5.12');
define('WPCHAT_FILE', __FILE__);
define('WPCHAT_DIR', plugin_dir_path(__FILE__));
define('WPCHAT_URL', plugin_dir_url(__FILE__));

require_once WPCHAT_DIR . 'includes/class-plugin.php';
require_once WPCHAT_DIR . 'includes/class-admin.php';
require_once WPCHAT_DIR . 'includes/class-settings.php';
require_once WPCHAT_DIR . 'includes/class-anthropic.php';
require_once WPCHAT_DIR . 'includes/class-content-backends.php';
require_once WPCHAT_DIR . 'includes/class-git-sync.php';
require_once WPCHAT_DIR . 'includes/class-history.php';
require_once WPCHAT_DIR . 'includes/class-tools.php';
require_once WPCHAT_DIR . 'includes/class-rest.php';
require_once WPCHAT_DIR . 'includes/class-upload.php';
require_once WPCHAT_DIR . 'includes/class-onboarding.php';
require_once WPCHAT_DIR . 'includes/class-frontend.php';

add_action('plugins_loaded', function () {
    \WPChat\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, ['\WPChat\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\WPChat\Plugin', 'deactivate']);

/*
 * Auto-update from GitHub Releases via Yahnis Elsts' Plugin Update Checker.
 * Vendored under vendor-puc/ (MIT). Every site running this plugin sees new
 * releases in wp-admin → Plugins → Updates within ~12h of being published
 * (or on manual "Check Again"). One-click "Update Now" pulls the latest
 * release ZIP and replaces the plugin folder. The `Update URI` header above
 * pins WordPress's update lookups to this repository so a future WP.org
 * plugin with the same slug can't silently hijack our update channel.
 */
require_once WPCHAT_DIR . 'vendor-puc/plugin-update-checker/plugin-update-checker.php';
$wpchat_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/gynciuz/wpchat',
    __FILE__,
    'wpchat'
);
$wpchat_update_checker->getVcsApi()->enableReleaseAssets();
