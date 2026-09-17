<?php
/**
 * Resolves a WordPress date query into a window of event time.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Turns a `date_query` into the window of time an event has to touch.
 *
 * WordPress resolves `date_query` against `post_date`, which for an event is
 * the day someone wrote the post, not the day the event happens. This reads
 * the same argument shapes and hands back a window that the events table can
 * be compared against instead.
 *
 * Boundaries are reckoned in the site's timezone, the way WordPress reckons
 * `post_date`, and returned as UTC. "Next month" therefore means the month
 * the site is in, not the month each event's own timezone is in, which is the
 * only reading that stays coherent across a schedule spanning many timezones.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */
final class Date_Query {

	/**
	 * Format both stored event datetimes and resolved boundaries use.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Column a date query resolves against unless it names another.
	 *
	 * The GMT pair is the only one comparable across events in different
	 * timezones, so it is the default. Naming `datetime_start` instead buckets
	 * by each event's own clock, which is what a list table wants when the
	 * column beside the filter renders in that clock.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DEFAULT_COLUMN = 'datetime_start_gmt';

	/**
	 * Columns a date query may name to be resolved against event dates.
	 *
	 * Any other column, `post_date` included, is left for WordPress to handle
	 * the way it always has.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const EVENT_COLUMNS = array( self::DEFAULT_COLUMN, 'datetime_start' );

	/**
	 * Arguments a clause may carry and still be resolved.
	 *
	 * A clause carrying anything else is refused outright rather than
	 * resolved in part. Honoring `year` while quietly dropping `week` would
	 * hand back a window a whole year wide, and the caller lifts the original
	 * `date_query` out of the query once a window comes back, so there is
	 * nothing left to apply the dropped argument.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const SUPPORTED_ARGS = array(
		'after',
		'before',
		'column',
		'day',
		'inclusive',
		'month',
		'monthnum',
		'year',
	);

	/**
	 * Resolve a date query into the window an event has to touch.
	 *
	 * Reads the range-shaped arguments only: `after`, `before`, `year`,
	 * `monthnum`, `month` and `day`, plus `inclusive`. Everything else
	 * WordPress accepts (`week`, `dayofweek`, `hour`, and friends) describes a
	 * repeating slice of the calendar rather than one stretch of it, and has no
	 * single answer across events in different timezones, so it is left alone
	 * rather than answered wrongly.
	 *
	 * A date query is resolved in full or not at all. One clause carrying only
	 * arguments from `SUPPORTED_ARGS` resolves; anything else, including a
	 * relation, a second clause, or one unreadable argument alongside readable
	 * ones, resolves to nothing and is left for WordPress.
	 *
	 * Arguments narrow each other the way they do in a WordPress clause, so a
	 * calendar span alongside an `after` or `before` yields their overlap
	 * rather than whichever was read last.
	 *
	 * Boundaries come back in the clock of the column they will be compared
	 * against: UTC for the GMT pair, the site's own time for the local pair. A
	 * clause naming a column outside `EVENT_COLUMNS` resolves to nothing, so
	 * the caller leaves it to WordPress.
	 *
	 * @since 0.36.0
	 *
	 * @param array<int|string, mixed> $date_query A WordPress date query.
	 *
	 * @return array{start: ?string, end: ?string, column: string}|null The window and
	 *         the start column it is for, either bound null when open ended, or null
	 *         when nothing could be read.
	 */
	public static function resolve( array $date_query ): ?array {
		$clause = self::single_clause( $date_query );

		if ( null === $clause ) {
			return null;
		}

		$column = (string) ( $clause['column'] ?? self::DEFAULT_COLUMN );

		if ( ! in_array( $column, self::EVENT_COLUMNS, true ) ) {
			return null;
		}

		$inclusive = ! empty( $clause['inclusive'] );
		$start     = null;
		$end       = null;

		$calendar = self::resolve_calendar_span( $clause );

		if ( null !== $calendar ) {
			list( $start, $end ) = $calendar;
		}

		if ( isset( $clause['after'] ) ) {
			$after = self::to_site_datetime( $clause['after'], false );

			if ( null !== $after ) {
				// WordPress treats `after` as exclusive unless told otherwise,
				// and the window is built to the second.
				$after = $inclusive ? $after : $after->modify( '+1 second' );
				$start = ( null === $start || $after > $start ) ? $after : $start;
			}
		}

		if ( isset( $clause['before'] ) ) {
			$before = self::to_site_datetime( $clause['before'], true );

			if ( null !== $before ) {
				$before = $inclusive ? $before : $before->modify( '-1 second' );
				$end    = ( null === $end || $before < $end ) ? $before : $end;
			}
		}

		if ( null === $start && null === $end ) {
			return null;
		}

		$in_utc = ( self::DEFAULT_COLUMN === $column );

		return array(
			'start'  => self::to_string( $start, $in_utc ),
			'end'    => self::to_string( $end, $in_utc ),
			'column' => $column,
		);
	}

