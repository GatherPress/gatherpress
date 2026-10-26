<?php
/**
 * The time grid of a calendar's day and week views.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Time_Grid.
 *
 * The hours a time grid shows, its rows, and the tracks of the events on it.
 *
 * @since TBD
 */
final class Time_Grid {

	/**
	 * First hour of the grid, from 0 to 23.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $start_hour;

	/**
	 * Hour the grid ends at, from 1 to 24 and after the first hour.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $end_hour;

	/**
	 * Hours in each labeled row.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $slot_hours;

	/**
	 * Minutes in each step of a row. Each row has four steps.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $quarter_mins;

	/**
	 * Number of steps, the tracks of the grid.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $total_quarters;

	/**
	 * Labels of the rows, like "08:00". Empty when the grid is off.
	 *
	 * @since TBD
	 * @var list<string>
	 */
	public readonly array $slots;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param bool $enabled    Whether the calendar shows the time grid.
	 * @param int  $start_hour First hour of the grid.
	 * @param int  $end_hour   Hour the grid ends at.
	 */
	public function __construct( public readonly bool $enabled = false, int $start_hour = 8, int $end_hour = 20 ) {
		$this->start_hour     = max( 0, min( 23, $start_hour ) );
		$this->end_hour       = max( $this->start_hour + 1, min( 24, $end_hour ) );
		$this->slot_hours     = $enabled ? self::get_slot_duration() : 1;
		$this->quarter_mins   = max( 1, (int) round( ( $this->slot_hours * 60 ) / 4 ) );
		$this->total_quarters = (int) ceil( ( ( $this->end_hour - $this->start_hour ) * 60 ) / $this->quarter_mins );
		$this->slots          = $enabled ? $this->build_slots() : array();
	}

	/**
	 * Creates the time grid of a calendar from its block attributes and query.
	 *
	 * The grid is on for day and week views of a query ordered by event date, when the
	 * calendar turns it on.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param array<string, mixed> $query      Query block context.
	 *
	 * @return self The time grid.
	 */
	public static function from_attributes( array $attributes, array $query ): self {
		$view_type = $attributes['viewType'] ?? 'month';
		$order_by  = $query['orderBy'] ?? $query['orderby'] ?? '';

		if (
			! in_array( $view_type, array( 'day', 'week' ), true ) ||
			'datetime' !== $order_by ||
			empty( $attributes['enableTimeGrid'] )
		) {
			return new self();
		}

		$start_hour = $attributes['dayStartHour'] ?? 8;
		$end_hour   = $attributes['dayEndHour'] ?? 20;

		return new self(
			true,
			is_numeric( $start_hour ) ? (int) $start_hour : 8,
			is_numeric( $end_hour ) ? (int) $end_hour : 20
		);
	}

	/**
	 * Retrieves the hours in each labeled row of the time grid.
	 *
	 * The editor preview reads the same value from the editor settings.
	 *
	 * @since TBD
	 *
	 * @return int Hours per row, 1 or more.
	 */
	public static function get_slot_duration(): int {
		/**
		 * Filters the hours in each labeled row of the time grid, to make a long day take less height.
		 *
		 * Defaults to 1. Each row has four steps. An event starts at the step at or before its start
		 * time and ends at the step at or after its end time: 15-minute steps for 1-hour rows, 30-minute
		 * steps for 2-hour rows. Values below 1 become 1.
		 *
		 * @since TBD
		 *
		 * @param int $slot_hours Hours per slot.
		 *
		 * @return int Hours per slot.
		 */
		return max( 1, (int) apply_filters( 'gatherpress_calendar_time_slot_duration', 1 ) );
	}

	/**
	 * Builds the labels of the rows.
	 *
	 * @since TBD
	 *
	 * @return list<string> Row labels.
	 */
	private function build_slots(): array {
		$slots = array();

		for ( $hour = $this->start_hour; $hour < $this->end_hour; $hour += $this->slot_hours ) {
			$slots[] = sprintf( '%02d:00', $hour );
		}

		return $slots;
	}

	/**
	 * Gets the tracks a post takes on the grid.
	 *
	 * A post outside the hours of the grid goes to the nearest edge: a post before the first
	 * hour starts on the first track, and a post at or after the last hour gets the last
	 * track. So no post shows below the grid.
	 *
	 * @since TBD
	 *
	 * @param Post_Dates $post_dates Dates of the post.
	 *
	 * @return array{row_start: int, row_end: int} First track, and the track after the last one.
	 */
	public function get_tracks( Post_Dates $post_dates ): array {
		$grid_start = $this->start_hour * 60;
		$grid_end   = $this->end_hour * 60;
		$start      = $this->round_to_step( max( $grid_start, $post_dates->start_minute ), false );
		$end        = $this->round_to_step( min( $grid_end, $post_dates->end_minute ), true );

		if ( $end <= $start ) {
			$end = $start + $this->quarter_mins;
		}

		$start_track = min( $this->total_quarters, intdiv( $start - $grid_start, $this->quarter_mins ) + 1 );
		$end_track   = intdiv( $end - $grid_start, $this->quarter_mins ) + 1;

		return array(
			'row_start' => $start_track,
			'row_end'   => min( $this->total_quarters + 1, max( $start_track + 1, $end_track ) ),
		);
	}

	/**
	 * Rounds minutes to a step of the grid.
	 *
	 * The steps count from the first minute of the grid. With 3-hour rows a step is 45 minutes,
	 * and a grid from 08:00 has steps at 08:45 and 09:30, not at 09:00.
	 *
	 * @since TBD
	 *
	 * @param int  $minutes Minutes past midnight.
	 * @param bool $is_end  Whether to round up, for an end, so a post never has zero length.
	 *
	 * @return int Rounded minutes.
	 */
	private function round_to_step( int $minutes, bool $is_end ): int {
		$origin = $this->start_hour * 60;
		$steps  = ( $minutes - $origin ) / $this->quarter_mins;

		return $origin + (int) ( ( $is_end ? ceil( $steps ) : floor( $steps ) ) * $this->quarter_mins );
	}
}
