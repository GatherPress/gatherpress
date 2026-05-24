<?php
/**
 * Utility class responsible for various utility-related functionality.
 *
 * This class provides utility methods for common tasks such as rendering templates, handling key prefixes, and
 * converting time zone markup to an array of choices. It encapsulates these utilities for use throughout the
 * GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_HTML_Tag_Processor;

/**
 * Class Utility.
 *
 * Essential utility functions for the GatherPress plugin.
 *
 * @since 0.27.0
 */
class Utility {

	/**
	 * Renders a template file.
	 *
	 * This method loads and renders a template file located at the specified path.
	 *
	 * @since 0.27.0
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
			// Loading PHP template file, not importing a class.
			require $path; // NOSONAR.
			return '';
		}

		ob_start();
		// Loading PHP template file, not importing a class.
		require $path; // NOSONAR.
		return ob_get_clean();
	}

	/**
	 * Locate a theme-overridable template file.
	 *
	 * Walks the resolution chain used everywhere GatherPress ships an
	 * overridable template:
	 *
	 * 1. `locate_template()` — classic theme / child-theme override.
	 * 2. `locate_block_template()` — block-theme override (HTML template
	 *    parts), keyed off the file basename minus extension.
	 * 3. The caller's bundled fallback under `$fallback_dir` (which may be
	 *    a plugin, mu-plugin, theme, or any other source):
	 *    - the file name as-supplied (so a caller's custom dir can ship a
	 *      `gatherpress_*.php` file under its prefixed name), then
	 *    - the file name with the `gatherpress_` prefix stripped (so the
	 *      bundled core templates live as plain `ical-download.php` on disk).
	 *
	 * Returns the first match, or `''` if nothing exists.
	 *
	 * @since 0.34.0
	 *
	 * @param string $file_name    The template file name, e.g. `gatherpress_ical-download.php`.
	 * @param string $fallback_dir Absolute directory the caller's bundled fallback lives in.
	 * @return string Absolute path to the resolved template, or `''` if none exists.
	 */
	public static function locate_template( string $file_name, string $fallback_dir = '' ): string {
		// `locate_template()` accepts a string, `locate_block_template()`
		// requires an array of candidates.
		$templates      = array( $file_name );
		$theme_template = locate_template( $templates );
		$theme_template = locate_block_template(
			$theme_template,
			pathinfo( $file_name, PATHINFO_FILENAME ),
			$templates
		);

		if ( $theme_template ) {
			$resolved = $theme_template;
		} elseif ( empty( $fallback_dir ) ) {
			$resolved = '';
		} else {
			$resolved = self::resolve_fallback_template_path( $fallback_dir, $file_name );
		}

		/**
		 * Filters the resolved template path returned by `Utility::locate_template()`.
		 *
		 * Lets extension code (plugin, mu-plugin, theme, etc.) override or
		 * replace GatherPress's default theme → block-template → fallback-dir
		 * resolution chain — for example to point the calendar's iCal
		 * templates at a custom directory, or to ship overridable templates
		 * from a companion source via the same utility. Return an empty
		 * string to signal "no template found"; callers will fall back to
		 * their own default.
		 *
		 * @since 0.34.0
		 *
		 * @param string $resolved     Resolved absolute template path, or `''` if no candidate matched.
		 * @param string $file_name    The template file name passed to `Utility::locate_template()`.
		 * @param string $fallback_dir Directory the bundled fallback was looked up in (may be empty).
		 */
		return (string) apply_filters( 'gatherpress_template_path', $resolved, $file_name, $fallback_dir );
	}

