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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

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
	 * @param bool   $output    Whether to echo the template (true) or return it (false).
	 * @return string The rendered template as a string.
	 */
	public static function render_template( string $path, array $variables = array(), bool $output = false ): string {
		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! empty( $variables ) ) {
			extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		if ( true === $output ) {
			require $path;
			return '';
		}

		ob_start();
		require $path;
		return ob_get_clean();
	}

	/**
	 * Prefixes a key with 'gatherpress_'.
	 *
	 * This method adds the 'gatherpress_' prefix to the provided key and returns the modified key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key to which the prefix will be added.
	 * @return string The key with the 'gatherpress_' prefix.
	 */
	public static function prefix_key( string $key ): string {
		if ( 0 !== strpos( $key, 'gatherpress_' ) ) {
			$key = sprintf( 'gatherpress_%s', $key );
		}

		return $key;
	}

	/**
	 * Remove the 'gatherpress_' prefix from a key.
	 *
	 * This method removes the 'gatherpress_' prefix from the provided key and returns the modified key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key from which the prefix will be removed.
	 * @return string The key with the 'gatherpress_' prefix removed.
	 */
	public static function unprefix_key( string $key ): string {
		return preg_replace( '/^gatherpress_/', '', $key );
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
		$timezones_raw   = explode( PHP_EOL, wp_timezone_choice( 'UTC', get_user_locale() ) );
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

	/**
	 * Get a list of all timezones and UTC offsets.
	 *
	 * This method returns an array containing all available timezones along with standard UTC offsets.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of timezone identifiers and UTC offsets.
	 */
	public static function list_timezone_and_utc_offsets(): array {
		// Get a list of all available timezone identifiers.
		$identifiers = timezone_identifiers_list();

		// Define an array of standard UTC offsets.
		$offset_range = array(
			'-12:00',
			'-11:30',
			'-11:00',
			'-10:30',
			'-10:00',
			'-09:30',
			'-09:00',
			'-08:30',
			'-08:00',
			'-07:30',
			'-07:00',
			'-06:30',
			'-06:00',
			'-05:30',
			'-05:00',
			'-04:30',
			'-04:00',
			'-03:30',
			'-03:00',
			'-02:30',
			'-02:00',
			'-01:30',
			'-01:00',
			'-00:30',
			'+00:00',
			'+00:30',
			'+01:00',
			'+01:30',
			'+02:00',
			'+02:30',
			'+03:00',
			'+03:30',
			'+04:00',
			'+04:30',
			'+05:00',
			'+05:30',
			'+05:45',
			'+06:00',
			'+06:30',
			'+07:00',
			'+07:30',
			'+08:00',
			'+08:30',
			'+08:45',
			'+09:00',
			'+09:30',
			'+10:00',
			'+10:30',
			'+11:00',
			'+11:30',
			'+12:00',
			'+12:45',
			'+13:00',
			'+13:45',
			'+14:00',
		);

		// Merge the timezone identifiers and UTC offsets into a single array.
		return array_merge( $identifiers, $offset_range );
	}

	/**
	 * Convert a UTC offset to a format compatible with DateTimeZone.
	 *
	 * This method takes a UTC offset in the form of "+HH:mm" or "-HH:mm" and converts it to a format
	 * that can be used with the DateTimeZone constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $timezone The UTC offset to convert, e.g., "+05:30" or "-08:00".
	 * @return string The converted timezone format, e.g., "+0530" or "-0800".
	 */
	public static function maybe_convert_utc_offset( string $timezone ): string {
		// Regex: https://regex101.com/r/wxhjIu/1.
		preg_match( '/^UTC([+-])(\d+)(.\d+)?$/', $timezone, $matches );

		if ( ! count( $matches ) ) {
			return $timezone;
		}

		if ( empty( $matches[3] ) ) {
			$matches[3] = ':00';
		}

		$matches[3] = str_replace( array( '.25', '.5', '.75' ), array( ':15', ':30', ':45' ), $matches[3] );

		return $matches[1] . str_pad( $matches[2], 2, '0', STR_PAD_LEFT ) . $matches[3];
	}

	/**
	 * Retrieves the system default timezone settings.
	 *
	 * Attempts to get the timezone set in WordPress settings. If a timezone string is not set,
	 * it falls back to using the GMT offset to construct a UTC timezone string. Note that
	 * 'Etc/GMT' timezone strings are considered outdated and are stripped in favor of a UTC
	 * representation.
	 *
	 * @since 1.0.0
	 *
	 * @return string The timezone string representing the system's default timezone. Falls back to a UTC offset representation if a named timezone string is not set.
	 */
	public static function get_system_timezone(): string {
		$gmt_offset      = intval( get_option( 'gmt_offset' ) );
		$timezone_string = get_option( 'timezone_string' );

		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( str_contains( $timezone_string, 'Etc/GMT' ) ) {
			$timezone_string = '';
		}

		if ( empty( $timezone_string ) ) { // Create a UTC+- zone if no timezone string exists.
			if ( 0 === $gmt_offset ) {
				$timezone_string = 'UTC+0';
			} elseif ( $gmt_offset < 0 ) {
				$timezone_string = 'UTC' . $gmt_offset;
			} else {
				$timezone_string = 'UTC+' . $gmt_offset;
			}
		}

		return $timezone_string;
	}
}