	/**
	 * Pull the one clause a date query can be resolved from.
	 *
	 * A date query may carry its arguments at the top level or wrap a single
	 * clause in a list. Both shapes are common in the wild, so both are read.
	 * Anything else, a relation, a second clause, or an argument outside
	 * `SUPPORTED_ARGS`, cannot be honored in full and so is not honored at all.
	 *
	 * @since 0.36.0
	 *
	 * @param array<int|string, mixed> $date_query A WordPress date query.
	 *
	 * @return array<string, mixed>|null The clause to read, or null when there is not exactly one to read.
	 */
	private static function single_clause( array $date_query ): ?array {
		$nested = array_filter( $date_query, 'is_int', ARRAY_FILTER_USE_KEY );

		// A wrapped clause is read only when it is the whole date query, which
		// rules out a relation or a sibling clause sitting beside it.
		if ( ! empty( $nested ) ) {
			$date_query = ( 1 === count( $nested ) && count( $nested ) === count( $date_query ) )
				? (array) reset( $nested )
				: array();
		}

		if ( empty( $date_query ) || ! empty( array_diff( array_keys( $date_query ), self::SUPPORTED_ARGS ) ) ) {
			return null;
		}

		/**
		 * The clause, now known to carry supported arguments only.
		 *
		 * @var array<string, mixed> $date_query
		 */
		return $date_query;
	}

	/**
	 * Resolve `year`, `monthnum`/`month` and `day` into a calendar span.
	 *
	 * A year alone spans the year, a year and month span the month, and all
	 * three span the day. A month or day without a year has no anchor to hang
	 * on, so it resolves to nothing rather than guessing at the current year.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $clause The clause being read.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}|null The span, or null when there is no year.
	 */
	private static function resolve_calendar_span( array $clause ): ?array {
		if ( ! isset( $clause['year'] ) || ! is_numeric( $clause['year'] ) ) {
			return null;
		}

		$year  = (int) $clause['year'];
		$month = $clause['monthnum'] ?? ( $clause['month'] ?? null );
		$day   = $clause['day'] ?? null;

		if ( ! is_numeric( $month ) ) {
			$opens = sprintf( '%04d-01-01 00:00:00', $year );
			$spans = '+1 year';
		} elseif ( ! is_numeric( $day ) ) {
			$opens = sprintf( '%04d-%02d-01 00:00:00', $year, (int) $month );
			$spans = '+1 month';
		} else {
			$opens = sprintf( '%04d-%02d-%02d 00:00:00', $year, (int) $month, (int) $day );
			$spans = '+1 day';
		}

		$start = self::site_datetime( $opens );

		// A month of 13 or a day of 32 builds a string no calendar has.
		if ( null === $start ) {
			return null;
		}

		return array( $start, $start->modify( $spans )->modify( '-1 second' ) );
	}

	/**
	 * Read an `after` or `before` value into a site-local datetime.
	 *
	 * WordPress accepts either a string it can parse or an array of date parts.
	 * A value that names a day without naming a time covers the whole day, so
	 * it opens at the first second and closes at the last.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed $value    The value to read.
	 * @param bool  $end_of_day Whether a bare day should resolve to its last second.
	 *
	 * @return DateTimeImmutable|null The datetime, or null when the value could not be read.
	 */
	private static function to_site_datetime( $value, bool $end_of_day ): ?DateTimeImmutable {
		if ( is_array( $value ) ) {
			$value = sprintf(
				'%04d-%02d-%02d %02d:%02d:%02d',
				(int) ( $value['year'] ?? 0 ),
				(int) ( $value['month'] ?? ( $end_of_day ? 12 : 1 ) ),
				(int) ( $value['day'] ?? ( $end_of_day ? 31 : 1 ) ),
				(int) ( $value['hour'] ?? ( $end_of_day ? 23 : 0 ) ),
				(int) ( $value['minute'] ?? ( $end_of_day ? 59 : 0 ) ),
				(int) ( $value['second'] ?? ( $end_of_day ? 59 : 0 ) )
			);
		}

		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}

		$datetime = self::site_datetime( trim( $value ) );

		if ( null === $datetime ) {
			return null;
		}

		// A bare day covers the whole day, so a `before` lands on its last
		// second rather than on midnight, which would drop the day entirely.
		if ( $end_of_day && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( $value ) ) ) {
			$datetime = $datetime->modify( '+1 day' )->modify( '-1 second' );
		}

		return $datetime;
	}

	/**
	 * Build a datetime in the site's timezone.
	 *
	 * @since 0.36.0
	 *
	 * @param string $value A value `DateTimeImmutable` can parse.
	 *
	 * @return DateTimeImmutable|null The datetime, or null when the value could not be parsed.
	 */
	private static function site_datetime( string $value ): ?DateTimeImmutable {
		try {
			return new DateTimeImmutable( $value, wp_timezone() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Render a datetime as a string the events table can be compared against.
	 *
	 * The local columns hold each event's own wall clock with no zone attached,
	 * so a site-local boundary compares against them as it is. The GMT columns
	 * need the boundary shifted to UTC first.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable|null $datetime The datetime to render.
	 * @param bool                   $in_utc   Whether to shift it to UTC first.
	 *
	 * @return string|null The datetime, or null when there was nothing to render.
	 */
	private static function to_string( ?DateTimeImmutable $datetime, bool $in_utc ): ?string {
		if ( null === $datetime ) {
			return null;
		}

		if ( $in_utc ) {
			$datetime = $datetime->setTimezone( new DateTimeZone( 'UTC' ) );
		}

		return $datetime->format( self::DATETIME_FORMAT );
	}
}
