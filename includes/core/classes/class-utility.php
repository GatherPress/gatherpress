<?php
/**
 * Utility class responsible for various utility-related functionality.
 *
 * This class provides utility methods for common tasks such as rendering templates, handling key prefixes, and
 * converting time zone markup to an array of choices. It encapsulates these utilities for use throughout the
 * GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

/**
 * Class Utility.
 *
 * Essential utility functions for the GatherPress plugin.
 *
 * @since 1.0.0
 */
class Utility {
	/**
	 * Renders a template file.
	 *
	 * This method loads and renders a template file located at the specified path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path      The path to the template file.
	 * @param array  $variables An array of variables to pass to the template.
	 * @param bool   $echo      Whether to echo the template (true) or return it (false).
	 * @return string The rendered template as a string.
	 */
	public static function render_template( string $path, array $variables = array(), bool $echo = false ): string {
		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! empty( $variables ) ) {
			extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		if ( true === $echo ) {
			require $path;
			return '';
		}

		ob_start();
		require $path;
		return ob_get_clean();
	}

	/**
	 * Prefixes a key with 'gp_'.
	 *
	 * This method adds the 'gp_' prefix to the provided key and returns the modified key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key to which the prefix will be added.
	 * @return string The key with the 'gp_' prefix.
	 */
	public static function prefix_key( string $key ): string {
		if ( 0 !== strpos( $key, 'gp_' ) ) {
			$key = sprintf( 'gp_%s', $key );
		}

		return $key;
	}

	/**
	 * Remove the 'gp_' prefix from a key.
	 *
	 * This method removes the 'gp_' prefix from the provided key and returns the modified key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key from which the prefix will be removed.
	 * @return string The key with the 'gp_' prefix removed.
	 */
	public static function unprefix_key( string $key ): string {
		return preg_replace( '/^gp_/', '', $key );
	}

	/**
	 * Retrieve an array of time zone choices.
	 *
	 * This method converts the Time Zone markup returned by WordPress into an associative array
	 * of time zones grouped by their labels. The array is used to populate select input fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of time zones with labels as keys and time zone choices as values.
	 */
	public static function timezone_choices(): array {
		$timezones_raw   = explode( PHP_EOL, wp_timezone_choice( 'UTC' ) );
		$timezones_clean = array();
		$group           = null;

		foreach ( $timezones_raw as $timezone ) {
			preg_match( '/<optgroup label="(.+)">/', $timezone, $matches );

			if ( 2 === count( $matches ) ) {
				$group                     = $matches[1];
				$timezones_clean[ $group ] = array();
				continue;
			}

			preg_match( '/<option.*value="(.+)">(.+)<\/option>/', $timezone, $matches );

			if ( ! empty( $group ) && 3 === count( $matches ) ) {
				$timezones_clean[ $group ][ $matches[1] ] = $matches[2];
			}
		}

		return $timezones_clean;
	}
}
