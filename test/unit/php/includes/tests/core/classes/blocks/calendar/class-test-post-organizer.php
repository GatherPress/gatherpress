<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Calendar\Post_Organizer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Calendar;

use DateTimeImmutable;
use GatherPress\Core\Blocks\Calendar\Post_Organizer;
use GatherPress\Core\Blocks\Calendar\Query_Integration;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Post_Organizer.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Calendar\Post_Organizer
 */
class Test_Post_Organizer extends Base {

	/**
	 * A time grid from 08:00 to 20:00 with 15-minute tracks.
	 *
	 * @var array<string, mixed>
	 */
	const GRID = array(
		'enabled'      => true,
		'start_hour'   => 8,
		'end_hour'     => 20,
		'quarter_mins' => 15,
	);

	/**
	 * Create an event with the given UTC datetimes.
	 *
	 * @param string $start   Start datetime (Y-m-d H:i:s).
	 * @param string $end     End datetime (Y-m-d H:i:s).
	 * @param bool   $all_day Whether the event is all day.
	 *
	 * @return int Event post ID.
	 */
	private function create_event( string $start, string $end, bool $all_day = false ): int {
		$post = $this->mock->post(
			array(
				'post_title' => 'Calendar Unit Test Event',
				'post_type'  => Event::POST_TYPE,
			)
		)->get();

		if ( $all_day ) {
			update_post_meta( $post->ID, 'gatherpress_is_all_day', true );
		}

		( new Event( $post->ID ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'UTC',
			)
		);

		return $post->ID;
	}

	/**
	 * Build an event descriptor like resolve_post_spans() returns.
	 *
	 * @param int    $id         Post ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param int    $total_days Number of days.
	 * @param bool   $is_all_day Whether the event is all day.
	 *
	 * @return array<string, mixed>
	 */
	private function span( int $id, string $start_date, int $total_days, bool $is_all_day = false ): array {
		return array(
			'id'         => $id,
			'start_obj'  => new DateTimeImmutable( $start_date, wp_timezone() ),
			'start_date' => $start_date,
			'total_days' => $total_days,
			'first_day'  => 0,
			'shown_days' => $total_days,
			'is_all_day' => $is_all_day,
		);
	}

	/**
	 * Build a visible range like get_visible_range() returns.
	 *
	 * @param string $start First visible day (Y-m-d).
	 * @param string $end   Last visible day (Y-m-d).
	 *
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable}
	 */
	private function range( string $start, string $end ): array {
		return array(
			'start' => new DateTimeImmutable( $start, wp_timezone() ),
			'end'   => new DateTimeImmutable( $end, wp_timezone() ),
		);
	}

	/**
	 * Coverage for organize_posts_by_date, organize_posts and fetch_post_ids_from_query.
	 *
	 * @covers ::organize_posts_by_date
	 * @covers ::organize_posts
	 * @covers ::fetch_post_ids_from_query
	 *
	 * @return void
	 */
	public function test_organize_posts_by_date(): void {
		$post_id = $this->mock->post( array( 'post_date' => '2026-10-15 10:00:00' ) )->get()->ID;

		$this->assertSame(
			array(
				'2026-10-15' => array(
					array(
						'id'         => $post_id,
						'span'       => 'single',
						'day_index'  => 1,
						'total_days' => 1,
						'is_all_day' => false,
						'is_timed'   => false,
						'slot'       => 0,
					),
				),
			),
			Post_Organizer::organize_posts_by_date(
				array(
					'post_type' => 'post',
					'post__in'  => array( $post_id ),
				)
			)
		);

		$this->assertSame(
			array(),
			Post_Organizer::organize_posts_by_date( array( 'post__in' => array( PHP_INT_MAX ) ) ),
			'Failed to assert an empty query gives no entries.'
		);
		$this->assertSame( array(), Post_Organizer::organize_posts( array() ) );
	}

