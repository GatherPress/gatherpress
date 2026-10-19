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
 *     is_all_day: bool
 * }
 * @phpstan-type PostsByDate array<string, list<array<string, mixed>>>
 */
final class Post_Organizer {

	/**
	 * Maximum number of days an event can span to prevent memory exhaustion from invalid dates.
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
		return self::organize_posts( self::fetch_post_ids_from_query( $query_args ), $grid_config );
	}

	/**
	 * Transforms an array of post IDs into slotted date-grouped entries.
	 *
	 * @since TBD
	 *
	 * @param int[]                $post_ids    Array of post IDs.
	 * @param array<string, mixed> $grid_config Time grid configuration.
	 *
	 * @return PostsByDate Slotted calendar entries grouped by date.
	 */
	public static function organize_posts( array $post_ids, array $grid_config = array() ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$sorted_events = self::sort_events_for_slotting( self::resolve_post_spans( $post_ids ) );

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
	 * @param int[] $post_ids Array of post IDs.
	 *
	 * @return list<EventSpan>
	 */
	public static function resolve_post_spans( array $post_ids ): array {
		$spans = array();

		foreach ( $post_ids as $post_id ) {
			$dates = self::get_post_dates( $post_id );
			$span  = self::calculate_date_span( $dates['start'], $dates['end'] );

			if ( null === $span ) {
				continue;
			}

			$spans[] = array(
				'id'         => $post_id,
				'start_obj'  => $span['start_obj'],
				'start_date' => $dates['start'],
				'total_days' => $span['total_days'],
				'is_all_day' => ( new Event\Event( $post_id ) )->is_all_day(),
			);
		}

		return $spans;
	}

	/**
	 * Calculates day duration and start DateTime object from start and end date strings.
	 *
	 * Pure date math decoupled from WordPress post data.
	 *
	 * @since TBD
	 *
	 * @param string $start_date Y-m-d start date string.
	 * @param string $end_date   Y-m-d end date string.
	 *
	 * @return array{start_obj: DateTimeImmutable, total_days: int}|null
	 */
	public static function calculate_date_span( string $start_date, string $end_date ): ?array {
		$start_obj = DateTimeImmutable::createFromFormat( '!Y-m-d', $start_date, wp_timezone() );
		$end_obj   = DateTimeImmutable::createFromFormat( '!Y-m-d', $end_date, wp_timezone() );

		if ( ! $start_obj instanceof DateTimeImmutable || ! $end_obj instanceof DateTimeImmutable ) {
			return null;
		}

		$end_obj    = max( $start_obj, $end_obj );
		$total_days = (int) $start_obj->diff( $end_obj )->days + 1;

		return array(
			'start_obj'  => $start_obj,
			'total_days' => min( self::MAX_EVENT_SPAN_DAYS, $total_days ),
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
		$event_dates = self::get_span_dates( $event['start_obj'], $event['total_days'] );
		$slot        = self::find_available_slot( $event_dates, $occupied_slots );

		foreach ( $event_dates as $i => $date_str ) {
			$occupied_slots[ $date_str ][ $slot ] = true;
			$posts_by_date[ $date_str ][]         = array(
				'id'         => $event['id'],
				'span'       => self::determine_span_type( $i + 1, $event['total_days'] ),
				'day_index'  => $i + 1,
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
	 * Each timed entry gets the number of timed entries it overlaps (itself included) and its
	 * position among them.
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

		foreach ( $timed as $curr_idx => $curr ) {
			$overlaps    = 0;
			$overlap_idx = 0;

			foreach ( $timed as $other_idx => $other ) {
				$is_before = $curr['time_row_end'] <= $other['time_row_start'];
				$is_after  = $curr['time_row_start'] >= $other['time_row_end'];

				if ( $is_before || $is_after ) {
					continue;
				}

				++$overlaps;

				if ( $other_idx < $curr_idx ) {
					++$overlap_idx;
				}
			}

			$day_entries[ $curr_idx ]['overlap_total'] = max( 1, $overlaps );
			$day_entries[ $curr_idx ]['overlap_index'] = $overlap_idx;
		}

		return array_values( $day_entries );
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