	/**
	 * Resolve a caller-bundled template path, with prefix-strip fallback.
	 *
	 * Tries the file name as-supplied first (so a caller can ship a
	 * `gatherpress_*.php` file under its prefixed name on disk), then falls
	 * back to the unprefixed name so the bundled core templates (which live
	 * on disk as plain `ical-download.php`) still resolve when callers pass
	 * the prefixed name (the convention used by `Utility::prefix_key()`).
	 *
	 * @since 0.34.0
	 *
	 * @param string $fallback_dir Absolute directory the caller's bundled fallback lives in.
	 * @param string $file_name    Template file name to check inside `$fallback_dir`.
	 * @return string Resolved template path, or `''` if neither variant exists on disk.
	 */
	private static function resolve_fallback_template_path( string $fallback_dir, string $file_name ): string {
		$fallback_template = trailingslashit( $fallback_dir ) . $file_name;

		if ( file_exists( $fallback_template ) ) {
			return $fallback_template;
		}

		$unprefixed = self::unprefix_key( $file_name );

		if ( $unprefixed === $file_name ) {
			return '';
		}

		$fallback_template = trailingslashit( $fallback_dir ) . $unprefixed;

		return file_exists( $fallback_template ) ? $fallback_template : '';
	}

	/**
	 * Prefixes a key with 'gatherpress_'.
	 *
	 * This method adds the 'gatherpress_' prefix to the provided key and returns the modified key.
	 *
	 * @since 0.27.0
	 *
	 * @param string $key The key to which the prefix will be added.
	 * @return string The key with the 'gatherpress_' prefix.
	 */
	public static function prefix_key( string $key ): string {
		if ( ! str_starts_with( $key, 'gatherpress_' ) ) {
			$key = sprintf( 'gatherpress_%s', $key );
		}

		return $key;
	}

	/**
	 * Remove the 'gatherpress_' prefix from a key.
	 *
	 * This method removes the 'gatherpress_' prefix from the provided key and returns the modified key.
	 *
	 * @since 0.27.0
	 *
	 * @param string $key The key from which the prefix will be removed.
	 * @return string The key with the 'gatherpress_' prefix removed.
	 */
	public static function unprefix_key( string $key ): string {
		return preg_replace( '/^gatherpress_/', '', $key );
	}

	/**
	 * Resolve a single label from a post type's registered labels.
	 *
	 * Wraps `get_post_type_object()` so call sites don't have to defend
	 * against unregistered post types or missing label keys. Lets UI
	 * strings reflect whatever a site builder filtered the labels to,
	 * and lets extenders' event-supporting post types surface their own
	 * labels instead of GatherPress's defaults (#1612).
	 *
	 * @since 0.34.0
	 *
	 * @param string $key       Label key to read (e.g. `singular_name`,
	 *                          `name`, `add_new_item`).
	 * @param string $post_type Post type slug to read the label from.
	 * @return string The resolved label, or empty string when the post
	 *                type isn't registered or the key isn't set.
	 */
	public static function post_type_label( string $key, string $post_type ): string {
		$object = get_post_type_object( $post_type );

		if ( ! $object || empty( $object->labels->$key ) ) {
			return '';
		}

		return (string) $object->labels->$key;
	}

	/**
	 * Convert a snake_case string to camelCase.
	 *
	 * Expects standard snake_case input (lowercase words separated by single underscores).
	 * Leading underscores or consecutive underscores may produce unexpected results.
	 *
	 * @since 0.34.0
	 *
	 * @param string $key The snake_case string to convert.
	 * @return string The converted camelCase string.
	 */
	public static function snake_to_camel( string $key ): string {
		return lcfirst( str_replace( '_', '', ucwords( $key, '_' ) ) );
	}

	/**
	 * Authorization callback for post meta that mirrors the post-level edit cap.
	 *
	 * Routes through `user_can( $user_id, 'edit_post', $object_id )` so the
	 * per-post permission model (`map_meta_cap` → `edit_others_posts`,
	 * `edit_published_posts`, etc.) gates meta the same way it gates the post
	 * itself. Without this, the meta layer would be more permissive than the
	 * post layer that owns it: a custom REST route or third-party
	 * `update_post_meta()` call could bypass the per-post check that the WP
	 * posts controller already enforces on the post.
	 *
	 * Wired in via `'auth_callback' => array( Utility::class, 'can_edit_post_meta' )`
	 * on every editor-writable meta key registered through `register_post_meta()`.
	 *
	 * @since 0.34.0
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $allowed and $meta_key
	 * are required by WP's register_post_meta auth_callback signature.
	 *
	 * @param bool   $allowed   Whether the user can edit the post meta. Unused;
	 *                          we authoritatively return based on `edit_post`.
	 * @param string $meta_key  The meta key being accessed. Unused.
	 * @param int    $object_id The post ID the meta belongs to.
	 * @param int    $user_id   The user ID attempting the edit.
	 * @return bool True if the user can edit the post, false otherwise.
	 */
	public static function can_edit_post_meta(
		bool $allowed,
		string $meta_key,
		int $object_id,
		int $user_id
	): bool {
		return user_can( $user_id, 'edit_post', $object_id );
	}

