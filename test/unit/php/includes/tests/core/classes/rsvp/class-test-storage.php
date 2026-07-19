<?php
/**
 * Unit tests for GatherPress\Core\Rsvp\Storage.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Storage;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Storage.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Storage
 */
class Test_Storage extends Base {

	/**
	 * Create an event post and its Storage.
	 *
	 * @return array{0: int, 1: Storage} Event ID and storage.
	 */
	protected function make_storage(): array {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		return array( $event_id, new Storage( $event_id ) );
	}

	/**
	 * Build a user-identity attending intent.
	 *
	 * @param int    $user_id   The user.
	 * @param Status $status    Status to request.
	 * @param int    $guests    Guest count.
	 * @param bool   $anonymous Anonymous flag.
	 *
	 * @return Intent
	 */
	protected function user_intent( int $user_id, Status $status, int $guests = 0, bool $anonymous = false ): Intent {
		return new Intent(
			new Data( new Identity( Identity_Type::WP_USER_ID, $user_id ), $status, $guests, $anonymous ),
			new User()
		);
	}

	/**
	 * Saving a new user RSVP inserts an approved comment, stamps the
	 * status term and meta, and returns a hydrated State.
	 *
	 * @covers ::__construct
	 * @covers ::save
	 * @covers ::hydrate
	 * @covers ::hydrate_data
	 * @covers ::get_status
	 * @covers ::get_value_from_object_terms
	 * @covers ::add_identity_comment_data
	 *
	 * @return void
	 */
	public function test_save_inserts_user_rsvp(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$user_id = $this->factory->user->create( array( 'display_name' => 'Storage Tester' ) );
		$state   = $storage->save( $this->user_intent( $user_id, Status::ATTENDING, 2, true ), null );

		$this->assertInstanceOf( State::class, $state );
		$this->assertSame( Status::ATTENDING, $state->data->status );
		$this->assertSame( 2, $state->data->guests );
		$this->assertTrue( $state->data->anonymous );
		$this->assertSame( $event_id, (int) $state->comment->comment_post_ID );
		$this->assertSame( Rsvp::COMMENT_TYPE, $state->comment->comment_type );
		$this->assertSame( 'Storage Tester', $state->comment->comment_author );

		$terms = wp_get_object_terms( (int) $state->comment->comment_ID, Status::TAXONOMY );
		$this->assertSame( 'attending', $terms[0]->slug );

		$provider_terms = wp_get_object_terms( (int) $state->comment->comment_ID, User::TAXONOMY );
		$this->assertSame( 'user', $provider_terms[0]->slug, 'The issuing provider is stamped as a term.' );
	}

