<?php
/**
 * Plugin Name:       WPChat
 * Plugin URI:        https://github.com/gynciuz/wpchat
 * Description:       Chat-based admin for WooCommerce orders. Type "mark order 2833 used" — the assistant calls the right WP/WC functions and renders rich UI inline.
 * Version:           0.5.8
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Gintaras Lukoševičius
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       wpchat
 *
 * @package WPChat
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPCHAT_VERSION', '0.5.8');
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
require_once WPCHAT_DIR . 'includes/class-frontend.php';

add_action('plugins_loaded', function () {
    \WPChat\Plugin::instance()->boot();
});

register_activation_hook(__FILE__, ['\WPChat\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['\WPChat\Plugin', 'deactivate']);
