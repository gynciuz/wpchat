<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WordPress test framework, then activates the WPChat plugin so
 * its classes, REST routes, and migrations are available to every test.
 *
 * Expects WP_TESTS_DIR env var (path to the wp-tests-lib checkout) or falls
 * back to /tmp/wordpress-tests-lib (the path bin/install-wp-tests.sh uses).
 *
 * Run scenarios + integration tests with a real MySQL + WordPress.
 * Pure-unit tests under tests/Unit/ that don't touch WP can extend
 * \PHPUnit\Framework\TestCase directly (no WP_UnitTestCase) and skip
 * the WP framework — but the bootstrap still loads it because PHPUnit's
 * bootstrap is global.
 *
 * @package WPChat\Tests
 */

$tests_dir = getenv('WP_TESTS_DIR');
if (!$tests_dir) {
    $tests_dir = '/tmp/wordpress-tests-lib';
}

if (!file_exists("{$tests_dir}/includes/functions.php")) {
    fwrite(STDERR, "Could not find {$tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first.\n");
    exit(1);
}

// Manually load WP-test functions then register a muplugin loader that
// loads WPChat before WordPress finishes booting.
require_once "{$tests_dir}/includes/functions.php";

function _wpchat_manually_load_plugin() {
    require dirname(__DIR__) . '/wpchat.php';
}
tests_add_filter('muplugins_loaded', '_wpchat_manually_load_plugin');

require "{$tests_dir}/includes/bootstrap.php";

// Make sure the messages table is migrated before any test runs.
\WPChat\History::migrate();

// Load test helpers.
require_once __DIR__ . '/MockAnthropic.php';
require_once __DIR__ . '/MockOpenAI.php';
require_once __DIR__ . '/MockGemini.php';
require_once __DIR__ . '/TestCase.php';
