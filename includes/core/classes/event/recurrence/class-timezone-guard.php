<?php
/**
 * Guards the fixed-offset timezone hazard.
 *
 * GatherPress normalizes timezones through `Utility::maybe_convert_utc_offset()`
 * and may therefore hold a value such as `UTC+5:30` where a tz-database
 * identifier belongs. PHP classifies those as timezone type 1, which carries no
 * DST rules at all, so a recurring series anchored on one silently drifts. The
 * guard refuses them.
 *
 * Validation is positive, not merely a colon check: the string must appear in
 * `timezone_identifiers_list()` and must contain no colon. The colon test is
 * kept because a colon is what a fixed offset looks like, so the check names
 * the thing being refused and makes the guard's intent legible at a glance.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use InvalidArgumentException;

/**
 * Class Timezone_Guard.
 *
 * Stateless validator. Called from `Expander::expand()` and
 * `Recurrence\Meta::set_recurrence()`.
 *
 * @since 0.36.0
 */
final class Timezone_Guard {

	/**
	 * Report whether a timezone string is a named tz-database identifier.
	 *
	 * Positive validation, not merely the colon check: the string must both
	 * appear in `timezone_identifiers_list()` and contain no colon. The colon
	 * check alone would admit any malformed string without a colon
	 * (`'Not/AZone'`, `'12345'`, `''`) straight through to `DateTimeZone`,
	 * where it becomes a fatal rather than a rejection. The colon check is kept
	 * even though `timezone_identifiers_list()` already excludes offsets
	 * because a colon is the shape of the fixed offset being refused, and
	 * naming the refusal in the guard is what keeps the next reader from
	 * "simplifying" it into an implicit one.
	 *
	 * The colon check is provably redundant today: none of the 419 entries in
	 * `timezone_identifiers_list()` contain a colon, so `in_array()` alone is
	 * the entire guard. It is kept anyway for the legibility reason above, but
	 * `in_array()` is the load-bearing half. Dropping it on the theory that
	 * the colon check is the real test would reopen `'Not/AZone'` and `''`.
	 *
	 * @since 0.36.0
	 *
	 * @param string $timezone Timezone string to validate.
	 *
	 * @return bool True when the string is a named identifier carrying DST rules.
	 */
	public static function is_named( string $timezone ): bool {
		return ! str_contains( $timezone, ':' )
			&& in_array( $timezone, timezone_identifiers_list(), true );
	}

	/**
	 * Assert that a timezone string is a named tz-database identifier.
	 *
	 * @since 0.36.0
	 *
	 * @param string $timezone Timezone string to validate.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the timezone is a fixed offset or otherwise unnamed.
	 */
	public static function assert_named( string $timezone ): void {
		if ( ! self::is_named( $timezone ) ) {
			throw new InvalidArgumentException(
				sprintf( '"%s" is not a named tz-database timezone identifier.', esc_html( $timezone ) )
			);
		}
	}
}
