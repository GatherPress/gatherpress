<?php
/**
 * Builds the grid structure of the Calendar blocks.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;

/**
 * Class Structure_Builder.
 *
 * Generates the calendar grid structure for month, week, or day view.
 *
 * @since TBD
 *
 * @phpstan-import-type DateRange from Date_Calculator
 * @phpstan-type DayPosts list<array<string, mixed>>|list<int>
 * @phpstan-type PostsByDate array<string, DayPosts>
 * @phpstan-type Weeks list<array<int, array<string, mixed>>>
 * @phpstan-type CalendarUnit array{caption: string, day_names: list<string>, weeks: Weeks}
 */
final class Structure_Builder {

	/**
	 * Build complete calendar structure.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $date_range    Date range array.
	 * @param int                  $start_of_week Start of week setting (0-6).
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
	 * @param bool                 $show_weekends Whether to include weekend days.
	 * @phpstan-param DateRange $date_range
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return array{
	 *     heading: string,
	 *     day_names: list<string>,
	 *     weeks: Weeks,
	 *     view_type: string,
	 *     unit_count: int,
	 *     units: non-empty-list<CalendarUnit>
	 * }
	 */
	public static function build_structure(
		array $date_range,
		int $start_of_week,
		array $posts_by_date,
		bool $show_weekends = true
	): array {
		$view_type  = $date_range['view_type'];
		$unit_count = max( 1, $date_range['unit_count'] );
		$start_date = $date_range['start_date_obj'];

		if ( 'day' === $view_type ) {
			$units = array(
				array(
					'caption'   => $date_range['heading'],
					'day_names' => Date_Calculator::get_view_day_names(
						$view_type,
						$start_date,
						$start_of_week,
						$show_weekends,
						$unit_count
					),
					'weeks'     => self::build_consecutive_days( $start_date, $unit_count, $posts_by_date ),
				),
			);
		} elseif ( 'week' === $view_type ) {
			$units = array(
				array(
					'caption'   => $date_range['heading'],
					'day_names' => Date_Calculator::get_day_names( $start_of_week, $show_weekends ),
					'weeks'     => self::build_consecutive_weeks(
						$date_range['raw_week_start'],
						$unit_count,
						$posts_by_date,
						$show_weekends
					),
				),
			);
		} else {
			$units = self::build_month_units(
				$start_date,
				$unit_count,
				$start_of_week,
				$posts_by_date,
				$show_weekends
			);
		}

		return array(
			'heading'    => $date_range['heading'],
			'day_names'  => $units[0]['day_names'],
			'weeks'      => $units[0]['weeks'],
			'view_type'  => $view_type,
			'unit_count' => $unit_count,
			'units'      => $units,
		);
	}

	/**
	 * Build one grid per month of a month view.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable    $start_date    First day of the first month.
	 * @param int                  $unit_count    Number of months, at least 1.
	 * @param int                  $start_of_week Start of week setting (0-6).
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
	 * @param bool                 $show_weekends Whether to include weekend days.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return non-empty-list<CalendarUnit>
	 */
	private static function build_month_units(
		DateTimeImmutable $start_date,
		int $unit_count,
		int $start_of_week,
		array $posts_by_date,
		bool $show_weekends
	): array {
		$units = array();
		$count = max( 1, $unit_count );

		for ( $i = 0; $i < $count; $i++ ) {
			$month_date = $start_date->modify( "+{$i} months" );

			// Each month grid gets its own name, like "October 2026".
			$units[] = array(
				'caption'   => Date_Calculator::format_heading( 'month', $month_date, $month_date ),
				'day_names' => Date_Calculator::get_day_names( $start_of_week, $show_weekends ),
				'weeks'     => self::build_month_weeks(
					(int) $month_date->format( 'Y' ),
					(int) $month_date->format( 'n' ),
					$start_of_week,
					(int) $month_date->format( 't' ),
					$posts_by_date,
					$show_weekends
				),
			);
		}

		return $units;
	}

	/**
	 * Factory to create an active day entry.
	 *
	 * @since TBD
	 *
	 * @param int          $day         Day of month.
	 * @param string       $date_str    YYYY-MM-DD date string.
	 * @param int          $day_of_week Day of week (0-6).
	 * @param array<mixed> $posts       Associated posts or post IDs.
	 * @phpstan-param DayPosts $posts
	 *
	 * @return array<string, mixed> Day entry.
	 */
	private static function create_day_entry(
		int $day,
		string $date_str,
		int $day_of_week,
		array $posts = array()
	): array {
		return array(
			'day'       => $day,
			'date'      => $date_str,
			'posts'     => $posts,
			'isEmpty'   => false,
			'dayOfWeek' => $day_of_week,
			'weekday'   => Date_Calculator::get_weekday_slug( $day_of_week ),
			'isWeekend' => Date_Calculator::is_weekend_day( $day_of_week ),
		);
	}

