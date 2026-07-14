<?php
/**
 * GitHub-Releases auto-update via Yahnis Elsts' Plugin Update Checker (vendored
 * under vendor-puc/, MIT). Sites see new releases in wp-admin → Plugins →
 * Updates within ~12h of a release (or on manual "Check Again"); one-click
 * "Update Now" pulls the latest release ZIP.
 *
 * This whole file is OMITTED from the WordPress.org build (which serves updates
 * itself and disallows bundled updaters). wpchat.php requires it only when the
 * file is present, so nothing here runs in that build.
 *
 * @package WPChat
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once WPCHAT_DIR . 'vendor-puc/plugin-update-checker/plugin-update-checker.php';

$wpchat_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/gynciuz/wpchat',
    WPCHAT_FILE,
    'wpchat'
);
$wpchat_update_checker->getVcsApi()->enableReleaseAssets();
