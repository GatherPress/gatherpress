<?php
/**
 * Test helper functions for GatherPress.
 *
 * @package GatherPress
 * @since   1.0.0
 */

namespace GatherPress\Core;

/**
 * Mock filter_input function for testing.
 *
 * This function will override the global filter_input function within
 * the GatherPress\Core namespace during tests, allowing us to mock
 * filter_input behavior.
 *
 * @since 1.0.0
 *
 * @param int    $type      Filter type (INPUT_GET, INPUT_POST, etc.).
 * @param string $var_name  Variable name.
 * @param int    $filter    Filter to apply (optional).
 * @param mixed  $options   Options (optional).
 *
 * @return mixed Mocked return value or original filter_input result.
 */
function filter_input( $type, $var_name, $filter = FILTER_DEFAULT, $options = 0 ) {
	// Check if we have a mock for this function.
	if ( isset( $GLOBALS['gatherpress_test_filter_input_mock'] ) ) {
		return call_user_func( $GLOBALS['gatherpress_test_filter_input_mock'], $type, $var_name, $filter, $options );
	}

	// Fall back to the global filter_input function.
	return \filter_input( $type, $var_name, $filter, $options );
}

/**
 * Mock wp_get_referer function for testing.
 *
 * This function will override the global wp_get_referer function within
 * the GatherPress\Core namespace during tests.
 *
 * @since 1.0.0
 *
 * @return string|false The referer URL or false.
 */
function wp_get_referer() {
	// Check if we have a mock for this function.
	if ( isset( $GLOBALS['gatherpress_test_wp_get_referer_mock'] ) ) {
		return call_user_func( $GLOBALS['gatherpress_test_wp_get_referer_mock'] );
	}

	// Fall back to the global wp_get_referer function.
	return \wp_get_referer();
}
