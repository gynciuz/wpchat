<?php
/**
 * GitHub-Releases auto-update via Yahnis Elsts' Plugin Update Checker (vendored
 * under vendor-puc/, MIT). Sites see new releases in wp-admin → Plugins →
 * Updates within ~12h of a release (or on manual "Check Again"); one-click
 * "Update Now" pulls the latest release ZIP.
 *
 * This whole file is OMITTED from the WordPress.org build (which serves updates
 * itself and disallows bundled updaters). chat-admin.php requires it only when
 * the file is present, so nothing here runs in that build.
 *
 * The update installs in place: the release ZIP (bin/release.sh) is packaged
 * with a `chat-admin/` prefix, the main file is `chat-admin.php`, and the PUC
 * slug below is `chat-admin` — all three agree, so WordPress replaces the
 * existing plugin folder instead of installing a second copy under a new name
 * and orphaning (deactivating) the old one.
 *
 * @package ChatAdmin
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once CHATADMIN_DIR . 'vendor-puc/plugin-update-checker/plugin-update-checker.php';

$chatadmin_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/gynciuz/wpchat',
    CHATADMIN_FILE,
    'chat-admin'
);
$chatadmin_update_checker->getVcsApi()->enableReleaseAssets();
