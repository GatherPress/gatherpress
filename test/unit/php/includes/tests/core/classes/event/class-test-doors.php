<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Doors.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Doors;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Doors.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Doors
 */
class Test_Doors extends Base {

	/**
	 * Create an event with dates and doors times.
	 *
	 * @since TBD
	 *
	 * @param string $start       Local start datetime.
	 * @param string $end         Local end datetime.
	 * @param string $doors_open  Doors open time, or an empty string.
	 * @param string $doors_close Doors close time, or an empty string.
	 * @param bool   $all_day     Whether the event is all day.
	 *
	 * @return Event The event.
	 */
	private function create_doors_event(
		string $start,
		string $end,
		string $doors_open,
		string $doors_close,
		bool $all_day = false
	): Event {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_post_meta( $post_id, 'gatherpress_is_all_day', $all_day );
		update_post_meta( $post_id, 'gatherpress_doors_open', $doors_open );
		update_post_meta( $post_id, 'gatherpress_doors_close', $doors_close );

		$event = new Event( $post_id );
		$event->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'America/New_York',
			)
		);

		return new Event( $post_id );
	}

	/**
	 * Doors open on the start date and close on the end date.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_on_the_event_dates(): void {
		$event = $this->create_doors_event( '2026-11-20 20:00:00', '2026-11-20 23:00:00', '19:00', '23:30' );
		$doors = new Doors( $event );

		$this->assertSame(
			'2026-11-20T19:00:00-05:00',
			$doors->get_datetime( 'open' )->format( 'c' ),
			'Failed to assert the doors open on the start date, in the event timezone.'
		);
		$this->assertSame(
			'2026-11-20T23:30:00-05:00',
			$doors->get_datetime( 'close' )->format( 'c' ),
			'Failed to assert the doors close on the end date.'
		);
	}

	/**
	 * A doors time on the other side of midnight moves to the day it belongs to.
	 *
	 * @since TBD
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_across_midnight(): void {
		$event = $this->create_doors_event( '2026-11-21 00:30:00', '2026-11-21 03:00:00', '23:30', '04:00' );
		$doors = new Doors( $event );

		$this->assertSame(
			'2026-11-20T23:30:00-05:00',
			$doors->get_datetime( 'open' )->format( 'c' ),
			'Failed to assert doors open later than the start means the day before.'
		);

		$event = $this->create_doors_event( '2026-11-20 20:00:00', '2026-11-20 23:00:00', '', '01:00' );
		$doors = new Doors( $event );

		$this->assertSame(
			'2026-11-21T01:00:00-05:00',
			$doors->get_datetime( 'close' )->format( 'c' ),
			'Failed to assert doors close earlier than the end means the day after.'
		);
	}

	/**
	 * An all-day event keeps its doors on the start and end dates.
	 *
	 * @since TBD
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_for_an_all_day_event(): void {
		$event = $this->create_doors_event( '2026-11-20 00:00:00', '2026-11-22 23:59:59', '10:00', '22:00', true );
		$doors = new Doors( $event );

		$this->assertSame(
			'2026-11-20T10:00:00-05:00',
			$doors->get_datetime( 'open' )->format( 'c' ),
			'Failed to assert an all-day event opens its doors on the start date.'
		);
		$this->assertSame(
			'2026-11-22T22:00:00-05:00',
			$doors->get_datetime( 'close' )->format( 'c' ),
			'Failed to assert an all-day event closes its doors on the end date.'
		);
	}

	/**
	 * No doors time, an invalid one, or no event dates gives no doors datetime.
	 *
	 * @since TBD
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime_without_a_time(): void {
		$event = $this->create_doors_event( '2026-11-20 20:00:00', '2026-11-20 23:00:00', '', '' );
		$doors = new Doors( $event );

		$this->assertNull( $doors->get_datetime( 'open' ), 'Failed to assert an unset time gives null.' );

		// A value stored before the sanitize callback existed, written past it.
		remove_all_filters( sprintf( 'sanitize_post_meta_gatherpress_doors_close_for_%s', Event::POST_TYPE ) );
		update_post_meta( $event->post->ID, 'gatherpress_doors_close', 'late' );

		$this->assertNull( $doors->get_datetime( 'close' ), 'Failed to assert an invalid time gives null.' );

		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_post_meta( $post_id, 'gatherpress_doors_open', '19:00' );
		delete_post_meta( $post_id, 'gatherpress_datetime_start' );

		$this->assertNull(
			( new Doors( new Event( $post_id ) ) )->get_datetime( 'open' ),
			'Failed to assert an event without a start date gives null.'
		);
		$this->assertNull(
			( new Doors( new Event( 0 ) ) )->get_datetime( 'open' ),
			'Failed to assert a missing post gives null.'
		);
	}

	/**
	 * Doors times are formatted with the site time format, in the viewer's
	 * timezone for a timed event and the event's own for an all-day one.
	 *
	 * @since TBD
	 *
	 * @covers ::get_time
	 *
	 * @return void
	 */
	public function test_get_time(): void {
		update_option( 'time_format', 'g:i a' );

		$event = $this->create_doors_event( '2026-11-20 20:00:00', '2026-11-20 23:00:00', '19:00', '' );
		$doors = new Doors( $event );

		$this->assertSame( '7:00 pm', $doors->get_time( 'open' ), 'Failed to assert the site format is used.' );
		$this->assertSame( '19:00', $doors->get_time( 'open', 'H:i' ), 'Failed to assert a format is used.' );
		$this->assertSame( '', $doors->get_time( 'close' ), 'Failed to assert an unset time is empty.' );

		$viewer_timezone = static fn (): string => 'America/Los_Angeles';

		add_filter( 'gatherpress_timezone', $viewer_timezone, 99 );

		$timed   = ( new Doors( new Event( $event->post->ID ) ) )->get_time( 'open' );
		$all_day = ( new Doors(
			$this->create_doors_event( '2026-11-20 00:00:00', '2026-11-20 23:59:59', '10:00', '', true )
		) )->get_time( 'open' );

		remove_filter( 'gatherpress_timezone', $viewer_timezone, 99 );

		$this->assertSame( '4:00 pm', $timed, 'Failed to assert a timed event follows the viewer timezone.' );
		$this->assertSame( '10:00 am', $all_day, 'Failed to assert an all-day event keeps the event timezone.' );
	}

	/**
	 * Doors on another day than the start or end they belong to show the date.
	 *
	 * @since TBD
	 *
	 * @covers ::get_time
	 *
	 * @return void
	 */
	public function test_get_time_on_another_day(): void {
		update_option( 'date_format', 'M j' );
		update_option( 'time_format', 'g:i a' );

		$event = $this->create_doors_event( '2026-11-21 00:30:00', '2026-11-21 03:00:00', '23:30', '04:00' );
		$doors = new Doors( $event );

		$this->assertSame( 'Nov 20 11:30 pm', $doors->get_time( 'open' ), 'Failed to assert the date shows.' );
		$this->assertSame( '4:00 am', $doors->get_time( 'close' ), 'Failed to assert only the time shows.' );
		$this->assertSame( '23:30', $doors->get_time( 'open', 'H:i' ), 'Failed to assert a format is kept.' );
	}

	/**
	 * A timezone string becomes a timezone, falling back to the site's.
	 *
	 * @since TBD
	 *
	 * @covers ::to_timezone
	 *
	 * @return void
	 */
	public function test_to_timezone(): void {
		$doors = new Doors( new Event( 0 ) );

		$this->assertSame(
			'America/New_York',
			Utility::invoke_hidden_method( $doors, 'to_timezone', array( 'America/New_York' ) )->getName(),
			'Failed to assert an IANA timezone is kept.'
		);
		$this->assertSame(
			'+05:00',
			Utility::invoke_hidden_method( $doors, 'to_timezone', array( 'UTC+5' ) )->getName(),
			'Failed to assert a UTC offset is converted.'
		);
		$this->assertSame(
			wp_timezone()->getName(),
			Utility::invoke_hidden_method( $doors, 'to_timezone', array( 'nowhere' ) )->getName(),
			'Failed to assert an unknown timezone falls back to the site timezone.'
		);
	}
}
