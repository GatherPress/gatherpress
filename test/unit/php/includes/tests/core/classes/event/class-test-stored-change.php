<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Stored_Change.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Logistics;
use GatherPress\Core\Event\Stored_Change;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Stored_Change.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Stored_Change
 */
class Test_Stored_Change extends Base {

	/**
	 * Logistics before a change.
	 *
	 * @return Logistics The logistics.
	 */
	private function before(): Logistics {
		return new Logistics( '2030-01-01 18:00:00', '2030-01-01 20:00:00', 'UTC', array(), array( 'scheduled' ) );
	}

	/**
	 * Logistics after a change to the time zone, the venue, and the status.
	 *
	 * @return Logistics The logistics.
	 */
	private function after(): Logistics {
		return new Logistics(
			'2030-01-01 18:00:00',
			'2030-01-01 20:00:00',
			'Europe/Paris',
			array(
				'_library'     => 'Library',
				'online-event' => 'Online event',
			),
			array( 'canceled' )
		);
	}

	/**
	 * A change survives the round trip through post meta, and other values hold no change.
	 *
	 * @covers ::__construct
	 * @covers ::from_meta
	 * @covers ::to_meta
	 *
	 * @return void
	 */
	public function test_meta_round_trip(): void {
		$change = new Stored_Change( array( 'venue' ), $this->before(), $this->after(), false );

		$this->assertEquals(
			$change,
			Stored_Change::from_meta( $change->to_meta() ),
			'Failed to assert the round trip.'
		);
		$this->assertNull( Stored_Change::from_meta( '' ), 'Failed to assert empty meta holds no change.' );
		$this->assertNull(
			Stored_Change::from_meta( array_merge( $change->to_meta(), array( 'keys' => array() ) ) ),
			'Failed to assert a change without keys is none.'
		);
		$this->assertNull(
			Stored_Change::from_meta( array_merge( $change->to_meta(), array( 'before' => 'x' ) ) ),
			'Failed to assert a change without logistics before is none.'
		);
		$this->assertNull(
			Stored_Change::from_meta( array_merge( $change->to_meta(), array( 'after' => null ) ) ),
			'Failed to assert a change without logistics after is none.'
		);
	}

	/**
	 * Only a date, time, or time zone change counts as a date change.
	 *
	 * @covers ::has_datetime_change
	 *
	 * @return void
	 */
	public function test_has_datetime_change(): void {
		$venue    = new Stored_Change( array( 'venue', 'status' ), $this->before(), $this->after() );
		$timezone = new Stored_Change( array( 'venue', 'timezone' ), $this->before(), $this->after() );

		$this->assertFalse( $venue->has_datetime_change(), 'Failed to assert a venue change is not a date change.' );
		$this->assertTrue( $timezone->has_datetime_change(), 'Failed to assert a time zone change is a date change.' );
	}

	/**
	 * Each kind of change has its own headline and message line.
	 *
	 * @covers ::headline
	 * @covers ::message
	 * @covers ::format_venue
	 *
	 * @return void
	 */
	public function test_headline_and_message_per_change(): void {
		$cases = array(
			'datetime_start' => array( 'The start date or time changed.', 'Start: was ' ),
			'datetime_end'   => array( 'The end date or time changed.', 'End: was ' ),
			'timezone'       => array( 'The time zone changed.', 'Time zone: was UTC, now Europe/Paris.' ),
			'venue'          => array( 'The venue changed.', 'Venue: was no venue, now Library, Online event.' ),
			'status'         => array(
				'The event status changed to Canceled.',
				'Status: was Scheduled, now Canceled.',
			),
		);

		foreach ( $cases as $key => $expected ) {
			$change = new Stored_Change( array( $key ), $this->before(), $this->after() );

			$this->assertSame( $expected[0], $change->headline(), "Failed to assert the headline for {$key}." );
			$this->assertStringStartsWith(
				$expected[1],
				$change->message(),
				"Failed to assert the message for {$key}."
			);
		}

		$several = new Stored_Change( array( 'timezone', 'venue' ), $this->before(), $this->after() );

		$this->assertSame(
			'Several event details changed.',
			$several->headline(),
			'Failed to assert the headline for several.'
		);
		$this->assertSame(
			"Time zone: was UTC, now Europe/Paris.\nVenue: was no venue, now Library, Online event.",
			$several->message(),
			'Failed to assert one line per change.'
		);
	}

	/**
	 * Venue names are plain text, without HTML entities.
	 *
	 * @covers ::format_venue
	 *
	 * @return void
	 */
	public function test_format_venue_decodes_entities(): void {
		$change = new Stored_Change( array( 'venue' ), $this->before(), $this->after() );

		$this->assertSame(
			'Bar & Grill',
			Utility::invoke_hidden_method( $change, 'format_venue', array( array( '_bar' => 'Bar &amp; Grill' ) ) ),
			'Failed to assert the venue name is decoded.'
		);
	}

	/**
	 * Dates format in the site's formats, and fall back when they cannot be read.
	 *
	 * @covers ::format_datetime
	 *
	 * @return void
	 */
	public function test_format_datetime(): void {
		$change = new Stored_Change( array( 'datetime_start' ), $this->before(), $this->after() );

		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i' );
		update_option( 'timezone_string', 'UTC' );

		$format = static fn( string $datetime, string $timezone ): string => Utility::invoke_hidden_method(
			$change,
			'format_datetime',
			array( $datetime, $timezone )
		);

		$this->assertSame( 'not set', $format( '', 'UTC' ), 'Failed to assert an empty date.' );
		$this->assertSame(
			'2030-01-01 18:00 CET',
			$format( '2030-01-01 18:00:00', 'Europe/Paris' ),
			'Failed to assert the time zone.'
		);
		$this->assertSame(
			'2030-01-01 18:00 UTC',
			$format( '2030-01-01 18:00:00', '' ),
			'Failed to assert the site time zone.'
		);
		$this->assertSame( 'not a date', $format( 'not a date', 'UTC' ), 'Failed to assert an unreadable date.' );
	}
}
