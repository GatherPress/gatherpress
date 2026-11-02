<?php
/**
 * Organizes posts into the days and slots of a calendar.
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
 * Class Post_Organizer.
 *
 * Computes the multi-day spans of posts and organizes them by date and slot.
 *
 * @since TBD
 *
 * @phpstan-type EventSpan array{
 *     dates: Post_Dates,
 *     start: DateTimeImmutable,
 *     total_days: int,
 *     first_day: int,
 *     shown_days: int
 * }
 * @phpstan-type PostsByDate array<string, list<array<string, mixed>>>
 */
final class Post_Organizer {

	/**
	 * Maximum number of days of one event that the calendar places.
	 *
	 * Twelve months, the largest view. It also stops invalid dates from exhausting memory.
	 *
	 * @since TBD
	 * @var int
	 */
	const MAX_EVENT_SPAN_DAYS = 366;

	/**
	 * First visible day, at midnight UTC.
	 *
	 * @since TBD
	 * @var DateTimeImmutable
	 */
	private readonly DateTimeImmutable $first_day;

	/**
	 * Last visible day, at midnight UTC.
	 *
	 * @since TBD
	 * @var DateTimeImmutable
	 */
	private readonly DateTimeImmutable $last_day;

	/**
	 * Slots taken on each date.
	 *
	 * @since TBD
	 * @var array<string, array<int, bool>>
	 */
	private array $occupied_slots = array();

	/**
	 * Slotted entries grouped by date.
	 *
	 * @since TBD
	 * @var PostsByDate
	 */
	private array $posts_by_date = array();