	/**
	 * Updating an existing RSVP reuses the comment, and clearing guests
	 * and anonymity removes their meta rows.
	 *
	 * @covers ::save
	 *
	 * @return void
	 */
	public function test_save_updates_existing_rsvp(): void {
		list( , $storage ) = $this->make_storage();

		$user_id = $this->factory->user->create();
		$first   = $storage->save( $this->user_intent( $user_id, Status::ATTENDING, 2, true ), null );

		$comment_id = (int) $first->comment->comment_ID;
		$updated    = $storage->save(
			$this->user_intent( $user_id, Status::NOT_ATTENDING ),
			$comment_id
		);

		$this->assertSame( $comment_id, (int) $updated->comment->comment_ID, 'The comment is reused.' );
		$this->assertSame( Status::NOT_ATTENDING, $updated->data->status );
		$this->assertSame( '', get_comment_meta( $comment_id, 'gatherpress_rsvp_guests', true ) );
		$this->assertSame( '', get_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', true ) );
	}

	/**
	 * A no_status intent against an existing comment deletes the record;
	 * without a comment it inserts one (no_status only means removal for
	 * saved responses).
	 *
	 * @covers ::save
	 *
	 * @return void
	 */
	public function test_save_no_status_deletes_existing(): void {
		list( , $storage ) = $this->make_storage();

		$user_id    = $this->factory->user->create();
		$state      = $storage->save( $this->user_intent( $user_id, Status::ATTENDING ), null );
		$comment_id = (int) $state->comment->comment_ID;

		$result = $storage->save( $this->user_intent( $user_id, Status::NO_STATUS ), $comment_id );

		$this->assertNotFalse( $result, 'Deletion reports success.' );
		$this->assertNotInstanceOf( State::class, $result );

		// wp_delete_comment() without force trashes the comment; the
		// contract that matters is that the response stops resolving.
		$this->assertNull(
			$storage->get( new Identity( Identity_Type::WP_USER_ID, $user_id ) ),
			'The response no longer resolves after removal.'
		);
	}

	/**
	 * Get retrieves a saved response by identity, optionally passing the
	 * provider through to hydration, and returns null when nothing matches.
	 *
	 * @covers ::get
	 * @covers ::get_identity_query_args
	 *
	 * @return void
	 */
	public function test_get_by_identity(): void {
		list( , $storage ) = $this->make_storage();

		$user_id  = $this->factory->user->create();
		$identity = new Identity( Identity_Type::WP_USER_ID, $user_id );

		$this->assertNull( $storage->get( $identity ), 'No response yet.' );

		$storage->save( $this->user_intent( $user_id, Status::ATTENDING, 1 ), null );

		$found = $storage->get( $identity );
		$this->assertInstanceOf( State::class, $found );
		$this->assertSame( 1, $found->data->guests );

		$with_provider = $storage->get( $identity, new User() );
		$this->assertInstanceOf( State::class, $with_provider );
	}

	/**
	 * Email-identity responses save and load through the email provider,
	 * exercising the author-email mapping in both directions.
	 *
	 * @covers ::save
	 * @covers ::get
	 * @covers ::get_identity_query_args
	 * @covers ::add_identity_comment_data
	 *
	 * @return void
	 */
	public function test_email_identity_round_trip(): void {
		list( , $storage ) = $this->make_storage();

		$identity = new Identity( Identity_Type::EMAIL, 'storage-guest@example.test' );
		$intent   = new Intent( new Data( $identity, Status::ATTENDING ), new Email() );

		$state = $storage->save( $intent, null );

		$this->assertSame( 'storage-guest@example.test', $state->comment->comment_author_email );

		$found = $storage->get( new Identity( Identity_Type::EMAIL, 'storage-guest@example.test' ) );
		$this->assertInstanceOf( State::class, $found );
		$this->assertSame( Status::ATTENDING, $found->data->status );
	}

	/**
	 * All() hydrates every RSVP comment on the event.
	 *
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_all_returns_every_state(): void {
		list( , $storage ) = $this->make_storage();

		$storage->save( $this->user_intent( $this->factory->user->create(), Status::ATTENDING ), null );
		$storage->save( $this->user_intent( $this->factory->user->create(), Status::WAITING_LIST ), null );

		$states = $storage->all();

		$this->assertCount( 2, $states );
		$this->assertContainsOnlyInstancesOf( State::class, $states );
	}

	/**
	 * Hydration bails on non-RSVP comments and on comments whose identity
	 * cannot be resolved.
	 *
	 * @covers ::hydrate
	 * @covers ::get_identity_from_comment
	 *
	 * @return void
	 */
	public function test_hydrate_guards(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$plain_comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $event_id ) );

		$this->assertNull(
			Utility::invoke_hidden_method( $storage, 'hydrate', array( get_comment( $plain_comment_id ) ) ),
			'A non-RSVP comment never hydrates.'
		);

