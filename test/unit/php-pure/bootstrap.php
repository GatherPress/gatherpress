<?php
/**
 * PHPUnit bootstrap file for pure unit tests.
 *
 * Boots a minimal environment with Composer and GatherPress autoloaders,
 * without loading WordPress, wp-env, or Docker.
 *
 * @package GatherPress
 * @subpackage Tests\Pure
 * @since TBD
 */

// phpcs:disable Squiz.Commenting.FileComment.Missing
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.valueFound

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
}

if ( ! defined( 'GATHERPRESS_CORE_PATH' ) ) {
	define( 'GATHERPRESS_CORE_PATH', dirname( __DIR__, 3 ) );
}

// Require Composer autoloader.
require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

// Stub minimal WordPress functions required for pure class autoloading and testing.
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Minimal stub for apply_filters when running outside WordPress.
	 *
	 * @param string $hook_name The name of the filter hook.
	 * @param mixed  $value     The value to filter.
	 * @return mixed The unfiltered value.
	 */
	function apply_filters( $hook_name, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'validate_file' ) ) {
	/**
	 * Minimal stub for validate_file when running outside WordPress.
	 *
	 * @param mixed $file The file path to validate.
	 * @return int 0 if valid relative path, 2 if absolute path, 1 if path traversal or invalid.
	 */
	function validate_file( $file ) {
		if ( ! is_string( $file ) ) {
			return 1;
		}

		if ( str_contains( $file, '../' ) || str_contains( $file, '..\\' ) ) {
			return 1;
		}

		if ( 1 === preg_match( '|^([a-zA-Z]:)?[/\\\\]|', $file ) ) {
			return 2;
		}

		return 0;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Minimal stub for wp_json_encode when running outside WordPress.
	 *
	 * @param mixed       $data    Data to encode.
	 * @param int         $options Optional options.
	 * @param int<1, max> $depth   Optional depth.
	 * @return string|false
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

// Register GatherPress plugin autoloader.
require_once dirname( __DIR__, 3 ) . '/includes/core/classes/class-autoloader.php';
\GatherPress\Core\Autoloader::register();
