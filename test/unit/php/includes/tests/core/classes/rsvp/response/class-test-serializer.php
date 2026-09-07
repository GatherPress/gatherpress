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
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\Serializer;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings\Roles;
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
		// A real RSVP always carries the RSVP comment type, and its anonymity
		// lives in comment meta, so the fixture has to match for the masking
		// decision to see what it sees in production.
		$comment_id = $this->factory->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => Rsvp::COMMENT_TYPE,
			)
		);

		if ( $anonymous ) {
			update_comment_meta( $comment_id, Rsvp::ANONYMOUS_META_KEY, 1 );
		}

		$data = new Data(
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

		// The camelCase keys are the pre-existing responses() record
		// contract (block context mapping, editor JS); the snake_case
		// twins are the save() return contract. Both must survive.
		$this->assertSame( $row['comment_id'], $row['commentId'] );
		$this->assertSame( $row['post_id'], $row['postId'] );
		$this->assertSame( $row['user_id'], $row['userId'] );
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
		$this->assertSame( 0, $masked['userId'] );

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$unmasked = Serializer::to_array( $state );

		$this->assertSame( 'Shy Tester', $unmasked['name'], 'Privileged viewers see the real name.' );
		$this->assertSame( $user_id, $unmasked['user_id'] );

		wp_set_current_user( 0 );
	}

	/**
	 * The masked avatar URL carries no email hash, so it cannot be matched
	 * against the same responder's non-anonymous records.
	 *
	 * @covers ::to_array
	 * @covers ::anonymous_avatar_url
	 *
	 * @return void
	 */
	public function test_to_array_masks_avatar_url_for_anonymous_responses(): void {
		$user_id = $this->factory->user->create( array( 'user_email' => 'shy@example.test' ) );

		wp_set_current_user( 0 );

		$masked   = Serializer::to_array( $this->make_state( $user_id, true ) );
		$unmasked = Serializer::to_array( $this->make_state( $user_id, false ) );

		$this->assertNotSame(
			$unmasked['photo'],
			$masked['photo'],
			'An anonymous response must not reuse the identifying avatar URL.'
		);
		$this->assertSame(
			get_avatar_url( '', array( 'force_default' => true ) ),
			$masked['photo'],
			'The masked avatar is the identity-free default.'
		);
		$this->assertStringNotContainsString(
			md5( 'shy@example.test' ),
			(string) $masked['photo'],
			'The masked avatar must not embed an MD5 of the responder email.'
		);
		$this->assertStringNotContainsString(
			hash( 'sha256', 'shy@example.test' ),
			(string) $masked['photo'],
			'The masked avatar must not embed a SHA-256 of the responder email.'
		);
	}

	/**
	 * The raw identity value is withheld for anonymous responses so the
	 * responder's user ID is not disclosed alongside the masked name.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_masks_identifier_for_anonymous_responses(): void {
		$user_id = $this->factory->user->create();

		wp_set_current_user( 0 );

		$masked = Serializer::to_array( $this->make_state( $user_id, true ) );

		$this->assertSame( 0, $masked['identifier'] );
		$this->assertNotSame( $user_id, $masked['identifier'] );
	}

	/**
	 * The role is derived from the masked user ID, so an anonymous
	 * responder's site role is not disclosed.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_masks_role_for_anonymous_responses(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( 0 );

		$masked   = Serializer::to_array( $this->make_state( $user_id, true ) );
		$unmasked = Serializer::to_array( $this->make_state( $user_id, false ) );

		$this->assertSame(
			Roles::get_instance()->get_user_role( 0 ),
			$masked['role'],
			'The masked role is the one an unknown user would report.'
		);
		$this->assertSame(
			Roles::get_instance()->get_user_role( $user_id ),
			$unmasked['role'],
			'A non-anonymous response still reports the real role.'
		);
	}

	/**
	 * Privileged viewers keep the unmasked avatar, identifier, and role, so
	 * the admin-facing attendee views are unaffected by the masking.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_preserves_identity_fields_for_privileged_viewers(): void {
		$user_id = $this->factory->user->create();
		$state   = $this->make_state( $user_id, true );

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$unmasked = Serializer::to_array( $state );

		$this->assertSame( $user_id, $unmasked['identifier'] );
		$this->assertSame( get_avatar_url( $user_id ), $unmasked['photo'] );
		$this->assertSame( Roles::get_instance()->get_user_role( $user_id ), $unmasked['role'] );

		wp_set_current_user( 0 );
	}

	/**
	 * An email response reports the name it was saved with, and keeps the
	 * address itself for whoever manages RSVPs.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_withholds_email_identity(): void {
		$email      = 'guest@example.test';
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => 'Guest Name',
				'comment_author_email' => $email,
				'user_id'              => 0,
			)
		);
		$identity   = new Identity( Identity_Type::EMAIL, $email );

		$identity->display_name = 'Guest Name';

		$state = new State(
			new Data( $identity, Status::ATTENDING, 0, false, '2026-01-02 03:04:05' ),
			new Email(),
			get_comment( $comment_id )
		);

		wp_set_current_user( 0 );

		$public = Serializer::to_array( $state );

		$this->assertSame( 'Guest Name', $public['name'], 'The saved name is reported, not the address.' );
		$this->assertSame( 0, $public['identifier'], 'The address is withheld from the public.' );
		$this->assertStringNotContainsString(
			$email,
			wp_json_encode( $public ),
			'The address appears nowhere in a public record.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			$email,
			Serializer::to_array( $state )['identifier'],
			'Whoever manages RSVPs still sees the address.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * A responder identified only by an address has no name to show, so the
	 * record reports the address to whoever manages RSVPs and a generic label
	 * to everyone else.
	 *
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_to_array_names_a_responder_with_no_name(): void {
		$email      = 'no-name-to-show@example.test';
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'comment_author'       => '',
				'comment_author_email' => $email,
				'user_id'              => 0,
			)
		);
		$state      = new State(
			new Data(
				new Identity( Identity_Type::EMAIL, $email ),
				Status::ATTENDING,
				0,
				false,
				'2026-01-02 03:04:05'
			),
			new Email(),
			get_comment( $comment_id )
		);

		wp_set_current_user( 0 );

		$public = Serializer::to_array( $state );

		$this->assertSame( __( 'Attendee', 'gatherpress' ), $public['name'] );
		$this->assertStringNotContainsString(
			$email,
			wp_json_encode( $public ),
			'The address appears nowhere in a public record.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			$email,
			Serializer::to_array( $state )['name'],
			'Whoever manages RSVPs sees the address the response was saved with.'
		);

		wp_set_current_user( 0 );
	}
}
