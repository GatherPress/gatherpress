<?php
/**
 * Organizes posts into the days and slots of the Calendar blocks.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use GatherPress\Core\Event;
use WP_Post;
use WP_Query;

/**
 * Class Post_Organizer.
 *
 * Fetches posts, computes multi-day spans, and organizes them by date and slot.
 *
 * @since TBD
 *
 * @phpstan-type EventSpan array{
 *     id: int,
 *     start_obj: DateTimeImmutable,
 *     start_date: string,
 *     total_days: int,
 *     first_day: int,
 *     shown_days: int,
 *     is_all_day: bool
 * }
 * @phpstan-type VisibleRange array{start: DateTimeImmutable, end: DateTimeImmutable}
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
	 * Public entry point: queries posts and organizes them by date and time grid.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_args  WP_Query arguments.
	 * @param array<string, mixed> $grid_config Time grid configuration.
	 *
	 * @return PostsByDate Slotted calendar entries grouped by date.
	 */
	public static function organize_posts_by_date( array $query_args, array $grid_config = array() ): array {
		return self::organize_posts(
			self::fetch_post_ids_from_query( $query_args ),
			$grid_config,
			self::get_visible_range( $query_args )
		);
	}

	/**
	 * Reads the visible date range from calendar query arguments.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments.
	 *
	 * @return VisibleRange|null The first and last visible day, or null without a valid range.
	 */
	public static function get_visible_range( array $query_args ): ?array {
		$start = $query_args[ Query_Integration::CALENDAR_QUERY_START_DATE ] ?? null;
		$end   = $query_args[ Query_Integration::CALENDAR_QUERY_END_DATE ] ?? null;

		if ( ! is_string( $start ) || ! is_string( $end ) ) {
			return null;
		}

		$start_obj = DateTimeImmutable::createFromFormat( '!Y-m-d', $start, wp_timezone() );
		$end_obj   = DateTimeImmutable::createFromFormat( '!Y-m-d', $end, wp_timezone() );

		if ( ! $start_obj instanceof DateTimeImmutable || ! $end_obj instanceof DateTimeImmutable ) {
			return null;
		}

		if ( $end_obj < $start_obj ) {
			return null;
		}

		return array(
			'start' => $start_obj,
			'end'   => $end_obj,
		);
	}

	/**
	 * Transforms an array of post IDs into slotted date-grouped entries.
	 *
	 * @since TBD
	 *
	 * @param int[]                $post_ids    Array of post IDs.
	 * @param array<string, mixed> $grid_config Time grid configuration.
	 * @param VisibleRange|null    $range       Visible date range, or null to place each event from its start.
	 *
	 * @return PostsByDate Slotted calendar entries grouped by date.
	 */
	public static function organize_posts(
		array $post_ids,
		array $grid_config = array(),
		?array $range = null
	): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$sorted_events = self::sort_events_for_slotting( self::resolve_post_spans( $post_ids, $range ) );

		return self::build_slotted_calendar_entries( $sorted_events, $grid_config );
	}

	/**
	 * Extracts valid post IDs from a WP_Query loop.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_args WP_Query arguments.
	 *
	 * @return list<int>
	 */
	public static function fetch_post_ids_from_query( array $query_args ): array {
		// Ensure we only query IDs regardless of caller arguments.
		$query_args['fields'] = 'ids';
		$query                = new WP_Query( $query_args );

		if ( empty( $query->posts ) ) {
			return array();
		}

		$post_ids = array_values(
			array_map(
				static function ( $post ): int {
					return $post instanceof WP_Post ? $post->ID : (int) $post;
				},
				$query->posts
			)
		);

		// Batch-prime caches so subsequent get_post() and get_post_meta() calls are memory hits.
		_prime_post_caches( $post_ids );

		return $post_ids;
	}

	/**
	 * Resolves active date spans for a list of post IDs.
	 *
	 * @since TBD
	 *
	 * @param int[]             $post_ids Array of post IDs.
	 * @param VisibleRange|null $range    Visible date range, or null to place each event from its start.
	 *
	 * @return list<EventSpan>
	 */
	public static function resolve_post_spans( array $post_ids, ?array $range = null ): array {
		$spans = array();

		foreach ( $post_ids as $post_id ) {
			$dates = self::get_post_dates( $post_id );
			$span  = self::calculate_date_span( $dates['start'], $dates['end'], $range );

			if ( null === $span ) {
				continue;
			}

			$spans[] = array(
				'id'         => $post_id,
				'start_obj'  => $span['start_obj'],
				'start_date' => $dates['start'],
				'total_days' => $span['total_days'],
				'first_day'  => $span['first_day'],
				'shown_days' => $span['shown_days'],
				'is_all_day' => ( new Event\Event( $post_id ) )->is_all_day(),
			);
		}

		return $spans;
	}

	/**
	 * Calculates day duration and start DateTime object from start and end date strings.
	 *
	 * Pure date math decoupled from WordPress post data. `total_days` is the full length of the
	 * event. `first_day` (0-based) and `shown_days` give the part of the event inside the visible
	 * range, so an event that starts before the range still shows on the visible days.
	 *
	 * @since TBD
	 *
	 * @param string            $start_date Y-m-d start date string.
	 * @param string            $end_date   Y-m-d end date string.
	 * @param VisibleRange|null $range      Visible date range, or null to start at the first day of the event.
	 *
	 * @return array{start_obj: DateTimeImmutable, total_days: int, first_day: int, shown_days: int}|null Null
	 *         when a date is not valid or the event is outside the visible range.
	 */
	public static function calculate_date_span( string $start_date, string $end_date, ?array $range = null ): ?array {
		$start_obj = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, wp_timezone() );
		$end_obj   = DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, wp_timezone() );

		if ( ! $start_obj instanceof DateTimeImmutable || ! $end_obj instanceof DateTimeImmutable ) {
			return null;
		}

		$end_obj    = max( $start_obj, $end_obj );
		$total_days = (int) $start_obj->diff( $end_obj )->days + 1;
		$first_day  = 0;
		$last_day   = $total_days - 1;

		if ( null !== $range ) {
			if ( $end_obj < $range['start'] || $start_obj > $range['end'] ) {
				return null;
			}

			if ( $start_obj < $range['start'] ) {
				$first_day = (int) $start_obj->diff( $range['start'] )->days;
			}

			if ( $end_obj > $range['end'] ) {
				$last_day = (int) $start_obj->diff( $range['end'] )->days;
			}
		}

		return array(
			'start_obj'  => $start_obj,
			'total_days' => $total_days,
			'first_day'  => $first_day,
			'shown_days' => min( self::MAX_EVENT_SPAN_DAYS, $last_day - $first_day + 1 ),
		);
	}

	/**
	 * Sorts events for slotting.
	 *
	 * Multi-day and all-day events first, then longer before shorter, then start date, then ID.
	 *
	 * @since TBD
	 *
	 * @param array<int, EventSpan> $events List of event descriptors.
	 *
	 * @return list<EventSpan>
	 */
	public static function sort_events_for_slotting( array $events ): array {
		usort(
			$events,
			static function ( array $a, array $b ): int {
				$a_promoted = $a['total_days'] > 1 || $a['is_all_day'];
				$b_promoted = $b['total_days'] > 1 || $b['is_all_day'];

				return array( $b_promoted, $b['total_days'], $a['start_date'], $a['id'] )
					<=> array( $a_promoted, $a['total_days'], $b['start_date'], $b['id'] );
			}
		);

		return $events;
	}

	/**
	 * Generates an array of Y-m-d date strings covered by an event.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $start_obj  Start date object.
	 * @param int               $total_days Total days duration.
	 *
	 * @return list<string>
	 */
	public static function get_span_dates( DateTimeImmutable $start_obj, int $total_days ): array {
		$dates = array();

		for ( $i = 0; $i < $total_days; $i++ ) {
			$dates[] = $start_obj->modify( "+{$i} days" )->format( Date_Calculator::DATE_FORMAT );
		}

		return $dates;
	}

	/**
	 * Finds the lowest 0-indexed vertical slot free across all dates of an event's span.
	 *
	 * @since TBD
	 *
	 * @param string[]                        $dates          Dates occupied by the event.
	 * @param array<string, array<int, bool>> $occupied_slots Map of date strings to occupied slots.
	 *
	 * @return int Lowest available slot index.
	 */
	public static function find_available_slot( array $dates, array $occupied_slots ): int {
		$slot = 0;

		while ( self::is_slot_taken( $slot, $dates, $occupied_slots ) ) {
			++$slot;
		}

		return $slot;
	}

	/**
	 * Checks whether a slot is taken on any of the given dates.
	 *
	 * @since TBD
	 *
	 * @param int                             $slot           Slot index.
	 * @param string[]                        $dates          Dates occupied by the event.
	 * @param array<string, array<int, bool>> $occupied_slots Map of date strings to occupied slots.
	 *
	 * @return bool True when another event holds the slot on one of the dates.
	 */
	private static function is_slot_taken( int $slot, array $dates, array $occupied_slots ): bool {
		foreach ( $dates as $date_str ) {
			if ( isset( $occupied_slots[ $date_str ][ $slot ] ) ) {
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
	public static function determine_span_type( int $day_index, int $total_days ): string {
		if ( $total_days <= 1 ) {
			return 'single';
		}

		if ( 1 === $day_index ) {
			return 'start';
		}

		if ( $total_days === $day_index ) {
			return 'end';
		}

		return 'middle';
	}

	/**
	 * Allocates a single-day timed event to the time grid.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>            $event          Event descriptor.
	 * @param array<string, mixed>            $grid_config    Time grid configuration.
	 * @param array<string, array<int, bool>> $occupied_slots Map of date strings to occupied slots.
	 * @param array<string, mixed>            $posts_by_date  Slotted entries grouped by date.
	 * @phpstan-param EventSpan $event
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return void
	 */
	private static function allocate_timed_event_entry(
		array $event,
		array $grid_config,
		array &$occupied_slots,
		array &$posts_by_date
	): void {
		$tracks = self::calculate_timed_event_tracks( $event['id'], $grid_config );

		if ( null === $tracks ) {
			return;
		}

		$date_str                             = $event['start_date'];
		$slot                                 = self::find_available_slot( array( $date_str ), $occupied_slots );
		$occupied_slots[ $date_str ][ $slot ] = true;
		$posts_by_date[ $date_str ][]         = array(
			'id'             => $event['id'],
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
	 * @param array<string, mixed>            $event          Event descriptor.
	 * @param array<string, array<int, bool>> $occupied_slots Map of date strings to occupied slots.
	 * @param array<string, mixed>            $posts_by_date  Slotted entries grouped by date.
	 * @phpstan-param EventSpan $event
	 * @phpstan-param PostsByDate $posts_by_date
	 *
	 * @return void
	 */
	private static function allocate_ribbon_event_entry(
		array $event,
		array &$occupied_slots,
		array &$posts_by_date
	): void {
		$first_day   = $event['first_day'];
		$event_dates = self::get_span_dates(
			$event['start_obj']->modify( "+{$first_day} days" ),
			$event['shown_days']
		);
		$slot        = self::find_available_slot( $event_dates, $occupied_slots );

		foreach ( $event_dates as $i => $date_str ) {
			$day_index                            = $first_day + $i + 1;
			$occupied_slots[ $date_str ][ $slot ] = true;
			$posts_by_date[ $date_str ][]         = array(
				'id'         => $event['id'],
				'span'       => self::determine_span_type( $day_index, $event['total_days'] ),
				'day_index'  => $day_index,
				'total_days' => $event['total_days'],
				'is_all_day' => $event['is_all_day'],
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
	private static function resolve_day_timed_overlaps( array $day_entries ): array {
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
				$day_entries = self::assign_overlap_group( $day_entries, $group, count( $column_ends ) );
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

		$day_entries = self::assign_overlap_group( $day_entries, $group, count( $column_ends ) );

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
	private static function assign_overlap_group( array $day_entries, array $group, int $columns ): array {
		foreach ( $group as $idx => $column ) {
			$day_entries[ $idx ]['overlap_total'] = $columns;
			$day_entries[ $idx ]['overlap_index'] = $column;
		}

		return $day_entries;
	}

	/**
	 * Allocates slots and groups entries into the daily map.
	 *
	 * @since TBD
	 *
	 * @param array<int, EventSpan> $sorted_events Sorted event descriptors.
	 * @param array<string, mixed>  $grid_config   Time grid configuration.
	 *
	 * @return PostsByDate Slotted entries by date.
	 */
	public static function build_slotted_calendar_entries( array $sorted_events, array $grid_config = array() ): array {
		$occupied_slots = array();
		$posts_by_date  = array();
		$is_time_grid   = ! empty( $grid_config['enabled'] );

		foreach ( $sorted_events as $event ) {
			$is_multi_day = $event['total_days'] > 1 || $event['is_all_day'];

			// In time-grid mode, single-day events go on the time tracks.
			if ( $is_time_grid && ! $is_multi_day ) {
				self::allocate_timed_event_entry( $event, $grid_config, $occupied_slots, $posts_by_date );
				continue;
			}

			self::allocate_ribbon_event_entry( $event, $occupied_slots, $posts_by_date );
		}

		if ( $is_time_grid ) {
			foreach ( $posts_by_date as $date_str => $day_entries ) {
				$posts_by_date[ $date_str ] = self::resolve_day_timed_overlaps( $day_entries );
			}
		}

		return $posts_by_date;
	}

	/**
	 * Resolves start and end dates for a post.
	 *
	 * Events use their event dates. Other posts use their publish date.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{start: string, end: string}
	 */
	public static function get_post_dates( int $post_id ): array {
		$event_dates = Date_Calculator::get_post_event_dates( $post_id );

		if ( null !== $event_dates ) {
			return $event_dates;
		}

		$date_str = (string) get_the_date( Date_Calculator::DATE_FORMAT, $post_id );

		return array(
			'start' => $date_str,
			'end'   => $date_str,
		);
	}

	/**
	 * Compute quarter-slot row tracks for a timed event within configured day boundaries.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id     Post ID.
	 * @param array<string, mixed> $grid_config Time grid configuration.
	 *
	 * @return array{row_start: int, row_end: int}|null Row track coordinates or null if not an event.
	 */
	public static function calculate_timed_event_tracks( int $post_id, array $grid_config ): ?array {
		$datetimes = Date_Calculator::get_post_event_datetimes( $post_id );

		if ( null === $datetimes ) {
			return null;
		}

		$start_hour   = is_numeric( $grid_config['start_hour'] ?? null ) ? (int) $grid_config['start_hour'] : 8;
		$end_hour     = is_numeric( $grid_config['end_hour'] ?? null ) ? (int) $grid_config['end_hour'] : 20;
		$quarter_mins = is_numeric( $grid_config['quarter_mins'] ?? null ) ? (int) $grid_config['quarter_mins'] : 15;
		$quarter_mins = max( 1, $quarter_mins );

		$day_start_min = $start_hour * 60;
		$day_end_min   = $end_hour * 60;

		// Clamp the event to the visible day window.
		$clamped_start = max( $day_start_min, $datetimes['start_min'] );
		$clamped_end   = min( $day_end_min, $datetimes['end_min'] );

		// Round to 1/4 of slot duration.
		$rounded_start = Date_Calculator::round_to_quarter_slot( $clamped_start, $quarter_mins );
		$rounded_end   = Date_Calculator::round_to_quarter_slot( $clamped_end, $quarter_mins, true );

		if ( $rounded_end <= $rounded_start ) {
			$rounded_end = $rounded_start + $quarter_mins;
		}

		$start_track = (int) ( ( $rounded_start - $day_start_min ) / $quarter_mins ) + 1;
		$end_track   = (int) ( ( $rounded_end - $day_start_min ) / $quarter_mins ) + 1;

		return array(
			'row_start' => $start_track,
			'row_end'   => max( $start_track + 1, $end_track ),
		);
	}
}