		// An RSVP-type comment with no user, no email, and no provider
		// term cannot resolve a provider.
		$orphan_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'user_id'              => 0,
				'comment_author_email' => '',
			)
		);

		$this->assertNull(
			Utility::invoke_hidden_method( $storage, 'hydrate', array( get_comment( $orphan_id ) ) ),
			'An RSVP comment without any resolvable provider never hydrates.'
		);

		// A comment resolving to the email provider but carrying a
		// malformed address fails identity construction.
		$bad_email_id = $this->factory->comment->create(
			array(
				'comment_post_ID'      => $event_id,
				'comment_type'         => Rsvp::COMMENT_TYPE,
				'user_id'              => 0,
				'comment_author_email' => 'not-an-email@',
			)
		);
		wp_set_object_terms( $bad_email_id, Email::get_slug(), Email::TAXONOMY );

		$this->assertNull(
			Utility::invoke_hidden_method( $storage, 'hydrate', array( get_comment( $bad_email_id ) ) ),
			'A resolvable provider with an invalid identity value never hydrates.'
		);
	}

	/**
	 * Provider resolution prefers the stored term and falls back to the
	 * comment's user ID, then its author email, then gives up.
	 *
	 * @covers ::get_identity_provider
	 *
	 * @return void
	 */
	public function test_get_identity_provider_fallbacks(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$user_comment = get_comment(
			$this->factory->comment->create(
				array(
					'comment_post_ID' => $event_id,
					'user_id'         => $this->factory->user->create(),
				)
			)
		);
		$this->assertInstanceOf(
			User::class,
			Utility::invoke_hidden_method( $storage, 'get_identity_provider', array( $user_comment ) ),
			'A user ID falls back to the user provider.'
		);

		$email_comment = get_comment(
			$this->factory->comment->create(
				array(
					'comment_post_ID'      => $event_id,
					'user_id'              => 0,
					'comment_author_email' => 'fallback@example.test',
				)
			)
		);
		$this->assertInstanceOf(
			Email::class,
			Utility::invoke_hidden_method( $storage, 'get_identity_provider', array( $email_comment ) ),
			'An author email falls back to the email provider.'
		);

		$orphan_comment = get_comment(
			$this->factory->comment->create(
				array(
					'comment_post_ID'      => $event_id,
					'user_id'              => 0,
					'comment_author_email' => '',
				)
			)
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $storage, 'get_identity_provider', array( $orphan_comment ) ),
			'No term, user, or email resolves no provider.'
		);
	}

	/**
	 * Identity extraction covers the URL and external-ID arms directly.
	 *
	 * @covers ::get_identity_from_comment
	 *
	 * @return void
	 */
	public function test_get_identity_from_comment_other_types(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$url_comment = get_comment(
			$this->factory->comment->create(
				array(
					'comment_post_ID'    => $event_id,
					'comment_author_url' => 'https://example.test/responder',
				)
			)
		);

		$url_identity = Utility::invoke_hidden_method(
			$storage,
			'get_identity_from_comment',
			array( $url_comment, Identity_Type::URL )
		);
		$this->assertSame( 'https://example.test/responder', $url_identity->value );

		// Comment meta always returns strings, so the int-typed external
		// identity cannot currently hydrate — document the behavior.
		$external_comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $event_id ) );
		update_comment_meta( $external_comment_id, 'gatherpress_rsvp_external_id', 42 );

		$this->assertNull(
			Utility::invoke_hidden_method(
				$storage,
				'get_identity_from_comment',
				array( get_comment( $external_comment_id ), Identity_Type::EXTERNAL_ID )
			),
			'External IDs read back as strings and fail the int-typed identity.'
		);
	}

	/**
	 * The identity-to-query and identity-to-comment-data mappers cover
	 * the URL and external-ID arms directly.
	 *
	 * @covers ::get_identity_query_args
	 * @covers ::add_identity_comment_data
	 *
	 * @return void
	 */
	public function test_identity_mappers_other_types(): void {
		list( , $storage ) = $this->make_storage();

		$url_identity      = new Identity( Identity_Type::URL, 'https://example.test/responder' );
		$external_identity = new Identity( Identity_Type::EXTERNAL_ID, 42 );

		$this->assertSame(
			array( 'author_url' => 'https://example.test/responder' ),
			Utility::invoke_hidden_method( $storage, 'get_identity_query_args', array( $url_identity ) )
		);
		$this->assertSame(
			array( 'comment_meta' => array( 'gatherpress_rsvp_external_id' => 42 ) ),
			Utility::invoke_hidden_method( $storage, 'get_identity_query_args', array( $external_identity ) )
		);

		$this->assertSame(
			array( 'comment_author_url' => 'https://example.test/responder' ),
			Utility::invoke_hidden_method( $storage, 'add_identity_comment_data', array( array(), $url_identity ) )
		);
		$this->assertSame(
			array( 'comment_meta' => array( 'gatherpress_rsvp_external_id' => 42 ) ),
			Utility::invoke_hidden_method( $storage, 'add_identity_comment_data', array( array(), $external_identity ) )
		);
	}

	/**
	 * A comment with no status term hydrates as NO_STATUS.
	 *
	 * @covers ::get_status
	 *
	 * @return void
	 */
	public function test_get_status_defaults_to_no_status(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $event_id ) );

		$this->assertSame(
			Status::NO_STATUS,
			Utility::invoke_hidden_method( $storage, 'get_status', array( $comment_id ) )
		);
	}

	/**
	 * A stored provider term wins over the user/email fallbacks, and the
	 * term-value reader returns null for termless objects — both invoked
	 * directly per the same-class-delegation coverage rule.
	 *
	 * @covers ::get_identity_provider
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_get_identity_provider_prefers_stored_term(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $event_id,
				'user_id'         => 0,
			)
		);
		wp_set_object_terms( $comment_id, User::get_slug(), User::TAXONOMY );

		$this->assertInstanceOf(
			User::class,
			Utility::invoke_hidden_method( $storage, 'get_identity_provider', array( get_comment( $comment_id ) ) ),
			'The stored provider term resolves without touching the fallbacks.'
		);

		$termless_id = $this->factory->comment->create( array( 'comment_post_ID' => $event_id ) );

		$this->assertNull(
			Utility::invoke_hidden_method(
				$storage,
				'get_value_from_object_terms',
				array( $termless_id, User::TAXONOMY )
			),
			'A termless object reads as null.'
		);
	}

	/**
	 * A failing comment update surfaces as false instead of a State.
	 *
	 * @covers ::save
	 *
	 * @return void
	 */
	public function test_save_returns_false_when_update_fails(): void {
		list( , $storage ) = $this->make_storage();

		$user_id = $this->factory->user->create();
		$state   = $storage->save( $this->user_intent( $user_id, Status::ATTENDING ), null );

		$force_failure = static function () {
			return new \WP_Error( 'simulated', 'Simulated update failure.' );
		};
		add_filter( 'wp_update_comment_data', $force_failure );

		$result = $storage->save(
			$this->user_intent( $user_id, Status::NOT_ATTENDING ),
			(int) $state->comment->comment_ID
		);

		remove_filter( 'wp_update_comment_data', $force_failure );

		$this->assertFalse( $result, 'An update failure reports false.' );
	}

	/**
	 * Passing a provider to get() does not prevent resolving an RSVP that
	 * carries no provider term — the identity pins the row, and the
	 * provider is only used for hydration. Guards term-less rows (the
	 * open/email form path, or content saved before stamping) against
	 * their next update becoming a duplicate insert.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_resolves_rows_without_provider_term(): void {
		list( $event_id, $storage ) = $this->make_storage();

		$user_id = $this->factory->user->create();

		// A comment + status term, but no provider term.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => 1,
				'user_id'          => $user_id,
			)
		);
		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		$found = $storage->get( new Identity( Identity_Type::WP_USER_ID, $user_id ), new User() );

		$this->assertInstanceOf( State::class, $found, 'The term-less row resolves when a provider is passed.' );
		$this->assertSame( $comment_id, (int) $found->comment->comment_ID );
	}
}
