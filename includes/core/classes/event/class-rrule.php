<?php
/**
 * Parses the RRULE subset repeat rules support.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Rrule.
 *
 * Reads a rule's date pattern from its RFC 5545 `RRULE` string: `FREQ`,
 * `INTERVAL`, `BYDAY` and `BYMONTHDAY`, in the combinations `Rule` expands.
 *
 * @since TBD
 *
 * @phpstan-import-type RulePattern from Rule
 */
final class Rrule {

	/**
	 * Parse the supported `RRULE` subset.
	 *
	 * @since TBD
	 *
	 * @param mixed $rrule The pattern, or null for a single date.
	 *
	 * @return array<string, mixed>|null The parts, or null if the pattern is not supported.
	 * @phpstan-return RulePattern|null
	 */
	public static function parse( mixed $rrule ): ?array {
		$parts = array(
			'frequency'       => null,
			'interval'        => 1,
			'weekdays'        => array(),
			'ordinal_weekday' => null,
			'month_day'       => null,
		);

		if ( null === $rrule || '' === $rrule ) {
			return $parts;
		}

		if ( ! is_string( $rrule ) ) {
			return null;
		}

		$values = array();

		foreach ( explode( ';', strtoupper( $rrule ) ) as $pair ) {
			list( $name, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );

			$supported = array( 'FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY' );

			if ( isset( $values[ $name ] ) || ! in_array( $name, $supported, true ) ) {
				return null;
			}

			$values[ $name ] = $value;
		}

		$parts['frequency'] = in_array( $values['FREQ'] ?? '', Rules::FREQUENCIES, true ) ? $values['FREQ'] : null;

		if ( isset( $values['INTERVAL'] ) ) {
			$parts['interval'] = ctype_digit( $values['INTERVAL'] ) ? (int) $values['INTERVAL'] : 0;
		}

		if ( ! $parts['frequency'] || 1 > $parts['interval'] || Rule::MAX_INTERVAL < $parts['interval'] ) {
			return null;
		}

		return self::by_parts( $parts, $values );
	}

	/**
	 * Parse `BYDAY` and `BYMONTHDAY` for the frequencies that take them.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>  $parts  The parts parsed so far.
	 * @param array<string, string> $values The raw `RRULE` values.
	 * @phpstan-param RulePattern $parts
	 *
	 * @return array<string, mixed>|null The parts, or null if `BYDAY` or `BYMONTHDAY` is not supported.
	 * @phpstan-return RulePattern|null
	 */
	protected static function by_parts( array $parts, array $values ): ?array {
		$by_day       = $values['BYDAY'] ?? null;
		$by_month_day = $values['BYMONTHDAY'] ?? null;

		if ( 'WEEKLY' === $parts['frequency'] && null === $by_month_day ) {
			$weekdays = null === $by_day ? array() : explode( ',', $by_day );

			$repeated = count( $weekdays ) !== count( array_unique( $weekdays ) );

			if ( $repeated || array_diff( $weekdays, Rules::WEEKDAYS ) ) {
				return null;
			}

			// Kept in week order, so the canonical form doesn't depend on how it was written.
			$parts['weekdays'] = array_values( array_intersect( Rules::WEEKDAYS, $weekdays ) );

			return $parts;
		}

		if ( 'MONTHLY' === $parts['frequency'] && ( null === $by_day || null === $by_month_day ) ) {
			if ( null !== $by_day ) {
				if ( ! preg_match( '/^(-1|[1-4])(MO|TU|WE|TH|FR|SA|SU)$/', $by_day, $matches ) ) {
					return null;
				}

				$parts['ordinal_weekday'] = array( (int) $matches[1], $matches[2] );
			}

			if ( null !== $by_month_day ) {
				if ( ! ctype_digit( $by_month_day ) || 1 > (int) $by_month_day || 31 < (int) $by_month_day ) {
					return null;
				}

				$parts['month_day'] = (int) $by_month_day;
			}

			return $parts;
		}

		$takes_no_by_parts = in_array( $parts['frequency'], array( 'DAILY', 'YEARLY' ), true );

		return ( $takes_no_by_parts && null === $by_day && null === $by_month_day ) ? $parts : null;
	}
}