	/**
	 * Factory to create an empty padded day entry.
	 *
	 * @since TBD
	 *
	 * @param int $day_of_week Day of week (0-6).
	 *
	 * @return array<string, mixed> Empty day entry.
	 */
	private static function create_empty_day_entry( int $day_of_week ): array {
		return array(
			'isEmpty'   => true,
			'posts'     => array(),
			'dayOfWeek' => $day_of_week,
			'weekday'   => Date_Calculator::get_weekday_slug( $day_of_week ),
			'isWeekend' => Date_Calculator::is_weekend_day( $day_of_week ),
		);
	}

	/**
	 * Resolves active days of week based on start of week and weekend toggle.
	 *
	 * @since TBD
	 *
	 * @param int  $start_of_week Start of week (0-6).
	 * @param bool $show_weekends Weekend visibility.
	 *
	 * @return list<int> Active day-of-week indices.
	 */
	private static function get_active_week_columns( int $start_of_week, bool $show_weekends ): array {
		$columns = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$dow = ( $start_of_week + $i ) % 7;

			if ( $show_weekends || ! Date_Calculator::is_weekend_day( $dow ) ) {
				$columns[] = $dow;
			}
		}

		return $columns;
	}

	/**
	 * Build weeks array for a full month (with leading/trailing empty cells).
	 *
	 * @since TBD
	 *
	 * @param int                  $year          Target year.
	 * @param int                  $month         Target month.
	 * @param int                  $start_of_week Start of week setting (0-6).
	 * @param int                  $days_in_month Number of days in month.
	 * @param array<string, mixed> $posts_by_date Posts by date.
	 * @param bool                 $show_weekends Whether to include weekend days.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private static function build_month_weeks(
		int $year,
		int $month,
		int $start_of_week,
		int $days_in_month,
		array $posts_by_date,
		bool $show_weekends = true
	): array {
		$columns       = self::get_active_week_columns( $start_of_week, $show_weekends );
		$days_per_week = count( $columns );
		$weeks         = array();
		$current_week  = array();

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$day_of_week = (int) gmdate( 'w', (int) gmmktime( 0, 0, 0, $month, $day, $year ) );

			if ( ! in_array( $day_of_week, $columns, true ) ) {
				continue;
			}

			// Pad the first week with empty cells up to the column of the first visible day.
			if ( empty( $weeks ) && empty( $current_week ) ) {
				$leading = (int) array_search( $day_of_week, $columns, true );

				for ( $i = 0; $i < $leading; $i++ ) {
					$current_week[] = self::create_empty_day_entry( $columns[ $i ] );
				}
			}

			$date_str       = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$day_posts      = $posts_by_date[ $date_str ] ?? array();
			$current_week[] = self::create_day_entry( $day, $date_str, $day_of_week, $day_posts );

			if ( count( $current_week ) === $days_per_week ) {
				$weeks[]      = $current_week;
				$current_week = array();
			}
		}

		if ( ! empty( $current_week ) ) {
			for ( $i = count( $current_week ); $i < $days_per_week; $i++ ) {
				$current_week[] = self::create_empty_day_entry( $columns[ $i ] );
			}

			$weeks[] = $current_week;
		}

		return $weeks;
	}

	/**
	 * Build weeks array for a single week view (continuous days, no isEmpty padding).
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable    $week_start    Start of the week.
	 * @param int                  $unit_count    Number of units to show.
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
	 * @param bool                 $show_weekends Whether to include weekend days.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private static function build_consecutive_weeks(
		DateTimeImmutable $week_start,
		int $unit_count,
		array $posts_by_date,
		bool $show_weekends = true
	): array {
		$weeks = array();

		for ( $w = 0; $w < $unit_count; $w++ ) {
			$current_week_start = $week_start->modify( sprintf( '+%d weeks', $w ) );
			$week_days          = array();

			for ( $i = 0; $i < 7; $i++ ) {
				$day_obj     = $current_week_start->modify( "+{$i} days" );
				$day_of_week = (int) $day_obj->format( 'w' );

				if ( ! $show_weekends && Date_Calculator::is_weekend_day( $day_of_week ) ) {
					continue;
				}

				$date_str    = $day_obj->format( Date_Calculator::DATE_FORMAT );
				$week_days[] = self::create_day_entry(
					(int) $day_obj->format( 'j' ),
					$date_str,
					$day_of_week,
					$posts_by_date[ $date_str ] ?? array()
				);
			}

			$weeks[] = $week_days;
		}

		return $weeks;
	}

	/**
	 * Build single day view.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable    $start_day_obj Target day.
	 * @param int                  $unit_count    Number of units to show.
	 * @param array<string, mixed> $posts_by_date Posts by date.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private static function build_consecutive_days(
		DateTimeImmutable $start_day_obj,
		int $unit_count,
		array $posts_by_date
	): array {
		$days = array();

		for ( $i = 0; $i < $unit_count; $i++ ) {
			$day_obj  = $start_day_obj->modify( "+{$i} days" );
			$date_str = $day_obj->format( Date_Calculator::DATE_FORMAT );
			$days[]   = self::create_day_entry(
				(int) $day_obj->format( 'j' ),
				$date_str,
				(int) $day_obj->format( 'w' ),
				$posts_by_date[ $date_str ] ?? array()
			);
		}

		return array( $days );
	}
}
