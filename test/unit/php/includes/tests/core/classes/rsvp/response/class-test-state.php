<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\State and Intent.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_State.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\State
 */
class Test_State extends Base {

	/**
	 * Build a State around a real comment with the given status/guests.
	 *
	 * @param Status $status Status for the state.
	 * @param int    $guests Guest count.
	 *
	 * @return State
	 */
	protected function make_state( Status $status, int $guests = 0 ): State {
		$comment_id = $this->factory->comment->create();
		$data       = new Data(
			new Identity( Identity_Type::EMAIL, 'state-test@example.test' ),
			$status,
			$guests
		);

		return new State( $data, new Email(), get_comment( $comment_id ) );
	}

	/**
	 * Status predicates reflect the underlying data.
	 *
	 * @covers ::__construct
	 * @covers ::is_attending
	 * @covers ::is_waiting_list
	 *
	 * @return void
	 */
	public function test_status_predicates(): void {
		$attending = $this->make_state( Status::ATTENDING );
		$this->assertTrue( $attending->is_attending() );
		$this->assertFalse( $attending->is_waiting_list() );

		$waiting = $this->make_state( Status::WAITING_LIST );
		$this->assertFalse( $waiting->is_attending() );
		$this->assertTrue( $waiting->is_waiting_list() );
	}

	/**
	 * The attendee count is the responder plus their guests.
	 *
	 * @covers ::get_attendee_count
	 *
	 * @return void
	 */
	public function test_get_attendee_count_includes_guests(): void {
		$this->assertSame( 1, $this->make_state( Status::ATTENDING )->get_attendee_count() );
		$this->assertSame( 3, $this->make_state( Status::ATTENDING, 2 )->get_attendee_count() );
	}

	/**
	 * Intent::attend produces an attending intent that keeps the state's
	 * provider and remaining data fields.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Intent::__construct
	 * @covers \GatherPress\Core\Rsvp\Response\Intent::attend
	 *
	 * @return void
	 */
	public function test_intent_attend_promotes_state(): void {
		$state  = $this->make_state( Status::WAITING_LIST, 2 );
		$intent = Intent::attend( $state );

		$this->assertSame( Status::ATTENDING, $intent->data->status );
		$this->assertSame( 2, $intent->data->guests );
		$this->assertSame( $state->provider, $intent->provider );
		$this->assertSame( $state->data->identity, $intent->data->identity );
	}
}