	/**
	 * Retrieve an array of time zone choices.
	 *
	 * This method converts the Time Zone markup returned by WordPress into an associative array
	 * of time zones grouped by their labels. The array is used to populate select input fields.
	 *
	 * @since 0.27.0
	 *
	 * @return array An array of time zones with labels as keys and time zone choices as values.
	 */
	public static function timezone_choices(): array {
		// Parse `wp_timezone_choice()` output through WordPress's HTML tag
		// processor — no regex, no string-stripping. A previous greedy-regex
		// parser silently broke when WordPress added a `dir="auto"`
		// attribute to optgroup/option tags: the captured value carried
		// trailing markup and never matched the saved timezone, so the
		// editor's timezone select always fell back to the first option.
		// Walking tokens via `WP_HTML_Tag_Processor` insulates this code
		// from any future markup changes WordPress makes to those tags.
		$tags = new WP_HTML_Tag_Processor(
			wp_timezone_choice( 'UTC', get_user_locale() )
		);

		$timezones_clean = array();
		$group           = null;
		$pending_value   = null;

		while ( $tags->next_token() ) {
			$token_type = $tags->get_token_type();

			if ( '#tag' === $token_type ) {
				if ( $tags->is_tag_closer() ) {
					continue;
				}

				$tag_name = $tags->get_tag();

				if ( 'OPTGROUP' === $tag_name ) {
					$label = $tags->get_attribute( 'label' );

					if ( is_string( $label ) && '' !== $label ) {
						$group                     = $label;
						$timezones_clean[ $group ] = array();
					}

					$pending_value = null;
					continue;
				}

				if ( 'OPTION' === $tag_name && null !== $group ) {
					$value         = $tags->get_attribute( 'value' );
					$pending_value = ( is_string( $value ) && '' !== $value ) ? $value : null;
				}

				continue;
			}

			if (
				'#text' === $token_type &&
				null !== $group &&
				null !== $pending_value
			) {
				$text = trim( $tags->get_modifiable_text() );

				if ( '' !== $text ) {
					$timezones_clean[ $group ][ $pending_value ] = $text;
				}

				$pending_value = null;
			}
		}

		return $timezones_clean;
	}

	/**
	 * Get a list of all timezones and UTC offsets.
	 *
	 * This method returns an array containing all available timezones along with standard UTC offsets.
	 *
	 * @since 0.29.0
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
	 * @since 0.29.0
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
	 * @since 0.29.0
	 *
	 * @return string The timezone string representing the system's default timezone.
	 *                Falls back to a UTC offset representation if a named timezone string is not set.
	 */
	public static function get_system_timezone(): string {
		$gmt_offset      = get_option( 'gmt_offset' );
		$timezone_string = get_option( 'timezone_string' );

		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( str_contains( $timezone_string, 'Etc/GMT' ) ) {
			$timezone_string = '';
		}

		if ( empty( $timezone_string ) ) {
			$timezone_string = self::offset_to_timezone_string( (float) $gmt_offset );
		}

		return $timezone_string;
	}