	/**
	 * Constructor.
	 *
	 * The dates of the range are already days in the site's timezone. They count in UTC, where
	 * every day has 24 hours, so a day where daylight saving time starts at midnight (for
	 * example in America/Santiago) still counts as one day.
	 *
	 * @since TBD
	 *
	 * @param Date_Range $date_range The date range of the calendar.
	 * @param Time_Grid  $time_grid  The time grid of the calendar.
	 */
	public function __construct( Date_Range $date_range, private readonly Time_Grid $time_grid ) {
		$this->first_day = new DateTimeImmutable( $date_range->start_date, new DateTimeZone( 'UTC' ) );
		$this->last_day  = new DateTimeImmutable( $date_range->end_date, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Organizes posts into slotted entries grouped by date.
	 *
	 * @since TBD
	 *
	 * @param int[] $post_ids Post IDs.
	 *
	 * @return PostsByDate Slotted calendar entries grouped by date.
	 */
	public function organize( array $post_ids ): array {
		$this->occupied_slots = array();
		$this->posts_by_date  = array();

		foreach ( $this->sort_spans( $this->get_spans( $post_ids ) ) as $span ) {
			$is_multi_day = $span['total_days'] > 1 || $span['dates']->is_all_day;

			// In time-grid mode, single-day events go on the time tracks.
			if ( $this->time_grid->enabled && ! $is_multi_day ) {
				$this->allocate_timed_entry( $span );
				continue;
			}

			$this->allocate_ribbon_entry( $span );
		}

		foreach ( $this->posts_by_date as $date_str => $day_entries ) {
			if ( $this->time_grid->enabled ) {
				$day_entries = $this->resolve_timed_overlaps( $day_entries );
			}

			// Slot order, so the reading and tab order match the rows on screen, as in the editor
			// (grid-builder.js). The sort is stable, so entries in the same slot keep their order.
			usort(
				$day_entries,
				static function ( array $a, array $b ): int {
					return $a['slot'] <=> $b['slot'];
				}
			);

			$this->posts_by_date[ $date_str ] = $day_entries;
		}

		return $this->posts_by_date;
	}

	/**
	 * Gets the visible spans of posts.
	 *
	 * @since TBD
	 *
	 * @param int[] $post_ids Post IDs.
	 *
	 * @return list<EventSpan> Spans of the posts with visible days.
	 */
	private function get_spans( array $post_ids ): array {
		$spans = array();

		foreach ( $post_ids as $post_id ) {
			$span = $this->get_span( new Post_Dates( $post_id ) );

			if ( null !== $span ) {
				$spans[] = $span;
			}
		}

		return $spans;
	}

	/**
	 * Gets the span of a post.
	 *
	 * `total_days` is the full length of the post. `first_day` (0-based) and `shown_days` give
	 * the part of the post inside the visible range, so an event that starts before the range
	 * still shows on the visible days.
	 *
	 * @since TBD
	 *
	 * @param Post_Dates $post_dates Dates of the post.
	 *
	 * @return EventSpan|null Null when a date is not valid or the post is outside the visible range.
	 */
	private function get_span( Post_Dates $post_dates ): ?array {
		$start = DateTimeImmutable::createFromFormat( '!Y-m-d', $post_dates->start_date, new DateTimeZone( 'UTC' ) );
		$end   = DateTimeImmutable::createFromFormat( '!Y-m-d', $post_dates->end_date, new DateTimeZone( 'UTC' ) );

		if ( ! $start || ! $end || $end < $this->first_day || $start > $this->last_day ) {
			return null;
		}

		$total_days = (int) $start->diff( $end )->days + 1;
		$first_day  = $start < $this->first_day ? (int) $start->diff( $this->first_day )->days : 0;
		$last_day   = $end > $this->last_day ? (int) $start->diff( $this->last_day )->days : $total_days - 1;

		return array(
			'dates'      => $post_dates,
			'start'      => $start,
			'total_days' => $total_days,
			'first_day'  => $first_day,
			'shown_days' => min( self::MAX_EVENT_SPAN_DAYS, $last_day - $first_day + 1 ),
		);
	}

	/**
	 * Sorts spans for slotting.
	 *
	 * Multi-day and all-day events first, then longer before shorter, then start date, then ID.
	 *
	 * @since TBD
	 *
	 * @param array<int, EventSpan> $spans Spans of the posts.
	 *
	 * @return list<EventSpan> Sorted spans.
	 */
	private function sort_spans( array $spans ): array {
		usort(
			$spans,
			static function ( array $a, array $b ): int {
				$a_promoted = $a['total_days'] > 1 || $a['dates']->is_all_day;
				$b_promoted = $b['total_days'] > 1 || $b['dates']->is_all_day;

				return array( $b_promoted, $b['total_days'], $a['dates']->start_date, $a['dates']->post_id )
					<=> array( $a_promoted, $a['total_days'], $b['dates']->start_date, $b['dates']->post_id );
			}
		);

		return $spans;
	}

	/**
	 * Finds the lowest 0-indexed vertical slot free across all dates of a span.
	 *
	 * @since TBD
	 *
	 * @param string[] $dates Dates of the span.
	 *
	 * @return int Lowest available slot index.
	 */
	private function find_available_slot( array $dates ): int {
		$slot = 0;

		while ( $this->is_slot_taken( $slot, $dates ) ) {
			++$slot;
		}

		return $slot;
	}

	/**
	 * Checks whether a slot is taken on any of the given dates.
	 *
	 * @since TBD
	 *
	 * @param int      $slot  Slot index.
	 * @param string[] $dates Dates of the span.
	 *
	 * @return bool True when another post holds the slot on one of the dates.
	 */
	private function is_slot_taken( int $slot, array $dates ): bool {
		foreach ( $dates as $date_str ) {
			if ( isset( $this->occupied_slots[ $date_str ][ $slot ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Classifies the span position for a day within a multi-day event.
	 *
	 * @since TBD
	 *
	 * @param int $day_index  1-based day index.
	 * @param int $total_days Total days of the event.
	 *
	 * @return string 'single', 'start', 'middle', or 'end'.
	 */
	private function get_span_type( int $day_index, int $total_days ): string {
		return match ( true ) {
			$total_days <= 1           => 'single',
			1 === $day_index           => 'start',
			$total_days === $day_index => 'end',
			default                    => 'middle',
		};
	}

	/**
	 * Allocates a single-day timed event to the time grid.
	 *
	 * Posts without event dates have no time, so the time grid leaves them out.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span Span of the post.
	 * @phpstan-param EventSpan $span
	 *
	 * @return void
	 */
	private function allocate_timed_entry( array $span ): void {
		if ( ! $span['dates']->is_event ) {
			return;
		}

		$tracks   = $this->time_grid->get_tracks( $span['dates'] );
		$date_str = $span['dates']->start_date;
		$slot     = $this->find_available_slot( array( $date_str ) );

		$this->occupied_slots[ $date_str ][ $slot ] = true;
		$this->posts_by_date[ $date_str ][]         = array(
			'id'             => $span['dates']->post_id,
			'span'           => 'single',
			'day_index'      => 1,
			'total_days'     => 1,
			'is_all_day'     => false,
			'is_timed'       => true,
			'time_row_start' => $tracks['row_start'],
			'time_row_end'   => $tracks['row_end'],
			'slot'           => $slot,
		);
	}

	/**
	 * Allocates ribbon slots for an all-day or multi-day event.
	 *
	 * Only the visible days get an entry. Each entry keeps its day number in the full event.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $span Span of the post.
	 * @phpstan-param EventSpan $span
	 *
	 * @return void
	 */
	private function allocate_ribbon_entry( array $span ): void {
		$first_day = $span['first_day'];
		$dates     = array();

		for ( $i = 0; $i < $span['shown_days']; $i++ ) {
			$date    = $span['start']->modify( sprintf( '+%d days', $first_day + $i ) );
			$dates[] = $date->format( Date_Range::DATE_FORMAT );
		}

		$slot = $this->find_available_slot( $dates );

		foreach ( $dates as $i => $date_str ) {
			$day_index = $first_day + $i + 1;

			$this->occupied_slots[ $date_str ][ $slot ] = true;
			$this->posts_by_date[ $date_str ][]         = array(
				'id'         => $span['dates']->post_id,
				'span'       => $this->get_span_type( $day_index, $span['total_days'] ),
				'day_index'  => $day_index,
				'total_days' => $span['total_days'],
				'is_all_day' => $span['dates']->is_all_day,
				'is_timed'   => false,
				'slot'       => $slot,
			);
		}
	}

	/**
	 * Resolves concurrent overlapping columns for timed entries in a single day.
	 *
	 * Timed entries that overlap, directly or through other entries, form a group. Each entry
	 * goes in the first column of its group that is free at its start time. Each entry gets
	 * the number of columns of its group and its column, so no two overlapping entries share
	 * a column and all entries of a group have the same width.
	 *
	 * @since TBD
	 *
	 * @param list<array<string, mixed>> $day_entries Day entries list.
	 *
	 * @return list<array<string, mixed>> Day entries with overlap data on timed entries.
	 */
	private function resolve_timed_overlaps( array $day_entries ): array {
		$timed = array_filter(
			$day_entries,
			static function ( array $entry ): bool {
				return ! empty( $entry['is_timed'] );
			}
		);

		// Order by start track, then by end track. The keys keep the positions in $day_entries.
		uasort(
			$timed,
			static function ( array $a, array $b ): int {
				return array( $a['time_row_start'], $a['time_row_end'] )
					<=> array( $b['time_row_start'], $b['time_row_end'] );
			}
		);

		$group       = array();
		$column_ends = array();
		$group_end   = 0;

		foreach ( $timed as $idx => $entry ) {
			$row_start = (int) $entry['time_row_start'];
			$row_end   = (int) $entry['time_row_end'];

			// An entry that starts after every entry of the group ends starts a new group.
			if ( $row_start >= $group_end ) {
				$day_entries = $this->assign_overlap_group( $day_entries, $group, count( $column_ends ) );
				$group       = array();
				$column_ends = array();
			}

			$column = 0;

			while ( isset( $column_ends[ $column ] ) && $column_ends[ $column ] > $row_start ) {
				++$column;
			}

			$column_ends[ $column ] = $row_end;
			$group[ $idx ]          = $column;
			$group_end              = max( $group_end, $row_end );
		}

		$day_entries = $this->assign_overlap_group( $day_entries, $group, count( $column_ends ) );

		return array_values( $day_entries );
	}

	/**
	 * Writes the overlap column data of one group of timed entries.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $day_entries Day entries list.
	 * @param array<int, int>                  $group       Map of entry positions to their columns.
	 * @param int                              $columns     Number of columns the group uses.
	 *
	 * @return array<int, array<string, mixed>> Day entries with overlap data on the entries of the group.
	 */
	private function assign_overlap_group( array $day_entries, array $group, int $columns ): array {
		foreach ( $group as $idx => $column ) {
			$day_entries[ $idx ]['overlap_total'] = $columns;
			$day_entries[ $idx ]['overlap_index'] = $column;
		}

		return $day_entries;
	}
}
