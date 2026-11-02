<?php
/**
 * Builds the grid structure of a calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

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
 * @phpstan-type DayPosts list<array<string, mixed>>|list<int>
 * @phpstan-type PostsByDate array<string, DayPosts>
 * @phpstan-type Weeks list<array<int, array<string, mixed>>>
 * @phpstan-type CalendarUnit array{caption: string, day_names: list<string>, weeks: Weeks}
 */
final class Structure_Builder {

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param Date_Range $date_range The date range of the calendar.
	 */
	public function __construct( private readonly Date_Range $date_range ) {}

	/**
	 * Build complete calendar structure.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
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
	public function build( array $posts_by_date ): array {
		$date_range = $this->date_range;

		$units = match ( $date_range->view_type ) {
			'day'   => array(
				array(
					'caption'   => $date_range->heading,
					'day_names' => $date_range->get_day_names(),
					'weeks'     => $this->build_consecutive_days( $posts_by_date ),
				),
			),
			'week'  => array(
				array(
					'caption'   => $date_range->heading,
					'day_names' => $date_range->get_day_names(),
					'weeks'     => $this->build_consecutive_weeks( $posts_by_date ),
				),
			),
			default => $this->build_month_units( $posts_by_date ),
		};

		return array(
			'heading'    => $date_range->heading,
			'day_names'  => $units[0]['day_names'],
			'weeks'      => $units[0]['weeks'],
			'view_type'  => $date_range->view_type,
			'unit_count' => max( 1, $date_range->unit_count ),
			'units'      => $units,
		);
	}

	/**
	 * Build one grid per month of a month view.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return non-empty-list<CalendarUnit>
	 */
	private function build_month_units( array $posts_by_date ): array {
		$units = array();

		// Each month grid gets its own name, like "October 2026".
		foreach ( $this->date_range->get_months() as $month ) {
			$units[] = array(
				'caption'   => $month->heading,
				'day_names' => $month->get_day_names(),
				'weeks'     => $this->build_month_weeks( $month->start, $posts_by_date ),
			);
		}

		return $units;
	}

	/**
	 * Factory to create an active day entry.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date  The day.
	 * @param array<mixed>      $posts Associated posts or post IDs.
	 * @phpstan-param DayPosts $posts
	 *
	 * @return array<string, mixed> Day entry.
	 */
	private function create_day_entry( DateTimeImmutable $date, array $posts = array() ): array {
		$day_of_week = (int) $date->format( 'w' );

		return array(
			'day'       => (int) $date->format( 'j' ),
			'date'      => $date->format( Date_Range::DATE_FORMAT ),
			'posts'     => $posts,
			'isEmpty'   => false,
			'dayOfWeek' => $day_of_week,
			'weekday'   => Week::DAY_SLUGS[ $day_of_week ],
			'isWeekend' => $this->date_range->week->is_weekend( $day_of_week ),
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
	private function create_empty_day_entry( int $day_of_week ): array {
		return array(
			'isEmpty'   => true,
			'posts'     => array(),
			'dayOfWeek' => $day_of_week,
			'weekday'   => Week::DAY_SLUGS[ $day_of_week ],
			'isWeekend' => $this->date_range->week->is_weekend( $day_of_week ),
		);
	}

	/**
	 * Build weeks array for a full month (with leading/trailing empty cells).
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable    $month_start   First day of the month.
	 * @param array<string, mixed> $posts_by_date Posts by date.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private function build_month_weeks( DateTimeImmutable $month_start, array $posts_by_date ): array {
		$columns       = $this->date_range->week->get_days();
		$days_per_week = count( $columns );
		$days_in_month = (int) $month_start->format( 't' );
		$weeks         = array();
		$current_week  = array();

		for ( $i = 0; $i < $days_in_month; $i++ ) {
			$date        = $month_start->modify( "+{$i} days" );
			$day_of_week = (int) $date->format( 'w' );

			if ( ! in_array( $day_of_week, $columns, true ) ) {
				continue;
			}

			// Pad the first week with empty cells up to the column of the first visible day.
			if ( empty( $weeks ) && empty( $current_week ) ) {
				$leading = (int) array_search( $day_of_week, $columns, true );

				for ( $j = 0; $j < $leading; $j++ ) {
					$current_week[] = $this->create_empty_day_entry( $columns[ $j ] );
				}
			}

			$current_week[] = $this->create_day_entry(
				$date,
				$posts_by_date[ $date->format( Date_Range::DATE_FORMAT ) ] ?? array()
			);

			if ( count( $current_week ) === $days_per_week ) {
				$weeks[]      = $current_week;
				$current_week = array();
			}
		}

		if ( ! empty( $current_week ) ) {
			for ( $j = count( $current_week ); $j < $days_per_week; $j++ ) {
				$current_week[] = $this->create_empty_day_entry( $columns[ $j ] );
			}

			$weeks[] = $current_week;
		}

		return $weeks;
	}

	/**
	 * Build weeks array for a week view (continuous days, no isEmpty padding).
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $posts_by_date Posts organized by date.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private function build_consecutive_weeks( array $posts_by_date ): array {
		$week       = $this->date_range->week;
		$week_start = $this->date_range->get_week_start();
		$weeks      = array();

		for ( $w = 0; $w < $this->date_range->unit_count; $w++ ) {
			$week_days = array();

			for ( $i = 0; $i < 7; $i++ ) {
				$date = $week_start->modify( sprintf( '+%d days', ( $w * 7 ) + $i ) );

				if ( ! $week->show_weekends && $week->is_weekend( (int) $date->format( 'w' ) ) ) {
					continue;
				}

				$week_days[] = $this->create_day_entry(
					$date,
					$posts_by_date[ $date->format( Date_Range::DATE_FORMAT ) ] ?? array()
				);
			}

			$weeks[] = $week_days;
		}

		return $weeks;
	}

	/**
	 * Build the days of a day view.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $posts_by_date Posts by date.
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return Weeks
	 */
	private function build_consecutive_days( array $posts_by_date ): array {
		$days = array();

		for ( $i = 0; $i < $this->date_range->unit_count; $i++ ) {
			$date   = $this->date_range->start->modify( "+{$i} days" );
			$days[] = $this->create_day_entry(
				$date,
				$posts_by_date[ $date->format( Date_Range::DATE_FORMAT ) ] ?? array()
			);
		}

		return array( $days );
	}
}