	/**
	 * A long event shows on the visible days, also when it started more than a year before them.
	 *
	 * @covers ::organize_posts_by_date
	 * @covers ::get_visible_range
	 * @covers ::allocate_ribbon_event_entry
	 *
	 * @return void
	 */
	public function test_organize_posts_by_date_clips_long_event_to_visible_range(): void {
		$event_id  = $this->create_event( '2025-01-01 10:00:00', '2026-12-31 16:00:00' );
		$start_key = Query_Integration::CALENDAR_QUERY_START_DATE;
		$end_key   = Query_Integration::CALENDAR_QUERY_END_DATE;
		$entries   = Post_Organizer::organize_posts_by_date(
			array(
				'post_type' => Event::POST_TYPE,
				'post__in'  => array( $event_id ),
				$start_key  => '2026-10-01',
				$end_key    => '2026-10-31',
			)
		);

		$this->assertCount( 31, $entries, 'Failed to assert one entry for each day of October.' );
		$this->assertSame( '2026-10-01', array_key_first( $entries ) );
		$this->assertSame( '2026-10-31', array_key_last( $entries ) );
		$this->assertSame(
			array(
				'id'         => $event_id,
				'span'       => 'middle',
				'day_index'  => 639,
				'total_days' => 730,
				'is_all_day' => false,
				'is_timed'   => false,
				'slot'       => 0,
			),
			$entries['2026-10-01'][0],
			'Failed to assert the first visible day keeps its day number in the full event.'
		);
		$this->assertSame( 669, $entries['2026-10-31'][0]['day_index'] );

		$entries = Post_Organizer::organize_posts( array( $event_id ) );
		$this->assertCount(
			Post_Organizer::MAX_EVENT_SPAN_DAYS,
			$entries,
			'Failed to assert the day limit without a visible range.'
		);
		$this->assertSame( 'start', $entries['2025-01-01'][0]['span'] );
	}

