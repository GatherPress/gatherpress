<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Post_Dates.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Post_Dates;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;

/**
 * Class Test_Post_Dates.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Post_Dates
 */
class Test_Post_Dates extends Base {

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
	 * Get the dates and minutes of a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_dates( int $post_id ): array {
		$post_dates = new Post_Dates( $post_id );

		return array(
			'is_event'     => $post_dates->is_event,
			'is_all_day'   => $post_dates->is_all_day,
			'start_date'   => $post_dates->start_date,
			'end_date'     => $post_dates->end_date,
			'start_minute' => $post_dates->start_minute,
			'end_minute'   => $post_dates->end_minute,
		);
	}

	/**
	 * Coverage for the dates of posts that are not events.
	 *
	 * @covers ::__construct
	 * @covers ::resolve_end_date
	 * @covers ::resolve_end_minute
	 * @covers ::to_minutes
	 *
	 * @return void
	 */
	public function test_post_without_event_dates(): void {
		$post_id = $this->mock->post( array( 'post_date' => '2026-10-15 10:30:00' ) )->get()->ID;

		$this->assertSame(
			array(
				'is_event'     => false,
				'is_all_day'   => false,
				'start_date'   => '2026-10-15',
				'end_date'     => '2026-10-15',
				'start_minute' => 630,
				'end_minute'   => 690,
			),
			$this->get_dates( $post_id ),
			'Failed to assert a post uses its publish date.'
		);

		$no_dates = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->assertFalse(
			( new Post_Dates( $no_dates ) )->is_event,
			'Failed to assert no event dates without a start.'
		);
		$this->assertFalse( ( new Post_Dates( 0 ) )->is_event, 'Failed to assert no event dates for ID 0.' );
		$this->assertSame(
			'',
			( new Post_Dates( PHP_INT_MAX ) )->start_date,
			'Failed to assert no date without a post.'
		);
	}

	/**
	 * Coverage for the dates of events.
	 *
	 * @covers ::__construct
	 * @covers ::resolve_end_date
	 *
	 * @return void
	 */
	public function test_event_dates(): void {
		$post_dates = new Post_Dates( $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' ) );

		$this->assertTrue( $post_dates->is_event );
		$this->assertFalse( $post_dates->is_all_day );
		$this->assertSame( '2026-10-16', $post_dates->start_date );
		$this->assertSame( '2026-10-20', $post_dates->end_date );

		$midnight = new Post_Dates( $this->create_event( '2026-10-23 20:00:00', '2026-10-24 00:00:00' ) );

		$this->assertSame(
			'2026-10-23',
			$midnight->end_date,
			'Failed to assert an end at 00:00 ends on the day before.'
		);

		$all_day = new Post_Dates( $this->create_event( '2026-10-22 00:00:00', '2026-10-22 23:59:59', true ) );

		$this->assertTrue( $all_day->is_all_day );
		$this->assertSame( '2026-10-22', $all_day->end_date, 'Failed to assert an all-day event keeps its day.' );

		$no_end = $this->create_event( '2026-10-22 10:00:00', '2026-10-22 12:00:00' );
		delete_post_meta( $no_end, 'gatherpress_datetime_end' );

		$this->assertSame( '2026-10-22', ( new Post_Dates( $no_end ) )->end_date );

		$backwards = $this->create_event( '2026-10-22 10:00:00', '2026-10-22 12:00:00' );
		update_post_meta( $backwards, 'gatherpress_datetime_end', '2026-10-20 12:00:00' );

		$this->assertSame(
			'2026-10-22',
			( new Post_Dates( $backwards ) )->end_date,
			'Failed to assert an end before the start is ignored.'
		);
	}

	/**
	 * Coverage for the minutes of events.
	 *
	 * @covers ::__construct
	 * @covers ::resolve_end_minute
	 * @covers ::to_minutes
	 *
	 * @return void
	 */
	public function test_event_minutes(): void {
		$event = new Post_Dates( $this->create_event( '2026-10-16 10:30:00', '2026-10-16 12:00:00' ) );

		$this->assertSame( 630, $event->start_minute );
		$this->assertSame( 720, $event->end_minute );

		$multi_day = new Post_Dates( $this->create_event( '2026-10-16 10:00:00', '2026-10-20 16:00:00' ) );

		$this->assertSame( 600, $multi_day->start_minute );
		$this->assertSame( 960, $multi_day->end_minute );

		$midnight = new Post_Dates( $this->create_event( '2026-10-23 20:00:00', '2026-10-24 00:00:00' ) );

		$this->assertSame(
			Post_Dates::MINUTES_PER_DAY,
			$midnight->end_minute,
			'Failed to assert an end at 00:00 runs to the end of the day.'
		);

		$short = new Post_Dates( $this->create_event( '2026-10-16 10:00:00', '2026-10-16 10:05:00' ) );

		$this->assertSame( 615, $short->end_minute, 'Failed to assert a minimum length of 15 minutes.' );

		$no_end = $this->create_event( '2026-10-16 10:00:00', '2026-10-16 12:00:00' );
		delete_post_meta( $no_end, 'gatherpress_datetime_end' );

		$this->assertSame(
			660,
			( new Post_Dates( $no_end ) )->end_minute,
			'Failed to assert an event without an end lasts one hour.'
		);

		$late = $this->create_event( '2026-10-16 23:30:00', '2026-10-16 23:45:00' );
		delete_post_meta( $late, 'gatherpress_datetime_end' );

		$this->assertSame(
			Post_Dates::MINUTES_PER_DAY,
			( new Post_Dates( $late ) )->end_minute,
			'Failed to assert a default hour past midnight stops at the end of the day.'
		);
	}
}
