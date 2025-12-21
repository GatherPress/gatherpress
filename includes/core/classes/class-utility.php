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
	 * @return string The timezone string representing the system's default timezone.
	 *                Falls back to a UTC offset representation if a named timezone string is not set.
	 */
	public static function get_system_timezone(): string {
		$gmt_offset      = intval( get_option( 'gmt_offset' ) );
		$timezone_string = get_option( 'timezone_string' );

		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( false !== strpos( $timezone_string, 'Etc/GMT' ) ) {
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

	/**
	 * Retrieve the login URL for an event.
	 *
	 * This method generates and returns the URL for logging in or accessing event-specific content.
	 * It takes the optional `$post_id` parameter to customize the URL based on the event's Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. The Post ID of the event. Defaults to 0.
	 * @return string The login URL for the event.
	 */
	public static function get_login_url( int $post_id = 0 ): string {
		$permalink = get_the_permalink( $post_id );

		return wp_login_url( $permalink );
	}

	/**
	 * Retrieve the registration URL for an event.
	 *
	 * This method generates and returns the URL for user registration or accessing event-specific registration.
	 * It takes the optional `$post_id` parameter to customize the URL based on the event's Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. The Post ID of the event. Defaults to 0.
	 * @return string The registration URL for the event, or an empty string if user registration is disabled.
	 */
	public static function get_registration_url( int $post_id = 0 ): string {
		$permalink = get_the_permalink( $post_id );
		$url       = '';

		if ( get_option( 'users_can_register' ) ) {
			$url = add_query_arg( 'redirect', $permalink, wp_registration_url() );
		}

		return $url;
	}

	/**
	 * Ensures proper user authentication for AJAX/REST API contexts.
	 *
	 * When WordPress processes AJAX or REST API requests, the user context may not
	 * be properly established, causing functions like current_user_can() to behave
	 * incorrectly. This method forces WordPress to determine and set the current user,
	 * ensuring consistent authentication behavior between server-side rendering and
	 * dynamic requests.
	 *
	 * This is particularly important after the introduction of dynamic nonce generation,
	 * which changed how user authentication flows through the application.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false The user ID if authentication was successful, false otherwise.
	 */
	public static function ensure_user_authentication() {
		// Force WordPress to authenticate the user.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$user_id = apply_filters( 'determine_current_user', false );

		if ( $user_id ) {
			wp_set_current_user( $user_id );
		}

		return $user_id;
	}

	/**
	 * Check if a CSS class string contains a specific class.
	 *
	 * This method properly handles space-separated CSS class strings and checks for
	 * exact class matches, preventing false positives from substring matches.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $class_string The CSS class string to search in.
	 * @param string      $target_class The specific class to search for.
	 *
	 * @return bool True if the target class is found, false otherwise.
	 */
	public static function has_css_class( ?string $class_string, string $target_class ): bool {
		if ( empty( $class_string ) || empty( $target_class ) ) {
			return false;
		}

		$classes = preg_split( '/\s+/', trim( $class_string ) );

		return in_array( $target_class, $classes, true );
	}

	/**
	 * Get HTTP input with optional mocking for testing.
	 *
	 * Wrapper around filter_input() that can be easily mocked for testing.
	 * In production, uses real filter_input(). In tests, can use filters to mock data.
	 *
	 * @since 1.0.0
	 *
	 * @param int           $type      Input type (INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, INPUT_ENV).
	 * @param string        $var_name  Variable name to retrieve.
	 * @param callable|null $sanitizer Sanitization function to apply. Defaults to sanitize_text_field.
	 *
	 * @return string Sanitized input value or empty string if not found.
	 */
	public static function get_http_input( int $type, string $var_name, ?callable $sanitizer = null ): string {
		$value = null;

		// Only allow pre-filtering during unit tests for security.
		if ( defined( 'WP_TESTS_DOMAIN' ) || ( defined( 'PHPUNIT_RUNNING' ) && PHPUNIT_RUNNING ) ) {
			/**
			 * Short-circuit filter for HTTP input retrieval during testing.
			 *
			 * Allows tests to completely bypass filter_input() and provide
			 * their own values. Only available during unit tests for security.
			 * Return a non-null value to short-circuit.
			 *
			 * @since 1.0.0
			 *
			 * @param string|null $pre_value Pre-value to return instead of using filter_input.
			 * @param int         $type      Input type (INPUT_GET, INPUT_POST, etc.).
			 * @param string      $var_name  Variable name being requested.
			 */
			$pre_value = apply_filters( 'gatherpress_pre_get_http_input', null, $type, $var_name );

			if ( null !== $pre_value ) {
				$value = $pre_value;
			}
		}

		if ( null === $value ) {
			/**
			 * Raw input value from HTTP request.
			 *
			 * @var string|false|null $value
			 * @phpstan-var 0|1|2|4|5 $type
			 */
			$value = filter_input( $type, $var_name );
		}

		if ( null === $value || false === $value ) {
			return '';
		}

		// Apply sanitizer function.
		if ( null === $sanitizer ) {
			$sanitizer = 'sanitize_text_field';
		}

		// For WordPress sanitizers, unslash first.
		if ( in_array( $sanitizer, array( 'sanitize_text_field', 'sanitize_email' ), true ) ) {
			$value = wp_unslash( $value );
		}

		return (string) call_user_func( $sanitizer, $value );
	}

	/**
	 * Wrapper for wp_get_referer() with testable fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The referer URL on success, false on failure.
	 */
	public static function get_wp_referer() {
		// Only allow pre-filtering during unit tests for security.
		if ( defined( 'WP_TESTS_DOMAIN' ) || ( defined( 'PHPUNIT_RUNNING' ) && PHPUNIT_RUNNING ) ) {
			/**
			 * Short-circuit filter for wp_get_referer() during testing.
			 *
			 * Allows tests to completely bypass wp_get_referer() and provide
			 * their own referer values. Only available during unit tests for security.
			 * Return a non-null value to short-circuit.
			 *
			 * @since 1.0.0
			 *
			 * @param string|false|null $pre_value Pre-value to return instead of using wp_get_referer().
			 */
			$pre_value = apply_filters( 'gatherpress_pre_get_wp_referer', null );
			if ( null !== $pre_value ) {
				return $pre_value;
			}
		}

		return wp_get_referer();
	}

	/**
	 * Safely exits the script in a testable way.
	 *
	 * This method provides a centralized exit point that returns early during unit tests
	 * instead of calling exit(). The actual exit statement is excluded from code coverage.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function safe_exit(): void {
		// Return early during unit tests instead of exiting.
		if ( defined( 'WP_TESTS_DOMAIN' ) || ( defined( 'PHPUNIT_RUNNING' ) && PHPUNIT_RUNNING ) ) {
			return;
		}

		// @codeCoverageIgnoreStart
		exit;
		// @codeCoverageIgnoreEnd
	}
}
