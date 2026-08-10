<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Cache.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Rsvp\Cache;
use GatherPress\Tests\Base;

/**
 * Class Test_Cache.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Cache
 */
class Test_Cache extends Base {

	/**
	 * Set/get/delete round-trip, plus the non-array and empty guards on
	 * the read side.
	 *
	 * @covers ::get
	 * @covers ::set
	 * @covers ::delete
	 * @covers ::cache_key
	 *
	 * @return void
	 */
	public function test_round_trip_and_read_guards(): void {
		$post_id = 123456;

		$this->assertNull( Cache::get( $post_id ), 'A cold cache reads as null.' );

		Cache::set( $post_id, array( 'all' => array( 'count' => 2 ) ) );

		$this->assertSame(
			array( 'all' => array( 'count' => 2 ) ),
			Cache::get( $post_id ),
			'A stored array round-trips.'
		);

		Cache::delete( $post_id );

		$this->assertNull( Cache::get( $post_id ), 'Deleting empties the entry.' );

		Cache::set( $post_id, 'not-an-array' );
		$this->assertNull( Cache::get( $post_id ), 'Non-array values read as null.' );

		Cache::set( $post_id, array() );
		$this->assertNull( Cache::get( $post_id ), 'An empty array reads as null.' );

		Cache::delete( $post_id );
	}
}
