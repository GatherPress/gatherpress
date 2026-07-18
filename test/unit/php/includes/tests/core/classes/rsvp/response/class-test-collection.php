<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\Collection.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Collection;
use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Collection.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Collection
 */
class Test_Collection extends Base {

	/**
	 * Build a State with the given status, guests, and timestamp.
	 *
	 * @param Status $status    Status for the state.
	 * @param int    $guests    Guest count.
	 * @param string $timestamp MySQL-format timestamp.
	 *
	 * @return State
	 */
	protected function make_state( Status $status, int $guests, string $timestamp ): State {
		static $counter = 0;

		++$counter;

		$comment_id = $this->factory->comment->create();
		$data       = new Data(
			new Identity( Identity_Type::EMAIL, sprintf( 'collection-%d@example.test', $counter ) ),
			$status,
			$guests,
			false,
			$timestamp
		);

		return new State( $data, new Email(), get_comment( $comment_id ) );
	}

	/**
	 * An empty collection reports zeroes everywhere.
	 *
	 * @covers ::__construct
	 * @covers ::all
	 * @covers ::attending
	 * @covers ::waiting_list
	 * @covers ::attending_count
	 * @covers ::has_waiting_list
	 * @covers ::get_attendee_count
	 * @covers ::waiting_list_count
	 *
	 * @return void
	 */
	public function test_empty_collection(): void {
		$empty = new Collection();

		$this->assertSame( array(), $empty->all() );
		$this->assertSame( array(), $empty->attending() );
		$this->assertSame( array(), $empty->waiting_list() );
		$this->assertSame( 0, $empty->attending_count() );
		$this->assertFalse( $empty->has_waiting_list() );
		$this->assertSame( 0, $empty->get_attendee_count() );
		$this->assertSame( 0, $empty->waiting_list_count() );
	}

	/**
	 * Filtering, counting, and waiting-list ordering over a mixed set.
	 *
	 * @covers ::all
	 * @covers ::attending
	 * @covers ::waiting_list
	 * @covers ::attending_count
	 * @covers ::has_waiting_list
	 * @covers ::get_attendee_count
	 * @covers ::waiting_list_count
	 *
	 * @return void
	 */
	public function test_mixed_collection_counts_and_ordering(): void {
		$attending_solo   = $this->make_state( Status::ATTENDING, 0, '2026-01-01 10:00:00' );
		$attending_guests = $this->make_state( Status::ATTENDING, 2, '2026-01-01 11:00:00' );
		$waiting_late     = $this->make_state( Status::WAITING_LIST, 0, '2026-01-03 10:00:00' );
		$waiting_early    = $this->make_state( Status::WAITING_LIST, 0, '2026-01-02 10:00:00' );
		$not_attending    = $this->make_state( Status::NOT_ATTENDING, 0, '2026-01-01 12:00:00' );

		$collection = new Collection(
			array( $attending_solo, $attending_guests, $waiting_late, $waiting_early, $not_attending )
		);

		$this->assertCount( 5, $collection->all() );
		$this->assertSame( array( $attending_solo, $attending_guests ), $collection->attending() );
		$this->assertSame( 2, $collection->attending_count() );
		$this->assertTrue( $collection->has_waiting_list() );
		$this->assertSame( 2, $collection->waiting_list_count() );
		$this->assertSame(
			array( $waiting_early, $waiting_late ),
			$collection->waiting_list(),
			'Waiting list orders by timestamp, earliest first.'
		);
		$this->assertSame(
			4,
			$collection->get_attendee_count(),
			'Two attending responders plus two guests.'
		);
	}

	/**
	 * Without a limit, available_spots reports the waiting-list count;
	 * with a limit it reports the remaining attending capacity, floored
	 * at zero.
	 *
	 * @covers ::available_spots
	 *
	 * @return void
	 */
	public function test_available_spots_branches(): void {
		$collection = new Collection(
			array(
				$this->make_state( Status::ATTENDING, 0, '2026-01-01 10:00:00' ),
				$this->make_state( Status::ATTENDING, 0, '2026-01-01 11:00:00' ),
				$this->make_state( Status::WAITING_LIST, 0, '2026-01-01 12:00:00' ),
			)
		);

		$this->assertSame( 1, $collection->available_spots( null ), 'No limit falls back to the waiting-list count.' );
		$this->assertSame( 3, $collection->available_spots( 5 ) );
		$this->assertSame( 0, $collection->available_spots( 1 ), 'Overfull events floor at zero.' );
	}
}
