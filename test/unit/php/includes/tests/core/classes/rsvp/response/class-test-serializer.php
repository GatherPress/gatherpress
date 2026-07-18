<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Response\Serializer.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\Serializer;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Serializer.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Serializer
 */
class Test_Serializer extends Base {

	/**
	 * Build a user-provider State for the given user.
	 *
	 * @param int  $user_id   The responder's user ID.
	 * @param bool $anonymous Whether the response is anonymous.
	 *
	 * @return State
	 */
	protected function make_state( int $user_id, bool $anonymous ): State {
		$comment_id = $this->factory->comment->create( array( 'user_id' => $user_id ) );
		$data       = new Data(
			new Identity( Identity_Type::WP_USER_ID, $user_id ),
			Status::ATTENDING,
			1,
			$anonymous,
			'2026-01-02 03:04:05'
		);

		return new State( $data, new User(), get_comment( $comment_id ) );
	}

	/**
	 * A non-anonymous response serializes the responder's real identity.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_serializes_full_identity(): void {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Serializer Tester' ) );

		wp_set_current_user( 0 );

		$row = Serializer::to_array( $this->make_state( $user_id, false ) );

		$this->assertSame( 'Serializer Tester', $row['name'] );
		$this->assertSame( get_author_posts_url( $user_id ), $row['profile'] );
		$this->assertSame( $user_id, $row['user_id'] );
		$this->assertSame( 'attending', $row['status'] );
		$this->assertSame( 1, $row['guests'] );
		$this->assertFalse( $row['anonymous'] );
		$this->assertSame( '2026-01-02 03:04:05', $row['timestamp'] );
		$this->assertSame( 'user', $row['provider'] );
		$this->assertSame( $user_id, $row['identifier'] );
		$this->assertArrayHasKey( 'photo', $row );
		$this->assertArrayHasKey( 'role', $row );
	}

	/**
	 * An anonymous response is masked for viewers without edit_posts,
	 * but privileged viewers still see the real identity.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_masks_anonymous_for_unprivileged_viewers(): void {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Shy Tester' ) );
		$state   = $this->make_state( $user_id, true );

		wp_set_current_user( 0 );

		$masked = Serializer::to_array( $state );

		$this->assertSame( __( 'Anonymous', 'gatherpress' ), $masked['name'] );
		$this->assertSame( '', $masked['profile'] );
		$this->assertSame( 0, $masked['user_id'] );

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$unmasked = Serializer::to_array( $state );

		$this->assertSame( 'Shy Tester', $unmasked['name'], 'Privileged viewers see the real name.' );
		$this->assertSame( $user_id, $unmasked['user_id'] );

		wp_set_current_user( 0 );
	}
}
