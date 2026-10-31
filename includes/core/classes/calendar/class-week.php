<?php
/**
 * The days of the week of a calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Week.
 *
 * The first day of the week, the weekend days, and the days of the week a calendar shows.
 *
 * @since TBD
 */
final class Week {

	/**
	 * Lowercase English slugs of the days of the week, from Sunday (0) to Saturday (6).
	 *
	 * @since TBD
	 * @var list<string>
	 */
	const DAY_SLUGS = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );

	/**
	 * A Sunday at noon UTC, used to name the days of the week.
	 *
	 * 2024-01-07 12:00:00 UTC.
	 *
	 * @since TBD
	 * @var int
	 */
	const REFERENCE_SUNDAY = 1704628800;

	/**
	 * The first day of the week (0 = Sunday, 1 = Monday, etc.).
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $start_of_week;

	/**
	 * The days of the week that are weekend days (0 = Sunday, ..., 6 = Saturday).
	 *
	 * @since TBD
	 * @var list<int>
	 */
	public readonly array $weekend_days;

	/**
	 * Constructor.
	 *
	 * Reads the first day of the week from the site settings, and the weekend days from the
	 * `gatherpress_calendar_weekend_days` filter.
	 *
	 * @since TBD
	 *
	 * @param bool $show_weekends Whether the calendar shows weekend days.
	 */
	public function __construct( public readonly bool $show_weekends = true ) {
		$start_of_week = get_option( 'start_of_week', 0 );

		$this->start_of_week = is_numeric( $start_of_week ) ? (int) $start_of_week : 0;
		$this->weekend_days  = $this->resolve_weekend_days();
	}

	/**
	 * Creates the week of a calendar from its block attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 *
	 * @return self The week, showing weekend days unless `showWeekends` is false.
	 */
	public static function from_attributes( array $attributes ): self {
		$show_weekends = $attributes['showWeekends'] ?? true;

		return new self( false !== $show_weekends && 'false' !== $show_weekends );
	}

	/**
	 * Retrieves the days of the week considered weekend days.
	 *
	 * This filter allows sites to adapt the calendar grid, classes, and weekend-hiding toggle
	 * to match local cultural and regional norms. Filtered values that are not days 0 to 6, and
	 * repeated days, are dropped, so the column count matches the days that are hidden.
	 *
	 * @since TBD
	 *
	 * @return list<int> Day-of-week integers (0 = Sunday, 1 = Monday, ..., 6 = Saturday).
	 */
	private function resolve_weekend_days(): array {
		/**
		 * Filters which days of the week are weekend days, for a site in a region with a different weekend.
		 *
		 * Defaults to Sunday (0) and Saturday (6). Weekend days get the `gatherpress--is-weekend` class, and the
		 * calendar hides them when Show Weekends is off.
		 *
		 * Across different cultures, religions, and regions, the weekend days vary significantly:
		 * - Western standard: Saturday (6) and Sunday (0).
		 * - Middle East & North Africa (e.g., Egypt, Saudi Arabia, Jordan): Friday (5) and Saturday (6).
		 * - Israel: Friday (5) and Saturday (6) (observing Shabbat).
		 * - Iran: Friday (5) as the sole official weekend day.
		 * - Nepal: Saturday (6) as the sole official weekend day.
		 *
		 * @since TBD
		 *
		 * @param int[] $weekend_days Array of day numbers where 0 = Sunday, 1 = Monday, ..., 6 = Saturday.
		 *
		 * @return int[] Day numbers. Values that are not 0 to 6, and repeated days, are dropped.
		 */
		$weekend_days = apply_filters( 'gatherpress_calendar_weekend_days', array( 0, 6 ) );
		$days         = array();
		$day_range    = array(
			'options' => array(
				'min_range' => 0,
				'max_range' => 6,
			),
		);

		foreach ( (array) $weekend_days as $day ) {
			$valid_day = filter_var( $day, FILTER_VALIDATE_INT, $day_range );

			if ( false !== $valid_day ) {
				$days[] = $valid_day;
			}
		}

		return array_values( array_unique( $days ) );
	}

	/**
	 * Checks whether a day of the week is a weekend day.
	 *
	 * @since TBD
	 *
	 * @param int $day_of_week Day of week (0 = Sunday through 6 = Saturday).
	 *
	 * @return bool True if weekend, false otherwise.
	 */
	public function is_weekend( int $day_of_week ): bool {
		return in_array( $day_of_week, $this->weekend_days, true );
	}

	/**
	 * Gets the days of the week the calendar shows, in order from the first day of the week.
	 *
	 * @since TBD
	 *
	 * @return list<int> Days of the week (0 = Sunday through 6 = Saturday).
	 */
	public function get_days(): array {
		$days = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$day_of_week = ( $this->start_of_week + $i ) % 7;

			if ( $this->show_weekends || ! $this->is_weekend( $day_of_week ) ) {
				$days[] = $day_of_week;
			}
		}

		return $days;
	}

	/**
	 * Gets the translated short names of the days of the week the calendar shows.
	 *
	 * @since TBD
	 *
	 * @return list<string> Translated day names, like "Mon".
	 */
	public function get_day_names(): array {
		$day_names = array();

		foreach ( $this->get_days() as $day_of_week ) {
			$day_timestamp = self::REFERENCE_SUNDAY + ( $day_of_week * DAY_IN_SECONDS );
			$day_names[]   = (string) wp_date( 'D', $day_timestamp, new DateTimeZone( 'UTC' ) );
		}

		return $day_names;
	}

	/**
	 * Gets the first day of the week of a date.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date Target date.
	 *
	 * @return DateTimeImmutable First day of the week at 00:00:00.
	 */
	public function get_start( DateTimeImmutable $date ): DateTimeImmutable {
		$diff = ( (int) $date->format( 'w' ) - $this->start_of_week + 7 ) % 7;

		return $date->modify( "-{$diff} days" )->setTime( 0, 0, 0 );
	}

	/**
	 * Gets the first and last day the calendar shows of one or more consecutive weeks.
	 *
	 * Without a day to show, when every day is a hidden weekend day, the weeks run from their
	 * first to their last day.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date  A date in the first week.
	 * @param int               $count Number of weeks.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} First day at 00:00:00 and last day at 23:59:59.
	 */
	public function get_bounds( DateTimeImmutable $date, int $count ): array {
		$week_start   = $this->get_start( $date );
		$total_days   = $count * 7;
		$visible_days = array();

		for ( $i = 0; $i < $total_days; $i++ ) {
			$day = $week_start->modify( "+{$i} days" );

			if ( $this->show_weekends || ! $this->is_weekend( (int) $day->format( 'w' ) ) ) {
				$visible_days[] = $day;
			}
		}

		$first = $visible_days[0] ?? $week_start;
		$last  = end( $visible_days );
		$last  = false !== $last ? $last : $week_start->modify( sprintf( '+%d days', $total_days - 1 ) );

		return array( $first->setTime( 0, 0, 0 ), $last->setTime( 23, 59, 59 ) );
	}

	/**
	 * Gets the ISO 8601 week number of a calendar row.
	 *
	 * A row that does not start on Monday spans two ISO weeks. Its fourth day
	 * is always in the ISO week that holds most of the row, so that one is used.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $week_days Day entries of the row.
	 *
	 * @return int Week number, or 0 when the row has no dated day.
	 */
	public function get_number( array $week_days ): int {
		foreach ( $week_days as $day ) {
			if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || '' === $day['date'] ) {
				continue;
			}

			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $day['date'], wp_timezone() );

			if ( ! $date instanceof DateTimeImmutable ) {
				return 0;
			}

			return (int) $this->get_start( $date )->modify( '+3 days' )->format( 'W' );
		}

		return 0;
	}
}