	/**
	 * Coverage for get_visible_range.
	 *
	 * @covers ::get_visible_range
	 *
	 * @return void
	 */
	public function test_get_visible_range(): void {
		$start = Query_Integration::CALENDAR_QUERY_START_DATE;
		$end   = Query_Integration::CALENDAR_QUERY_END_DATE;

		$this->assertNull( Post_Organizer::get_visible_range( array() ) );
		$this->assertNull(
			Post_Organizer::get_visible_range(
				array(
					$start => '2026-10-01',
					$end   => 20261031,
				)
			),
			'Failed to assert a date that is not a string gives no range.'
		);
		$this->assertNull(
			Post_Organizer::get_visible_range(
				array(
					$start => 'October',
					$end   => '2026-10-31',
				)
			),
			'Failed to assert a date that is not valid gives no range.'
		);
		$this->assertNull(
			Post_Organizer::get_visible_range(
				array(
					$start => '2026-10-31',
					$end   => '2026-10-01',
				)
			),
			'Failed to assert an end before the start gives no range.'
		);

		$range = Post_Organizer::get_visible_range(
			array(
				$start => '2026-10-01',
				$end   => '2026-10-31',
			)
		);
		$this->assertSame( '2026-10-01 00:00:00', $range['start']->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-10-31 00:00:00', $range['end']->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Coverage for resolve_post_spans and get_post_dates.
	 *
	 * @covers ::resolve_post_spans
	 * @covers ::get_post_dates
	 *
	 * @return void
	 */
	public function test_resolve_post_spans(): void {
		$multi_day = $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' );
		$all_day   = $this->create_event( '2026-10-22 00:00:00', '2026-10-22 23:59:59', true );
		$post_id   = $this->mock->post( array( 'post_date' => '2026-10-15 10:00:00' ) )->get()->ID;
		$spans     = Post_Organizer::resolve_post_spans( array( $multi_day, $all_day, $post_id, PHP_INT_MAX ) );

		$this->assertCount( 3, $spans, 'Failed to assert a missing post is skipped.' );
		$this->assertSame( $multi_day, $spans[0]['id'] );
		$this->assertSame( '2026-10-16', $spans[0]['start_date'] );
		$this->assertSame( 5, $spans[0]['total_days'] );
		$this->assertSame( 0, $spans[0]['first_day'] );
		$this->assertSame( 5, $spans[0]['shown_days'] );
		$this->assertFalse( $spans[0]['is_all_day'] );
		$this->assertSame( 1, $spans[1]['total_days'] );
		$this->assertTrue( $spans[1]['is_all_day'] );
		$this->assertSame( '2026-10-15', $spans[2]['start_date'], 'Failed to assert a post uses its publish date.' );

		$spans = Post_Organizer::resolve_post_spans(
			array( $multi_day, $all_day, $post_id ),
			$this->range( '2026-10-18', '2026-10-31' )
		);
		$this->assertCount( 2, $spans, 'Failed to assert a post before the visible range is skipped.' );
		$this->assertSame( 2, $spans[0]['first_day'] );
		$this->assertSame( 3, $spans[0]['shown_days'] );
		$this->assertSame( $all_day, $spans[1]['id'] );

		$this->assertSame(
			array(
				'start' => '',
				'end'   => '',
			),
			Post_Organizer::get_post_dates( PHP_INT_MAX )
		);
	}

	/**
	 * Coverage for calculate_date_span.
	 *
	 * @covers ::calculate_date_span
	 *
	 * @return void
	 */
	public function test_calculate_date_span(): void {
		$this->assertNull( Post_Organizer::calculate_date_span( '', '2026-10-20' ) );

		$span = Post_Organizer::calculate_date_span( '2026-10-16', '2026-10-20' );
		$this->assertSame( '2026-10-16', $span['start_obj']->format( 'Y-m-d' ) );
		$this->assertSame( 5, $span['total_days'] );

		$span = Post_Organizer::calculate_date_span( '2026-10-20', '2026-10-16' );
		$this->assertSame( 1, $span['total_days'], 'Failed to assert an end before the start is one day.' );

		$span = Post_Organizer::calculate_date_span( '2026-01-01', '2027-12-31' );
		$this->assertSame( 730, $span['total_days'], 'Failed to assert the full length of a long event.' );
		$this->assertSame( 0, $span['first_day'] );
		$this->assertSame( Post_Organizer::MAX_EVENT_SPAN_DAYS, $span['shown_days'] );

		$october = $this->range( '2026-10-01', '2026-10-31' );
		$span    = Post_Organizer::calculate_date_span( '2025-01-01', '2026-12-31', $october );
		$this->assertSame( 730, $span['total_days'] );
		$this->assertSame( 638, $span['first_day'], 'Failed to assert the first visible day is October 1.' );
		$this->assertSame( 31, $span['shown_days'], 'Failed to assert the event ends after the visible range.' );

		$span = Post_Organizer::calculate_date_span( '2026-10-16', '2026-10-20', $october );
		$this->assertSame( 0, $span['first_day'] );
		$this->assertSame( 5, $span['shown_days'], 'Failed to assert an event inside the range is not clipped.' );

		$this->assertNull(
			Post_Organizer::calculate_date_span( '2026-09-01', '2026-09-30', $october ),
			'Failed to assert an event before the range gives no span.'
		);
		$this->assertNull(
			Post_Organizer::calculate_date_span( '2026-11-01', '2026-11-02', $october ),
			'Failed to assert an event after the range gives no span.'
		);
	}

	/**
	 * A day where daylight saving time starts at midnight still counts as a day.
	 *
	 * In America/Santiago the clocks go from 00:00 to 01:00 on 2026-09-06, so that day has 23 hours.
	 *
	 * @covers ::calculate_date_span
	 * @covers ::get_visible_range
	 * @covers ::parse_day
	 *
	 * @return void
	 */
	public function test_date_span_across_midnight_dst_start(): void {
		update_option( 'timezone_string', 'America/Santiago' );

		$span = Post_Organizer::calculate_date_span( '2026-09-06', '2026-09-07' );
		$this->assertSame( 2, $span['total_days'], 'Failed to assert the 23-hour day counts.' );

		$start_key = Query_Integration::CALENDAR_QUERY_START_DATE;
		$end_key   = Query_Integration::CALENDAR_QUERY_END_DATE;
		$range     = Post_Organizer::get_visible_range(
			array(
				$start_key => '2026-09-07',
				$end_key   => '2026-09-30',
			)
		);
		$span      = Post_Organizer::calculate_date_span( '2026-09-06', '2026-09-08', $range );
		$this->assertSame( 3, $span['total_days'] );
		$this->assertSame( 1, $span['first_day'] );
		$this->assertSame( 2, $span['shown_days'], 'Failed to assert September 8 is still shown.' );

		$this->assertNull( Post_Organizer::calculate_date_span( 'September', '2026-09-07' ) );
	}

	/**
	 * Coverage for sort_events_for_slotting.
	 *
	 * @covers ::sort_events_for_slotting
	 *
	 * @return void
	 */
	public function test_sort_events_for_slotting(): void {
		$events = array(
			$this->span( 3, '2026-10-15', 1 ),
			$this->span( 2, '2026-10-16', 2 ),
			$this->span( 7, '2026-10-14', 1 ),
			$this->span( 5, '2026-10-14', 5 ),
			$this->span( 4, '2026-10-15', 1, true ),
			$this->span( 1, '2026-10-14', 1 ),
		);
		$sorted = Post_Organizer::sort_events_for_slotting( $events );

		$this->assertSame(
			array( 5, 2, 4, 1, 7, 3 ),
			array_column( $sorted, 'id' ),
			'Failed to assert long, then all-day, then by start date, then by ID.'
		);
	}

	/**
	 * Coverage for the slot helpers.
	 *
	 * @covers ::get_span_dates
	 * @covers ::find_available_slot
	 * @covers ::is_slot_taken
	 * @covers ::determine_span_type
	 *
	 * @return void
	 */
	public function test_slot_helpers(): void {
		$this->assertSame(
			array( '2026-10-30', '2026-10-31', '2026-11-01' ),
			Post_Organizer::get_span_dates( new DateTimeImmutable( '2026-10-30', wp_timezone() ), 3 )
		);

		$occupied = array(
			'2026-10-15' => array( 0 => true ),
			'2026-10-16' => array( 1 => true ),
		);
		$this->assertSame( 2, Post_Organizer::find_available_slot( array( '2026-10-15', '2026-10-16' ), $occupied ) );
		$this->assertSame( 0, Post_Organizer::find_available_slot( array( '2026-10-17' ), $occupied ) );

		$this->assertSame( 'single', Post_Organizer::determine_span_type( 1, 1 ) );
		$this->assertSame( 'single', Post_Organizer::determine_span_type( 1, 0 ) );
		$this->assertSame( 'start', Post_Organizer::determine_span_type( 1, 3 ) );
		$this->assertSame( 'middle', Post_Organizer::determine_span_type( 2, 3 ) );
		$this->assertSame( 'end', Post_Organizer::determine_span_type( 3, 3 ) );
	}

	/**
	 * Coverage for build_slotted_calendar_entries without a time grid.
	 *
	 * @covers ::build_slotted_calendar_entries
	 * @covers ::allocate_ribbon_event_entry
	 *
	 * @return void
	 */
	public function test_build_slotted_calendar_entries(): void {
		$entries = Post_Organizer::build_slotted_calendar_entries(
			array(
				$this->span( 5, '2026-10-14', 5 ),
				$this->span( 2, '2026-10-16', 2, true ),
				$this->span( 1, '2026-10-14', 1 ),
			)
		);

		$this->assertSame(
			array( '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18' ),
			array_keys( $entries )
		);
		$this->assertSame(
			array(
				'id'         => 5,
				'span'       => 'middle',
				'day_index'  => 3,
				'total_days' => 5,
				'is_all_day' => false,
				'is_timed'   => false,
				'slot'       => 0,
			),
			$entries['2026-10-16'][0]
		);
		$this->assertSame( 'start', $entries['2026-10-16'][1]['span'] );
		$this->assertTrue( $entries['2026-10-16'][1]['is_all_day'] );
		$this->assertSame(
			1,
			$entries['2026-10-16'][1]['slot'],
			'Failed to assert the second ribbon takes the next slot.'
		);
		$this->assertSame(
			1,
			$entries['2026-10-14'][1]['slot'],
			'Failed to assert the single event goes below the ribbon.'
		);
		$this->assertSame( 'end', $entries['2026-10-18'][0]['span'] );
	}

	/**
	 * The entries of a day are in slot order, so the reading order matches the rows on screen.
	 *
	 * X (October 5-6) takes slot 0 and Y (October 6-7) slot 1. Z (October 7) fits in slot 0,
	 * but it is placed after Y.
	 *
	 * @covers ::build_slotted_calendar_entries
	 *
	 * @return void
	 */
	public function test_build_slotted_calendar_entries_sorts_days_by_slot(): void {
		$entries = Post_Organizer::build_slotted_calendar_entries(
			array(
				$this->span( 1, '2026-10-05', 2 ),
				$this->span( 2, '2026-10-06', 2 ),
				$this->span( 3, '2026-10-07', 1 ),
			)
		);

		$this->assertSame( array( 3, 2 ), array_column( $entries['2026-10-07'], 'id' ) );
		$this->assertSame( array( 0, 1 ), array_column( $entries['2026-10-07'], 'slot' ) );
	}

	/**
	 * Coverage for build_slotted_calendar_entries with a time grid.
	 *
	 * @covers ::build_slotted_calendar_entries
	 * @covers ::allocate_timed_event_entry
	 * @covers ::resolve_day_timed_overlaps
	 *
	 * @return void
	 */
	public function test_build_slotted_calendar_entries_with_time_grid(): void {
		$ribbon  = $this->create_event( '2026-10-15 09:00:00', '2026-10-16 17:00:00' );
		$first   = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 11:00:00' );
		$second  = $this->create_event( '2026-10-16 10:30:00', '2026-10-16 12:00:00' );
		$third   = $this->create_event( '2026-10-16 13:00:00', '2026-10-16 14:00:00' );
		$post_id = $this->mock->post( array( 'post_date' => '2026-10-16 10:00:00' ) )->get()->ID;
		$entries = Post_Organizer::organize_posts( array( $third, $post_id, $second, $ribbon, $first ), self::GRID );
		$day     = $entries['2026-10-16'];

		$this->assertCount( 4, $day, 'Failed to assert the post without event times is left out.' );
		$this->assertSame( $ribbon, $day[0]['id'] );
		$this->assertFalse( $day[0]['is_timed'] );
		$this->assertArrayNotHasKey( 'overlap_total', $day[0] );

		$this->assertSame(
			array(
				'id'             => $first,
				'span'           => 'single',
				'day_index'      => 1,
				'total_days'     => 1,
				'is_all_day'     => false,
				'is_timed'       => true,
				'time_row_start' => 9,
				'time_row_end'   => 13,
				'slot'           => 1,
				'overlap_total'  => 2,
				'overlap_index'  => 0,
			),
			$day[1]
		);
		$this->assertSame( $second, $day[2]['id'] );
		$this->assertSame( 11, $day[2]['time_row_start'] );
		$this->assertSame( 17, $day[2]['time_row_end'] );
		$this->assertSame( 2, $day[2]['overlap_total'] );
		$this->assertSame( 1, $day[2]['overlap_index'], 'Failed to assert the second event goes beside the first.' );
		$this->assertSame( $third, $day[3]['id'] );
		$this->assertSame( 1, $day[3]['overlap_total'], 'Failed to assert an event that touches no other is alone.' );
		$this->assertSame( 0, $day[3]['overlap_index'] );
	}

	/**
	 * Events that overlap through other events never share a column.
	 *
	 * B overlaps A and C, and C overlaps D, so the four events form one group of three columns.
	 *
	 * @covers ::resolve_day_timed_overlaps
	 * @covers ::assign_overlap_group
	 *
	 * @return void
	 */
	public function test_resolve_day_timed_overlaps_assigns_columns_per_group(): void {
		$a       = $this->create_event( '2026-10-16 09:00:00', '2026-10-16 10:00:00' );
		$b       = $this->create_event( '2026-10-16 09:30:00', '2026-10-16 10:30:00' );
		$c       = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 11:00:00' );
		$d       = $this->create_event( '2026-10-16 10:15:00', '2026-10-16 11:00:00' );
		$entries = Post_Organizer::organize_posts( array( $d, $c, $b, $a ), self::GRID );
		$columns = array();

		foreach ( $entries['2026-10-16'] as $entry ) {
			$columns[ $entry['id'] ] = array( $entry['overlap_index'], $entry['overlap_total'] );
		}

		$this->assertSame(
			array(
				$a => array( 0, 3 ),
				$b => array( 1, 3 ),
				$c => array( 0, 3 ),
				$d => array( 2, 3 ),
			),
			$columns,
			'Failed to assert B and C, which overlap, get different columns.'
		);
	}

	/**
	 * Coverage for calculate_timed_event_tracks.
	 *
	 * @covers ::calculate_timed_event_tracks
	 *
	 * @return void
	 */
	public function test_calculate_timed_event_tracks(): void {
		$post_id = $this->mock->post()->get()->ID;
		$this->assertNull( Post_Organizer::calculate_timed_event_tracks( $post_id, self::GRID ) );

		$event_id = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 11:10:00' );
		$this->assertSame(
			array(
				'row_start' => 9,
				'row_end'   => 14,
			),
			Post_Organizer::calculate_timed_event_tracks( $event_id, array() ),
			'Failed to assert the default grid and a rounded-up end.'
		);

		$early = $this->create_event( '2026-10-16 06:00:00', '2026-10-16 07:00:00' );
		$this->assertSame(
			array(
				'row_start' => 1,
				'row_end'   => 2,
			),
			Post_Organizer::calculate_timed_event_tracks( $early, self::GRID ),
			'Failed to assert an event before the grid gets the first track.'
		);

		// The grid has 48 tracks, from line 1 (08:00) to line 49 (20:00).
		$late = $this->create_event( '2026-10-16 21:00:00', '2026-10-16 22:00:00' );
		$this->assertSame(
			array(
				'row_start' => 48,
				'row_end'   => 49,
			),
			Post_Organizer::calculate_timed_event_tracks( $late, self::GRID ),
			'Failed to assert an event after the grid gets the last track, not a row below the grid.'
		);

		$at_end = $this->create_event( '2026-10-16 20:00:00', '2026-10-16 20:30:00' );
		$this->assertSame(
			array(
				'row_start' => 48,
				'row_end'   => 49,
			),
			Post_Organizer::calculate_timed_event_tracks( $at_end, self::GRID ),
			'Failed to assert an event at the last hour gets the last track.'
		);

		// 3-hour rows give 45-minute steps. From 08:00 they are at 08:45 and 09:30, not at 09:00.
		$three_hours = array(
			'start_hour'   => 8,
			'end_hour'     => 20,
			'quarter_mins' => 45,
		);
		$short       = $this->create_event( '2026-10-16 08:00:00', '2026-10-16 08:50:00' );
		$this->assertSame(
			array(
				'row_start' => 1,
				'row_end'   => 3,
			),
			Post_Organizer::calculate_timed_event_tracks( $short, $three_hours ),
			'Failed to assert the event ends at the step after 08:50 (09:30), not at 08:45.'
		);

		$config = array(
			'start_hour'   => 10,
			'end_hour'     => 12,
			'quarter_mins' => 0,
		);
		$this->assertSame(
			array(
				'row_start' => 1,
				'row_end'   => 71,
			),
			Post_Organizer::calculate_timed_event_tracks( $event_id, $config ),
			'Failed to assert tracks of at least 1 minute.'
		);
	}
}