	/**
	 * Convert a gmt_offset (in hours) into a DateTimeZone-compatible string.
	 *
	 * WordPress stores the gmt_offset as a decimal hour (e.g. 5.5 for
	 * India, -3.5 for Newfoundland). PHP's DateTimeZone rejects WP's display
	 * strings like `UTC+0` or `UTC+5` but accepts `UTC` and `+HH:MM` /
	 * `-HH:MM` offsets, so normalize here.
	 *
	 * @since 0.34.0
	 *
	 * @param float $offset Decimal-hour offset from UTC.
	 * @return string PHP-valid timezone identifier.
	 */
	public static function offset_to_timezone_string( float $offset ): string {
		if ( 0.0 === $offset ) {
			return 'UTC';
		}

		$sign    = $offset < 0 ? '-' : '+';
		$abs     = abs( $offset );
		$hours   = (int) $abs;
		$minutes = (int) round( ( $abs - $hours ) * 60 );

		return sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
	}

	/**
	 * Normalize a timezone string to a PHP `DateTimeZone`-compatible form.
	 *
	 * Accepts anything WordPress / GatherPress might have stored (IANA,
	 * `+HH:MM`, `UTC`, `UTC+N`, `UTC-N`, decimal UTC like `UTC+5.5`) and
	 * returns a string that `new DateTimeZone(...)` will accept.
	 *
	 * Unknown values pass through unchanged so downstream error handling
	 * can surface anything genuinely unexpected.
	 *
	 * @since 0.34.0
	 *
	 * @param string $timezone Raw timezone string.
	 * @return string
	 */
	public static function normalize_timezone_string( string $timezone ): string {
		$timezone = trim( $timezone );

		// Empty + the bare "UTC" display string both collapse to UTC.
		if ( '' === $timezone || preg_match( '/^UTC$/i', $timezone ) ) {
			return 'UTC';
		}

		// Already-valid +HH:MM / -HH:MM input or an IANA identifier
		// (America/New_York, Europe/London, etc.) passes straight through —
		// the second preg_match is the WP-style UTC offset parser, so its
		// failure means "not a UTC offset, treat as IANA".
		if ( preg_match( '/^[+-]\d{2}:\d{2}$/', $timezone )
			|| ! preg_match( '/^UTC([+-])(\d+(?:\.\d+)?|\d+:\d{2})$/i', $timezone, $matches ) ) {
			return $timezone;
		}

		$sign    = $matches[1];
		$value   = $matches[2];
		$hours   = 0;
		$minutes = 0;

		if ( str_contains( $value, ':' ) ) {
			list( $hours, $minutes ) = array_map( 'intval', explode( ':', $value ) );
		} elseif ( str_contains( $value, '.' ) ) {
			$hours   = (int) $value;
			$minutes = (int) round( ( (float) $value - $hours ) * 60 );
		} else {
			$hours = (int) $value;
		}

		return ( 0 === $hours && 0 === $minutes )
			? 'UTC'
			: sprintf( '%s%02d:%02d', $sign, $hours, $minutes );
	}

	/**
	 * Retrieve the login URL for an event.
	 *
	 * This method generates and returns the URL for logging in or accessing event-specific content.
	 * It takes the optional `$post_id` parameter to customize the URL based on the event's Post ID.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
			 * @since 0.27.0
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
	 * @since 0.33.0
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
			 * @since 0.27.0
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
	 * @since 0.33.0
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

	/**
	 * Recursively retrieves all block names from a given parsed-block array.
	 *
	 * Traverses a single block's structure (including its `innerBlocks`) and collects
	 * every `blockName` it finds into a flat list. The input is the parsed-block array
	 * shape that WordPress hands to `render_block` filters and `WP_Block`.
	 *
	 * @since 0.34.0
	 *
	 * @param array $blocks A parsed block, typically including `blockName` and `innerBlocks`.
	 * @return array An array of block names found within the provided block structure.
	 */
	public static function get_block_names( array $blocks ): array {
		$block_names = array();

		if ( isset( $blocks['blockName'] ) ) {
			$block_names[] = $blocks['blockName'];
		}

		if ( ! empty( $blocks['innerBlocks'] ) ) {
			foreach ( $blocks['innerBlocks'] as $inner_block ) {
				$block_names = array_merge( $block_names, self::get_block_names( $inner_block ) );
			}
		}

		return $block_names;
	}
}
