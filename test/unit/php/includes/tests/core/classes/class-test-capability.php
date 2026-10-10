<?php
/**
 * Class handles unit tests for GatherPress\Core\Capability.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Capability;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Capability.
 *
 * @coversDefaultClass \GatherPress\Core\Capability
 */
class Test_Capability extends Base {

	/**
	 * Verify Capability constants define expected meta capability names and aliases.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_constants(): void {
		$this->assertSame( 'read_post', Capability::READ_POST );
		$this->assertSame( 'edit_post', Capability::EDIT_POST );
		$this->assertSame( Capability::READ_POST, Capability::READ_CAPABILITY );
		$this->assertSame( Capability::EDIT_POST, Capability::EDIT_CAPABILITY );
		$this->assertSame( Capability::READ_POST, Event::READ_CAPABILITY );
		$this->assertSame( Capability::EDIT_POST, Event::EDIT_CAPABILITY );
	}

	/**
	 * Verify READ_POST meta capability resolves properly against event posts.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_read_post_resolves_against_event(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$author_id     = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id      = $this->factory->user->create( array( 'role' => 'author' ) );
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$published_event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$draft_event_id     = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
				'post_author' => $author_id,
			)
		);
		$private_event_id   = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $author_id,
			)
		);

		// Anonymous viewer: has no capabilities.
		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( Capability::READ_POST, $published_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $draft_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $private_event_id ) );

		// Subscriber: has 'read' capability, so can read published events but not drafts or private.
		wp_set_current_user( $subscriber_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $draft_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $private_event_id ) );

		// Author who owns the draft: can read own draft.
		wp_set_current_user( $author_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $draft_event_id ) );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_event_id ) );
		$this->assertTrue( current_user_can( Capability::READ_POST, $private_event_id ) );

		// Another author: cannot read someone else's draft or private post.
		wp_set_current_user( $other_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $draft_event_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $private_event_id ) );

		// Administrator: can read all statuses.
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_event_id ) );
		$this->assertTrue( current_user_can( Capability::READ_POST, $draft_event_id ) );
		$this->assertTrue( current_user_can( Capability::READ_POST, $private_event_id ) );

		wp_set_current_user( 0 );
	}

	/**
	 * Verify READ_POST meta capability resolves properly against venue posts.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_read_post_resolves_against_venue(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$author_id     = $this->factory->user->create( array( 'role' => 'author' ) );
		$admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$published_venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$draft_venue_id     = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
				'post_author' => $author_id,
			)
		);

		// Anonymous viewer: has no capabilities.
		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( Capability::READ_POST, $published_venue_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $draft_venue_id ) );

		// Subscriber: can read published venue, but not draft.
		wp_set_current_user( $subscriber_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_venue_id ) );
		$this->assertFalse( current_user_can( Capability::READ_POST, $draft_venue_id ) );

		// Administrator: can read draft venue.
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( Capability::READ_POST, $published_venue_id ) );
		$this->assertTrue( current_user_can( Capability::READ_POST, $draft_venue_id ) );

		wp_set_current_user( 0 );
	}

	/**
	 * Verify EDIT_POST meta capability resolves properly against events and venues.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function test_edit_post_resolves_against_event_and_venue(): void {
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$other_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$admin_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_author' => $author_id,
			)
		);
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_author' => $author_id,
			)
		);

		// Anonymous viewer cannot edit.
		wp_set_current_user( 0 );
		$this->assertFalse( current_user_can( Capability::EDIT_POST, $event_id ) );
		$this->assertFalse( current_user_can( Capability::EDIT_POST, $venue_id ) );

		// Author of the posts can edit them.
		wp_set_current_user( $author_id );
		$this->assertTrue( current_user_can( Capability::EDIT_POST, $event_id ) );
		$this->assertTrue( current_user_can( Capability::EDIT_POST, $venue_id ) );

		// Another author cannot edit someone else's posts.
		wp_set_current_user( $other_id );
		$this->assertFalse( current_user_can( Capability::EDIT_POST, $event_id ) );
		$this->assertFalse( current_user_can( Capability::EDIT_POST, $venue_id ) );

		// Administrator can edit all posts.
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( Capability::EDIT_POST, $event_id ) );
		$this->assertTrue( current_user_can( Capability::EDIT_POST, $venue_id ) );

		wp_set_current_user( 0 );
	}
}
