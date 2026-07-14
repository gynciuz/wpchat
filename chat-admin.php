<?php
/**
 * Plugin Name:       ChatAdmin
 * Plugin URI:        https://github.com/gynciuz/wpchat
 * Description:       Chat-based admin for WooCommerce orders. Type "mark order 2833 used" — the assistant calls the right WP/WC functions and renders rich UI inline.
 * Version:           0.7.2
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Gintaras Lukoševičius
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       chat-admin
 * Update URI:        https://github.com/gynciuz/wpchat
 *
 * @package ChatAdmin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CHATADMIN_VERSION', '0.7.2');
define('CHATADMIN_FILE', __FILE__);
define('CHATADMIN_DIR', plugin_dir_path(__FILE__));
define('CHATADMIN_URL', plugin_dir_url(__FILE__));

require_once CHATADMIN_DIR . 'includes/class-plugin.php';
require_once CHATADMIN_DIR . 'includes/class-admin.php';
require_once CHATADMIN_DIR . 'includes/class-settings.php';
require_once CHATADMIN_DIR . 'includes/class-llm-providers.php';
require_once CHATADMIN_DIR . 'includes/class-anthropic.php';
require_once CHATADMIN_DIR . 'includes/class-content-backends.php';
require_once CHATADMIN_DIR . 'includes/class-seo.php';
require_once CHATADMIN_DIR . 'includes/class-analytics-providers.php';
// GitSync (optional git-commit-on-write API for file-writing backends) is
// omitted from the WordPress.org build — load it only when the file is present.
if (file_exists(CHATADMIN_DIR . 'includes/class-git-sync.php')) {
    require_once CHATADMIN_DIR . 'includes/class-git-sync.php';
}
require_once CHATADMIN_DIR . 'includes/class-history.php';
require_once CHATADMIN_DIR . 'includes/class-telemetry.php';
require_once CHATADMIN_DIR . 'includes/class-tools.php';
require_once CHATADMIN_DIR . 'includes/class-rest.php';
require_once CHATADMIN_DIR . 'includes/class-upload.php';
require_once CHATADMIN_DIR . 'includes/class-onboarding.php';
require_once CHATADMIN_DIR . 'includes/class-frontend.php';

add_action('plugins_loaded', function () {
    \ChatAdmin\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, ['\ChatAdmin\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\ChatAdmin\Plugin', 'deactivate']);

/*
 * Auto-update from GitHub Releases via Yahnis Elsts' Plugin Update Checker.
 * Vendored under vendor-puc/ (MIT). Every site running this plugin sees new
 * releases in wp-admin → Plugins → Updates within ~12h of being published
 * (or on manual "Check Again"). One-click "Update Now" pulls the latest
 * release ZIP and replaces the plugin folder. The `Update URI` header above
 * pins WordPress's update lookups to this repository so a future WP.org
 * plugin with the same slug can't silently hijack our update channel.
 */
// Optional GitHub-Releases auto-update helper. The whole file is stripped from
// the WordPress.org build, so this require simply no-ops there.
if (file_exists(CHATADMIN_DIR . 'includes/updater.php')) {
    require_once CHATADMIN_DIR . 'includes/updater.php';
}
