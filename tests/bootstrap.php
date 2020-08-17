<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Gatherpress
 */

$gatherpress_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $gatherpress_tests_dir ) {
	$gatherpress_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $gatherpress_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $gatherpress_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $gatherpress_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function gatherpress_manually_load_plugin() {
	// Include BuddyPress from composer.
	require_once dirname( dirname( __FILE__ ) ) . '/vendor/buddypress/bp-loader.php';

	require_once dirname( dirname( __FILE__ ) ) . '/gatherpress.php';
}
tests_add_filter( 'muplugins_loaded', 'gatherpress_manually_load_plugin' );

// Start up the WP testing environment.
require $gatherpress_tests_dir . '/includes/bootstrap.php';
