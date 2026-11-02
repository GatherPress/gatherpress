<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Post_Organizer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use DateTimeImmutable;
use GatherPress\Core\Calendar\Date_Range;
use GatherPress\Core\Calendar\Post_Dates;
use GatherPress\Core\Calendar\Post_Organizer;
use GatherPress\Core\Calendar\Time_Grid;
use GatherPress\Core\Calendar\Week;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Post_Organizer.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Post_Organizer
 */
class Test_Post_Organizer extends Base {

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
	 * Build an organizer for a visible range.
	 *
	 * @param string         $start     First visible day (Y-m-d).
	 * @param string         $end       Last visible day (Y-m-d).
	 * @param Time_Grid|null $time_grid Time grid, off by default.
	 *
	 * @return Post_Organizer
	 */
	private function get_organizer( string $start, string $end, ?Time_Grid $time_grid = null ): Post_Organizer {
		$date_range = new Date_Range(
			'month',
			1,
			new DateTimeImmutable( $start, wp_timezone() ),
			new DateTimeImmutable( $end . ' 23:59:59', wp_timezone() ),
			new Week()
		);

		return new Post_Organizer( $date_range, $time_grid ?? new Time_Grid() );
	}

	/**
	 * Get the span of a post, like get_spans() builds it.
	 *
	 * @param Post_Organizer $organizer Organizer with the visible range.
	 * @param int            $post_id   Post ID.
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_span( Post_Organizer $organizer, int $post_id ): ?array {
		return Utility::invoke_hidden_method( $organizer, 'get_span', array( new Post_Dates( $post_id ) ) );
	}

	/**
	 * Coverage for organize with posts.
	 *
	 * @covers ::__construct
	 * @covers ::organize
	 * @covers ::get_spans
	 * @covers ::get_span
	 *
	 * @return void
	 */
	public function test_organize(): void {
		$post_id   = $this->mock->post( array( 'post_date' => '2026-10-15 10:00:00' ) )->get()->ID;
		$organizer = $this->get_organizer( '2026-10-01', '2026-10-31' );

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
			$organizer->organize( array( $post_id, PHP_INT_MAX ) ),
			'Failed to assert a post uses its publish date, and a missing post is skipped.'
		);
		$this->assertSame( array(), $organizer->organize( array() ), 'Failed to assert no posts give no entries.' );
	}

	/**
	 * A long event shows on the visible days, also when it started more than a year before them.
	 *
	 * @covers ::organize
	 * @covers ::get_span
	 * @covers ::allocate_ribbon_entry
	 *
	 * @return void
	 */
	public function test_organize_clips_long_event_to_visible_range(): void {
		$event_id = $this->create_event( '2025-01-01 10:00:00', '2026-12-31 16:00:00' );
		$entries  = $this->get_organizer( '2026-10-01', '2026-10-31' )->organize( array( $event_id ) );

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

		$entries = $this->get_organizer( '2025-01-01', '2026-12-31' )->organize( array( $event_id ) );

		$this->assertCount(
			Post_Organizer::MAX_EVENT_SPAN_DAYS,
			$entries,
			'Failed to assert the day limit of a range longer than the largest view.'
		);
		$this->assertSame( 'start', $entries['2025-01-01'][0]['span'] );
	}

	/**
	 * Coverage for get_span.
	 *
	 * @covers ::get_span
	 *
	 * @return void
	 */
	public function test_get_span(): void {
		$october = $this->get_organizer( '2026-10-01', '2026-10-31' );
		$inside  = $this->get_span( $october, $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' ) );

		$this->assertSame( '2026-10-16', $inside['start']->format( 'Y-m-d' ) );
		$this->assertSame( 5, $inside['total_days'] );
		$this->assertSame( 0, $inside['first_day'] );
		$this->assertSame( 5, $inside['shown_days'], 'Failed to assert an event inside the range is not clipped.' );

		$long = $this->get_span( $october, $this->create_event( '2025-01-01 10:00:00', '2026-12-31 16:00:00' ) );

		$this->assertSame( 730, $long['total_days'], 'Failed to assert the full length of a long event.' );
		$this->assertSame( 638, $long['first_day'], 'Failed to assert the first visible day is October 1.' );
		$this->assertSame( 31, $long['shown_days'], 'Failed to assert the event ends after the visible range.' );

		$this->assertNull(
			$this->get_span( $october, $this->create_event( '2026-09-01 10:00:00', '2026-09-30 10:00:00' ) ),
			'Failed to assert an event before the range gives no span.'
		);
		$this->assertNull(
			$this->get_span( $october, $this->create_event( '2026-11-01 10:00:00', '2026-11-02 10:00:00' ) ),
			'Failed to assert an event after the range gives no span.'
		);
		$this->assertNull( $this->get_span( $october, PHP_INT_MAX ), 'Failed to assert a post without dates.' );
	}

	/**
	 * A day where daylight saving time starts at midnight still counts as a day.
	 *
	 * In America/Santiago the clocks go from 00:00 to 01:00 on 2026-09-06, so that day has 23 hours.
	 *
	 * @covers ::__construct
	 * @covers ::get_span
	 *
	 * @return void
	 */
	public function test_get_span_across_midnight_dst_start(): void {
		update_option( 'timezone_string', 'America/Santiago' );

		$two_days = $this->create_event( '2026-09-06 10:00:00', '2026-09-07 10:00:00' );
		$span     = $this->get_span( $this->get_organizer( '2026-09-01', '2026-09-30' ), $two_days );

		$this->assertSame( 2, $span['total_days'], 'Failed to assert the 23-hour day counts.' );

		$three_days = $this->create_event( '2026-09-06 10:00:00', '2026-09-08 10:00:00' );
		$span       = $this->get_span( $this->get_organizer( '2026-09-07', '2026-09-30' ), $three_days );

		$this->assertSame( 3, $span['total_days'] );
		$this->assertSame( 1, $span['first_day'] );
		$this->assertSame( 2, $span['shown_days'], 'Failed to assert September 8 is still shown.' );
	}

	/**
	 * Coverage for sort_spans.
	 *
	 * @covers ::sort_spans
	 *
	 * @return void
	 */
	public function test_sort_spans(): void {
		$organizer = $this->get_organizer( '2026-10-01', '2026-10-31' );
		$first     = $this->create_event( '2026-10-14 10:00:00', '2026-10-14 11:00:00' );
		$two_days  = $this->create_event( '2026-10-16 10:00:00', '2026-10-17 11:00:00' );
		$later     = $this->create_event( '2026-10-15 10:00:00', '2026-10-15 11:00:00' );
		$all_day   = $this->create_event( '2026-10-15 00:00:00', '2026-10-15 23:59:59', true );
		$five_days = $this->create_event( '2026-10-14 10:00:00', '2026-10-18 11:00:00' );
		$second    = $this->create_event( '2026-10-14 12:00:00', '2026-10-14 13:00:00' );
		$spans     = array();

		foreach ( array( $later, $two_days, $second, $five_days, $all_day, $first ) as $post_id ) {
			$spans[] = $this->get_span( $organizer, $post_id );
		}

		$sorted = Utility::invoke_hidden_method( $organizer, 'sort_spans', array( $spans ) );

		$this->assertSame(
			array( $five_days, $two_days, $all_day, $first, $second, $later ),
			array_map(
				static function ( array $span ): int {
					return $span['dates']->post_id;
				},
				$sorted
			),
			'Failed to assert long, then all-day, then by start date, then by ID.'
		);
	}

	/**
	 * Coverage for the slot helpers.
	 *
	 * @covers ::find_available_slot
	 * @covers ::is_slot_taken
	 * @covers ::get_span_type
	 *
	 * @return void
	 */
	public function test_slot_helpers(): void {
		$organizer = $this->get_organizer( '2026-10-01', '2026-10-31' );

		Utility::set_and_get_hidden_property(
			$organizer,
			'occupied_slots',
			array(
				'2026-10-15' => array( 0 => true ),
				'2026-10-16' => array( 1 => true ),
			)
		);

		$this->assertSame(
			2,
			Utility::invoke_hidden_method(
				$organizer,
				'find_available_slot',
				array( array( '2026-10-15', '2026-10-16' ) )
			)
		);
		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $organizer, 'find_available_slot', array( array( '2026-10-17' ) ) )
		);

		$this->assertSame( 'single', Utility::invoke_hidden_method( $organizer, 'get_span_type', array( 1, 1 ) ) );
		$this->assertSame( 'single', Utility::invoke_hidden_method( $organizer, 'get_span_type', array( 1, 0 ) ) );
		$this->assertSame( 'start', Utility::invoke_hidden_method( $organizer, 'get_span_type', array( 1, 3 ) ) );
		$this->assertSame( 'middle', Utility::invoke_hidden_method( $organizer, 'get_span_type', array( 2, 3 ) ) );
		$this->assertSame( 'end', Utility::invoke_hidden_method( $organizer, 'get_span_type', array( 3, 3 ) ) );
	}

	/**
	 * Coverage for organize without a time grid.
	 *
	 * @covers ::organize
	 * @covers ::allocate_ribbon_entry
	 *
	 * @return void
	 */
	public function test_organize_ribbons(): void {
		$five_days = $this->create_event( '2026-10-14 10:00:00', '2026-10-18 11:00:00' );
		$all_day   = $this->create_event( '2026-10-16 00:00:00', '2026-10-17 23:59:59', true );
		$single    = $this->create_event( '2026-10-14 10:00:00', '2026-10-14 11:00:00' );
		$entries   = $this->get_organizer( '2026-10-01', '2026-10-31' )->organize(
			array( $single, $all_day, $five_days )
		);

		$this->assertSame(
			array( '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17', '2026-10-18' ),
			array_keys( $entries )
		);
		$this->assertSame(
			array(
				'id'         => $five_days,
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
	 * @covers ::organize
	 *
	 * @return void
	 */
	public function test_organize_sorts_days_by_slot(): void {
		$x       = $this->create_event( '2026-10-05 10:00:00', '2026-10-06 11:00:00' );
		$y       = $this->create_event( '2026-10-06 10:00:00', '2026-10-07 11:00:00' );
		$z       = $this->create_event( '2026-10-07 10:00:00', '2026-10-07 11:00:00' );
		$entries = $this->get_organizer( '2026-10-01', '2026-10-31' )->organize( array( $x, $y, $z ) );

		$this->assertSame( array( $z, $y ), array_column( $entries['2026-10-07'], 'id' ) );
		$this->assertSame( array( 0, 1 ), array_column( $entries['2026-10-07'], 'slot' ) );
	}

	/**
	 * Coverage for organize with a time grid.
	 *
	 * @covers ::organize
	 * @covers ::allocate_timed_entry
	 * @covers ::resolve_timed_overlaps
	 *
	 * @return void
	 */
	public function test_organize_with_time_grid(): void {
		$ribbon    = $this->create_event( '2026-10-15 09:00:00', '2026-10-16 17:00:00' );
		$first     = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 11:00:00' );
		$second    = $this->create_event( '2026-10-16 10:30:00', '2026-10-16 12:00:00' );
		$third     = $this->create_event( '2026-10-16 13:00:00', '2026-10-16 14:00:00' );
		$post_id   = $this->mock->post( array( 'post_date' => '2026-10-16 10:00:00' ) )->get()->ID;
		$organizer = $this->get_organizer( '2026-10-12', '2026-10-18', new Time_Grid( true, 8, 20 ) );
		$entries   = $organizer->organize( array( $third, $post_id, $second, $ribbon, $first ) );
		$day       = $entries['2026-10-16'];

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

		$this->assertSame(
			$entries,
			$organizer->organize( array( $third, $post_id, $second, $ribbon, $first ) ),
			'Failed to assert organizing again starts over.'
		);
	}

	/**
	 * Events that overlap through other events never share a column.
	 *
	 * B overlaps A and C, and C overlaps D, so the four events form one group of three columns.
	 *
	 * @covers ::resolve_timed_overlaps
	 * @covers ::assign_overlap_group
	 *
	 * @return void
	 */
	public function test_resolve_timed_overlaps_assigns_columns_per_group(): void {
		$a         = $this->create_event( '2026-10-16 09:00:00', '2026-10-16 10:00:00' );
		$b         = $this->create_event( '2026-10-16 09:30:00', '2026-10-16 10:30:00' );
		$c         = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 11:00:00' );
		$d         = $this->create_event( '2026-10-16 10:15:00', '2026-10-16 11:00:00' );
		$organizer = $this->get_organizer( '2026-10-16', '2026-10-16', new Time_Grid( true, 8, 20 ) );
		$columns   = array();

		foreach ( $organizer->organize( array( $d, $c, $b, $a ) )['2026-10-16'] as $entry ) {
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
}
