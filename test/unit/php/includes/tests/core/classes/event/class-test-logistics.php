<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Logistics.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Logistics;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Logistics.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Logistics
 */
class Test_Logistics extends Base {

	/**
	 * Build logistics with a start, an end, a time zone, venues and a status.
	 *
	 * @param array<string, string> $venue  Venue term names keyed by slug.
	 * @param string[]              $status Status slugs.
	 *
	 * @return Logistics The logistics.
	 */
	private function logistics( array $venue = array(), array $status = array( 'scheduled' ) ): Logistics {
		return new Logistics( '2030-01-01 18:00:00', '2030-01-01 20:00:00', 'UTC', $venue, $status );
	}

	/**
	 * Logistics read from an event match what is stored, with the venues sorted by slug.
	 *
	 * @covers ::__construct
	 * @covers ::from_post
	 *
	 * @return void
	 */
	public function test_from_post(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$park     = wp_insert_term( 'Park', $taxonomy, array( 'slug' => '_park' ) );
		$hall     = wp_insert_term( 'Main Hall', $taxonomy, array( 'slug' => '_main-hall' ) );

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2030-01-01 18:00:00',
				'datetime_end'   => '2030-01-01 20:00:00',
				'timezone'       => 'Europe/Paris',
			)
		);
		wp_set_object_terms( $post_id, array( $park['term_id'], $hall['term_id'] ), $taxonomy );
		( new Status( $post_id ) )->set( 'postponed' );

		$logistics = Logistics::from_post( $post_id );

		$this->assertSame( '2030-01-01 18:00:00', $logistics->datetime_start, 'Failed to assert the start.' );
		$this->assertSame( '2030-01-01 20:00:00', $logistics->datetime_end, 'Failed to assert the end.' );
		$this->assertSame( 'Europe/Paris', $logistics->timezone, 'Failed to assert the time zone.' );
		$this->assertSame(
			array(
				'_main-hall' => 'Main Hall',
				'_park'      => 'Park',
			),
			$logistics->venue,
			'Failed to assert the venues are sorted by slug.'
		);
		$this->assertSame( array( 'postponed' ), $logistics->status, 'Failed to assert the status.' );
	}

	/**
	 * Logistics survive the round trip through an array, and missing values read as empty.
	 *
	 * @covers ::from_array
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_array_round_trip(): void {
		$logistics = $this->logistics( array( '_park' => 'Park' ), array( 'canceled' ) );

		$this->assertEquals(
			$logistics,
			Logistics::from_array( $logistics->to_array() ),
			'Failed to assert the round trip.'
		);

		$empty = Logistics::from_array( array() );

		$this->assertSame( '', $empty->datetime_start, 'Failed to assert a missing start reads as empty.' );
		$this->assertSame( array(), $empty->venue, 'Failed to assert missing venues read as none.' );
		$this->assertSame( array(), $empty->status, 'Failed to assert a missing status reads as none.' );
	}

	/**
	 * The copies change only the venue or the status, and sort the venues.
	 *
	 * @covers ::with_venue
	 * @covers ::with_status
	 *
	 * @return void
	 */
	public function test_with_venue_and_status(): void {
		$logistics = $this->logistics();
		$venue     = $logistics->with_venue(
			array(
				'_park'      => 'Park',
				'_main-hall' => 'Main Hall',
			)
		);
		$status    = $logistics->with_status( array( 'canceled' ) );

		$this->assertSame(
			array( '_main-hall', '_park' ),
			array_keys( $venue->venue ),
			'Failed to assert the venues are sorted.'
		);
		$this->assertSame( $logistics->status, $venue->status, 'Failed to assert the status is kept.' );
		$this->assertSame( array( 'canceled' ), $status->status, 'Failed to assert the new status.' );
		$this->assertSame( array(), $status->venue, 'Failed to assert the venue is kept.' );
		$this->assertSame( array( 'scheduled' ), $logistics->status, 'Failed to assert the original is unchanged.' );
	}

	/**
	 * Changed keys come in the order of `KEYS`, and a renamed venue is not a change.
	 *
	 * @covers ::changed_keys
	 *
	 * @return void
	 */
	public function test_changed_keys(): void {
		$before = $this->logistics( array( '_park' => 'Park' ) );

		$this->assertSame( array(), $before->changed_keys( $before ), 'Failed to assert no change.' );
		$this->assertSame(
			array(),
			$before->changed_keys( $before->with_venue( array( '_park' => 'City Park' ) ) ),
			'Failed to assert a renamed venue is not a change.'
		);

		$after = new Logistics(
			'2030-01-02 18:00:00',
			'2030-01-01 20:00:00',
			'Europe/Paris',
			array( '_hall' => 'Hall' ),
			array( 'canceled' )
		);

		$this->assertSame(
			array( 'datetime_start', 'timezone', 'venue', 'status' ),
			$before->changed_keys( $after ),
			'Failed to assert the changed keys in order.'
		);
	}
}
