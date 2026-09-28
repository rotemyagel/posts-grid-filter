<?php
/**
 * PHPUnit bootstrap: loads the WordPress test library, then the plugin.
 *
 * Under wp-env (npm run test:php) the library comes from the tests-cli
 * container, which sets WP_TESTS_DIR. Without Docker, the wp-phpunit
 * Composer package provides it; point WP_PHPUNIT__TESTS_CONFIG at a
 * wp-tests-config.php for an empty database.
 *
 * @package WMPGF
 */

$wmpgf_plugin_dir = dirname( __DIR__, 2 );

require_once $wmpgf_plugin_dir . '/vendor/autoload.php';

$wmpgf_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wmpgf_tests_dir ) {
	$wmpgf_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $wmpgf_tests_dir || ! file_exists( $wmpgf_tests_dir . '/includes/functions.php' ) ) {
	echo 'WordPress test library not found. Run the tests with `npm run test:php` (wp-env), or run `composer install` and set WP_PHPUNIT__TESTS_CONFIG.' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $wmpgf_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wmpgf_plugin_dir ) {
		require $wmpgf_plugin_dir . '/wm-posts-grid-filter.php';
	}
);

require $wmpgf_tests_dir . '/includes/bootstrap.php';
require_once __DIR__ . '/class-wmpgf-testcase.php';
