<?php
/**
 * Unit tests for the RSVP response providers: Base, User, Email.
 *
 * @package GatherPress\Core\Rsvp\Response\Provider
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response\Provider;

use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Tests\Base;

/**
 * Class Test_Providers.
 *
 * Covers the two concrete providers plus the branchy helpers they
 * inherit from the abstract Base.
 */
class Test_Providers extends Base {

	/**
	 * Static descriptors for both core providers.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\User::get_slug
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\User::get_identity_type
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\User::get_label
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Email::get_slug
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Email::get_identity_type
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Email::get_label
	 *
	 * @return void
	 */
	public function test_static_descriptors(): void {
		$this->assertSame( 'user', User::get_slug() );
		$this->assertSame( Identity_Type::WP_USER_ID, User::get_identity_type() );
		$this->assertNotEmpty( User::get_label() );

		$this->assertSame( 'email', Email::get_slug() );
		$this->assertSame( Identity_Type::EMAIL, Email::get_identity_type() );
		$this->assertNotEmpty( Email::get_label() );
	}

	/**
	 * User display names come from the account; a vanished account yields
	 * an empty string. The user profile URL comes from the author archive.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\User::get_display_name
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\User::get_url
	 *
	 * @return void
	 */
	public function test_user_provider_display_name_and_url(): void {
		$user_id = $this->factory->user->create( array( 'display_name' => 'Provider Tester' ) );
		$user    = new User();

		$identity = new Identity( Identity_Type::WP_USER_ID, $user_id );

		$this->assertSame( 'Provider Tester', $user->get_display_name( $identity ) );
		$this->assertSame( get_author_posts_url( $user_id ), $user->get_url( $identity ) );

		// The identity validated at construction, but the account can
		// vanish afterwards — the display name degrades to empty.
		wp_delete_user( $user_id );

		$this->assertSame( '', $user->get_display_name( $identity ) );
	}

	/**
	 * Email display names are the sanitized address.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Email::get_display_name
	 *
	 * @return void
	 */
	public function test_email_provider_display_name(): void {
		$identity = new Identity( Identity_Type::EMAIL, 'display@example.test' );

		$this->assertSame( 'display@example.test', ( new Email() )->get_display_name( $identity ) );
	}

	/**
	 * The inherited avatar helper resolves user IDs and email addresses
	 * and returns null for identities Gravatar can't serve.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Base::get_avatar_url
	 *
	 * @return void
	 */
	public function test_base_get_avatar_url_branches(): void {
		$user_id = $this->factory->user->create();
		$email   = new Email();

		$this->assertIsString(
			( new User() )->get_avatar_url( new Identity( Identity_Type::WP_USER_ID, $user_id ) ),
			'User IDs resolve to an avatar URL.'
		);
		$this->assertIsString(
			$email->get_avatar_url( new Identity( Identity_Type::EMAIL, 'avatar@example.test' ) ),
			'Email addresses resolve to an avatar URL.'
		);
		$this->assertNull(
			$email->get_avatar_url( new Identity( Identity_Type::EXTERNAL_ID, 42 ) ),
			'External IDs have no avatar source.'
		);
	}

	/**
	 * The inherited URL helper passes through URL-shaped identity values
	 * and returns null otherwise.
	 *
	 * @covers \GatherPress\Core\Rsvp\Response\Provider\Base::get_url
	 *
	 * @return void
	 */
	public function test_base_get_url_branches(): void {
		$email = new Email();

		$this->assertSame(
			'https://example.test/me',
			$email->get_url( new Identity( Identity_Type::URL, 'https://example.test/me' ) ),
			'URL identities pass their value through.'
		);
		$this->assertNull(
			$email->get_url( new Identity( Identity_Type::EMAIL, 'no-url@example.test' ) ),
			'Non-URL identities have no profile URL.'
		);
	}
}
