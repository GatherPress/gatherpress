<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Rsvp.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Data;
use GatherPress\Core\Rsvp\Response\Identity_Type;
use GatherPress\Core\Rsvp\Response\Identity;
use GatherPress\Core\Rsvp\Response\Intent;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Response\State;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Rsvp\Cache;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
// Deep import on purpose: test_prior_fqn_resolves_to_current_class asserts
// Rsvp::class equals the real FQN, which the BC alias intentionally is not.
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Rsvp
 */
class Test_Rsvp extends Base {

	/**
	 * Asserts that the prior fully-qualified class name `GatherPress\Core\Rsvp` continues
	 * to resolve to the current class `GatherPress\Core\Rsvp\Rsvp` via the alias map in
	 * `includes/core/register-class-aliases.php`. Removing the alias entry would silently
	 * break external consumers (other plugins, theme code) that reference the prior FQN —
	 * this test fails loudly first.
	 *
	 * @return void
	 */
	public function test_prior_fqn_resolves_to_current_class(): void {
		$prior_fqn = 'GatherPress\\Core\\Rsvp';

		$this->assertTrue(
			class_exists( $prior_fqn ),
			'The prior fully-qualified class name should resolve via the alias map.'
		);

		$reflection = new ReflectionClass( $prior_fqn );
		$this->assertSame(
			Rsvp::class,
			$reflection->getName(),
			'The prior FQN should resolve to the current Rsvp class.'
		);

		// Read a class constant through the prior FQN to confirm runtime usability.
		$this->assertSame( Rsvp::COMMENT_TYPE, constant( $prior_fqn . '::COMMENT_TYPE' ) );
	}

	/**
	 * Coverage for get method.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get(): void {
		$post    = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertEmpty( $rsvp->get( 0 ) );
		$this->assertEquals( 0, $rsvp->get( $user_id )['comment_id'] );

		$rsvp->save( $user_id, $status );

		$data = $rsvp->get( $user_id );

		$this->assertSame( $post->ID, intval( $data['post_id'] ) );
		$this->assertSame( $user_id, intval( $data['user_id'] ) );
		$this->assertSame( $status, $data['status'] );
		$this->assertIsInt( strtotime( $data['timestamp'] ) );
		$this->assertNotEmpty( $data['comment_id'] );
	}

	/**
	 * Coverage for save method.
	 *
	 * @covers ::save
	 * @covers ::__construct
	 */
	public function test_save(): void {
		$post    = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$status  = 'attending';

		$this->assertSame( $status, $rsvp->save( $user_id, $status )['status'], 'Failed to assert user is attending.' );

		$status = 'not_attending';

		$this->assertSame(
			$status,
			$rsvp->save( $user_id, $status )['status'],
			'Failed to assert user is not attending.'
		);

		$this->assertSame(
			'no_status',
			$rsvp->save( 0, $status )['status'],
			'Failed to assert no_status due to invalid user ID.'
		);

		$status = 'invalid_status';

		$this->assertSame(
			'no_status',
			$rsvp->save( $user_id, $status )['status'],
			'Failed to assert no_status due to invalid status.'
		);

		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp = new Rsvp( $post->ID );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$status    = 'attending';

		$this->assertSame(
			'attending',
			$rsvp->save( $user_1_id, $status )['status'],
			'Failed to assert that user 1 is attending.'
		);
		$this->assertSame(
			'waiting_list',
			$rsvp->save( $user_2_id, $status )['status'],
			'Failed to assert that user 2 is on waiting list.'
		);

		$user_1_id = $this->factory->user->create();

		// Enable anonymous RSVP for this test.
		update_post_meta( $post->ID, 'gatherpress_enable_anonymous_rsvp', true );

		// Anonymous not_attending should preserve the record.
		$this->assertSame(
			'waiting_list',
			$rsvp->save( $user_1_id, 'attending', 1 )['status'],
			'Failed to assert that user 1 is on waiting list.'
		);
		$this->assertSame(
			'not_attending',
			$rsvp->save( $user_1_id, 'not_attending', 1 )['status'],
			'Failed to assert that user 1 is not_attending.'
		);

		$user_2_id = $this->factory->user->create();

		$this->assertSame(
			'no_status',
			$rsvp->save( $user_2_id, 'no_status' )['status'],
			'Failed to assert that user 2 is no_status.'
		);

		$post      = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_max_guest_limit' => 2,
				),
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_1_id = $this->factory->user->create();
		$this->assertSame(
			2,
			$rsvp->save( $user_1_id, 'attending', 0, 3 )['guests'],
			'Failed to assert that user 1 can only bring 2 guests at most.'
		);

		// Simulate error saving RSVP.
		add_filter( 'query', '__return_false' );

		$result   = $rsvp->save( $user_1_id, 'attending' );
		$expected = array(
			'comment_id' => 0,
			'post_id'    => 0,
			'user_id'    => 0,
			'timestamp'  => '0000-00-00 00:00:00',
			'status'     => 'no_status',
			'guests'     => 0,
			'anonymous'  => 0,
		);

		$this->assertEquals( $expected, $result );

		remove_filter( 'query', '__return_false' );
	}

	/**
	 * Test check waiting list with no attendees.
	 *
	 * @since  0.34.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_no_attendees(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp     = new Rsvp( $event_id );

		$this->assertEquals(
			0,
			$rsvp->check_waiting_list(),
			'Should return 0 when there are no attendees'
		);
	}

	/**
	 * Test check waiting list with unlimited attendance.
	 *
	 * @since  0.34.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_unlimited_attendance(): void {
		$event_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp      = new Rsvp( $event_id );
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();
		$user_3_id = $this->factory->user->create();

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		// Fill the one spot.
		$rsvp->save( $user_1_id, 'attending' );

		// These should go to waiting list.
		$rsvp->save( $user_2_id, 'attending' );
		$rsvp->save( $user_3_id, 'attending' );

		// Now remove the limit.
		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 0 );

		$this->assertEquals(
			2,
			$rsvp->check_waiting_list(),
			'Should move all waiting list members to attending when no limit'
		);
	}

	/**
	 * Test check waiting list with limited attendance.
	 *
	 * @since  0.34.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_with_limited_attendance(): void {
		$event_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp      = new Rsvp( $event_id );
		$user_1_id = $this->factory->user->create();
		$user_2_id = $this->factory->user->create();

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$rsvp->save( $user_1_id, 'waiting_list' );
		$rsvp->save( $user_2_id, 'waiting_list' );

		$this->assertEquals(
			0,
			$rsvp->check_waiting_list(),
			'Should not move anyone to attending when limit is 1'
		);

		$this->assertSame(
			'attending',
			$rsvp->get( $user_1_id )['status'],
			'First user should have moved to attending'
		);

		$this->assertSame(
			'waiting_list',
			$rsvp->get( $user_2_id )['status'],
			'Second user should remain on waiting list'
		);
	}

	/**
	 * Test that an Open RSVP (email-keyed, userId 0) attendee is promoted off the waiting list.
	 *
	 * Regression for #1771: the promotion loop previously passed the record's
	 * userId straight to save(), which is 0 for non-logged-in Open RSVP
	 * attendees, so save() hit its empty-identifier guard and the promotion
	 * was silently dropped.
	 *
	 * @since  0.34.0
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_promotes_open_rsvp_attendee(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp     = new Rsvp( $event_id );
		$email    = 'open-rsvp-attendee@example.com';

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		// Fill the single spot with a logged-in user, then add an email-keyed
		// Open RSVP attendee who lands on the waiting list.
		$rsvp->save( $this->factory->user->create(), 'attending' );
		$rsvp->save( $email, 'attending' );

		$this->assertSame(
			'waiting_list',
			$rsvp->get( $email )['status'],
			'Open RSVP attendee should start on the waiting list'
		);

		// Open the event up and run the promotion sweep.
		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 0 );

		$this->assertSame(
			1,
			$rsvp->check_waiting_list(),
			'Should promote the email-keyed Open RSVP attendee'
		);

		$this->assertSame(
			'attending',
			$rsvp->get( $email )['status'],
			'Open RSVP attendee should be moved to attending'
		);
	}

	/**
	 * Coverage for attending_limit_reached method.
	 *
	 * @covers ::attending_limit_reached
	 *
	 * @return void
	 */
	public function test_attending_limit_reached(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp = new Rsvp( $post->ID );

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$user_id = $this->factory->user->create();

		$current_response = $rsvp->process(
			new Intent(
				new Data(
					new Identity( Identity_Type::WP_USER_ID, $user_id ),
					Status::WAITING_LIST
				),
				Provider_Registry::get_instance()->get( 'user' ),
			)
		);

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response ),
			'Failed to assert that limit has not been reached.'
		);

		$rsvp->save( $user_id, 'attending' );

		$current_response = $rsvp->find( $user_id );

		$this->assertTrue(
			$rsvp->attending_limit_reached( $current_response, 1 ),
			'Failed to assert that limit has been reached.'
		);

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 0 );

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response, 1 ),
			'Failed to assert that limit has not been reached.'
		);

		Utility::set_and_get_hidden_property( $rsvp, 'max_attendance_limit', 1 );

		$this->assertFalse(
			$rsvp->attending_limit_reached( $current_response ),
			'Failed to assert that limit has not been reached.'
		);
	}

	/**
	 * Coverages for responses method.
	 *
	 * @covers ::responses
	 * @covers ::sort_by_role
	 *
	 * @return void
	 */
	public function test_responses(): void {
		$post      = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_id_1 = $this->factory->user->create();
		$user_id_2 = $this->factory->user->create();

		$rsvp->save( $user_id_1, 'attending' );
		$rsvp->save( $user_id_2, 'not_attending' );

		$responses = $rsvp->responses();

		$this->assertEquals( 2, $responses['all']['count'], 'Failed to assert that count is 2.' );
		$this->assertEquals(
			$user_id_1,
			$responses['attending']['records'][0]['user_id'],
			'Failed to assert user ID matches.'
		);
		$this->assertEquals(
			$user_id_2,
			$responses['not_attending']['records'][0]['user_id'],
			'Failed to assert user ID matches.'
		);

		wp_delete_user( $user_id_2 );

		// User will remain while cached until it expires.
		Cache::delete( $post->ID );

		$responses = $rsvp->responses();

		$this->assertEmpty(
			$responses['not_attending']['records'],
			'Failed not_attending responses are empty after $user_id_2 was deleted.'
		);

		$post      = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$responses = $rsvp->responses();

		$this->assertEmpty(
			$responses['all']['records'],
			'Failed to assert all responses empty with non-event post type.'
		);
		$this->assertEquals( 0, $responses['all']['count'], 'Failed to assert count is 0 with non-event post type.' );

		$this->mock->user( 'subscriber' );

		$post      = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_enable_anonymous_rsvp' => true,
				),
			)
		)->get();
		$rsvp      = new Rsvp( $post->ID );
		$user_id_3 = $this->factory->user->create();

		$rsvp->save( $user_id_3, 'attending', 1 );

		$responses = $rsvp->responses();

		$this->assertEquals(
			0,
			$responses['all']['records'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEquals(
			0,
			$responses['attending']['records'][0]['id'],
			'Failed to assert user ID matches 0.'
		);
		$this->assertEmpty(
			$responses['all']['records'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertEmpty(
			$responses['attending']['records'][0]['profile'],
			'Failed to assert profile is empty.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['all']['records'][0]['name'],
			'Failed to assert user display name is Anonymous.'
		);
		$this->assertSame(
			'Anonymous',
			$responses['attending']['records'][0]['name'],
			'Failed to assert user display name is Anonymous.'
		);
	}

	/**
	 * Coverage for sort_by_timestamp method.
	 *
	 * @covers ::sort_by_timestamp
	 *
	 * @return void
	 */
	public function test_sort_by_timestamp(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$newer = array( 'timestamp' => '2023-05-11 08:30:00' );
		$older = array( 'timestamp' => '2022-05-11 08:30:00' );

		$this->assertSame(
			-1,
			$rsvp->sort_by_timestamp( $older, $newer ),
			'Failed to assert that it returns a negative number while the first response\'s timestamp is earlier.'
		);

		$this->assertSame(
			1,
			$rsvp->sort_by_timestamp( $newer, $older ),
			'Failed to assert that it returns a positive number while the second response\'s timestamp is earlier.'
		);

		$this->assertSame(
			0,
			$rsvp->sort_by_timestamp( $newer, $newer ),
			'Failed to assert that it returns 0 while both response\'s timestamps are equal.'
		);
	}

	/**
	 * Test get method with email identifier.
	 *
	 * @covers ::get
	 * @covers ::__construct
	 */
	public function test_get_with_email(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$email = 'test@example.com';

		// Get RSVP by email (should return empty before save).
		$data = $rsvp->get( $email );
		$this->assertEquals( 0, $data['comment_id'] );

		// Save RSVP with email.
		$rsvp->save( $email, 'attending' );

		// Get RSVP by email (should return the RSVP).
		$data = $rsvp->get( $email );

		$this->assertSame( $post->ID, intval( $data['post_id'] ) );
		$this->assertSame( 'attending', $data['status'] );
		$this->assertNotEmpty( $data['comment_id'] );
	}

	/**
	 * Test save method returns default data when RSVP is disabled for the event.
	 *
	 * @covers ::save
	 */
	public function test_save_rsvp_disabled(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		// Set rsvp_mode to per_event_on so that per-event disabling is respected.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );

		// Explicitly disable RSVP for this event.
		update_post_meta( $post->ID, 'gatherpress_enable_rsvp', 0 );

		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();
		$result  = $rsvp->save( $user_id, 'attending' );

		$this->assertSame( 0, $result['post_id'], 'Should return default data when RSVP is disabled.' );
		$this->assertSame( 'no_status', $result['status'], 'Should return no_status when RSVP is disabled.' );

		// Restore the setting for other tests.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Coverage for is_enabled method.
	 *
	 * @covers ::is_enabled
	 *
	 * @return void
	 */
	public function test_is_enabled(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Returns false when mode is per_event_on and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '0' );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return false when mode is per_event_on and meta is 0.'
		);

		// Returns false when mode is per_event_off and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_off' );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return false when mode is per_event_off and meta is 0.'
		);

		// Returns true when mode is per_event_on and meta is '1'.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '1' );
		$this->assertTrue(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return true when mode is per_event_on and meta is 1.'
		);

		// Returns true when mode is per_event_on and meta is '' (never set).
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );
		$this->assertTrue(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return true when mode is per_event_on and meta is empty (never set).'
		);

		// Returns false when mode is per_event_off and meta is '' (never set).
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_off' );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return false when mode is per_event_off and meta is empty (never set).'
		);

		// Returns true when mode is all_on and meta is '0'.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', '0' );
		$this->assertTrue(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return true when mode is all_on regardless of meta.'
		);

		// Returns false when mode is disabled regardless of meta.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->is_enabled(),
			'Should return false when mode is disabled regardless of meta.'
		);

		// Restore default setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Test save method with email identifier.
	 *
	 * @covers ::save
	 * @covers ::__construct
	 */
	public function test_save_with_email(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$email = 'rsvp@example.com';

		$data = $rsvp->save( $email, 'attending', 0, 0 );

		$this->assertSame( $post->ID, intval( $data['post_id'] ) );
		$this->assertSame( 'attending', $data['status'] );
		$this->assertNotEmpty( $data['comment_id'] );

		// Verify email was stored.
		$comment = get_comment( $data['comment_id'] );
		$this->assertEquals( $email, $comment->comment_author_email );
	}

	/**
	 * Test that anonymous RSVP is preserved when changing to not_attending.
	 *
	 * @covers ::save
	 *
	 * @return void
	 */
	public function test_save_anonymous_not_deleted_on_not_attending(): void {
		$post  = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_enable_anonymous_rsvp' => true,
				),
			)
		)->get();
		$rsvp  = new Rsvp( $post->ID );
		$email = 'anonymous@example.com';

		// Save an attending RSVP with anonymous flag.
		$data = $rsvp->save( $email, 'attending', 1 );
		$this->assertSame( 'attending', $data['status'], 'Failed to assert anonymous user is attending.' );

		$comment_id = $data['comment_id'];

		// Change RSVP to not_attending. The record should be preserved.
		$data = $rsvp->save( $email, 'not_attending', 1 );

		$this->assertSame(
			'not_attending',
			$data['status'],
			'Failed to assert anonymous user is not_attending.'
		);
		$this->assertNotEmpty(
			$data['comment_id'],
			'Failed to assert comment was preserved for anonymous user.'
		);

		// Verify the comment still exists.
		$comment = get_comment( $comment_id );
		$this->assertNotNull( $comment, 'Failed to assert comment still exists after not_attending.' );
	}

	/**
	 * Test that save() runs wp_filter_comment so WordPress-native
	 * privacy filters like pre_comment_user_ip and pre_comment_user_agent
	 * are honored on inserted RSVPs.
	 *
	 * @covers ::save
	 *
	 * @return void
	 */
	public function test_save_applies_comment_privacy_filters(): void {
		$post    = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$rsvp    = new Rsvp( $post->ID );
		$user_id = $this->factory->user->create();

		$_SERVER['REMOTE_ADDR']     = '203.0.113.42';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (test browser)';

		$redact_ip    = static function () {
			return '127.0.0.1';
		};
		$redact_agent = static function () {
			return '';
		};
		add_filter( 'pre_comment_user_ip', $redact_ip );
		add_filter( 'pre_comment_user_agent', $redact_agent );

		try {
			$data = $rsvp->save( $user_id, 'attending' );

			$this->assertSame( 'attending', $data['status'] );

			$comment = get_comment( $data['comment_id'] );
			$this->assertSame( '127.0.0.1', $comment->comment_author_IP );
			$this->assertSame( '', $comment->comment_agent );
		} finally {
			remove_filter( 'pre_comment_user_ip', $redact_ip );
			remove_filter( 'pre_comment_user_agent', $redact_agent );
			unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] );
		}
	}

	/**
	 * Test check_waiting_list when not enough people on waiting list.
	 *
	 * @covers ::check_waiting_list
	 * @covers ::responses
	 * @covers ::__construct
	 */
	public function test_check_waiting_list_insufficient_waiting(): void {
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		// Set attendance limit.
		update_post_meta( $post->ID, 'gatherpress_enable_attendance_limit', 1 );
		update_post_meta( $post->ID, 'gatherpress_max_attendance_limit', 3 );

		$rsvp = new Rsvp( $post->ID );

		// Add 3 attending users.
		$user1 = $this->factory->user->create();
		$user2 = $this->factory->user->create();
		$user3 = $this->factory->user->create();

		$rsvp->save( $user1, 'attending' );
		$rsvp->save( $user2, 'attending' );
		$rsvp->save( $user3, 'attending' );

		// Add 1 person to waiting list.
		$user4 = $this->factory->user->create();
		$rsvp->save( $user4, 'waiting_list' );

		// Remove one attending person, creating 2 open spots.
		$rsvp->save( $user1, 'not_attending' );

		// Check waiting list - should only move 1 person (user4) since only 1 on waiting list.
		$rsvp->check_waiting_list();

		// Verify user4 moved to attending.
		$data = $rsvp->get( $user4 );
		$this->assertEquals( 'attending', $data['status'] );

		// Verify only 3 attending (user2, user3, user4).
		$responses = $rsvp->responses();
		$this->assertEquals( 3, $responses['attending']['count'] );
	}

	/**
	 * Coverage for initialize_enabled method.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_writes_meta_in_all_on_mode(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear any meta set by the wp_after_insert_post hook during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		// Default mode is all_on; calling the method should write 1.
		( new Rsvp( $post_id ) )->initialize_enabled();

		$this->assertSame(
			'1',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should be written as 1 in all_on mode when not previously set.'
		);
	}

	/**
	 * Coverage for initialize_enabled method.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_does_not_overwrite_existing_meta(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Pre-set meta to 0 (RSVP disabled for this event).
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', 0 );

		// Default mode is all_on; method should not overwrite the existing value.
		( new Rsvp( $post_id ) )->initialize_enabled();

		$this->assertSame(
			'0',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Existing meta value should not be overwritten.'
		);
	}

	/**
	 * Coverage for initialize_enabled method.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	/**
	 * Coverage for initialize_enabled method.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_skips_non_rsvp_post_type(): void {
		// Use a standard post type that does not support gatherpress-rsvp.
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		( new Rsvp( $post_id ) )->initialize_enabled();

		// Meta should remain unset since the post type is not an RSVP-capable event.
		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should not be written for post types that do not support gatherpress-rsvp.'
		);
	}

	/**
	 * Coverage for initialize_enabled method in per_event_on mode.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_writes_meta_in_per_event_on_mode(): void {
		// Switch to per_event_on mode BEFORE creating the post so the hook does not write meta.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_on' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear meta set by the wp_after_insert_post hook during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		( new Rsvp( $post_id ) )->initialize_enabled();

		// per_event_on default is enabled, so meta should be written as 1.
		$this->assertSame(
			'1',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should be written as 1 in per_event_on mode when not previously set.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Coverage for initialize_enabled method in per_event_off mode.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_writes_meta_in_per_event_off_mode(): void {
		// Switch to per_event_off mode BEFORE creating the post so the hook does not write meta.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_off' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear meta set by the wp_after_insert_post hook during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		( new Rsvp( $post_id ) )->initialize_enabled();

		// per_event_off default is disabled, so meta should be written as 0.
		$this->assertSame(
			'0',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should be written as 0 in per_event_off mode when not previously set.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Coverage for initialize_enabled method in disabled mode.
	 *
	 * @covers ::initialize_enabled
	 *
	 * @return void
	 */
	public function test_initialize_enabled_skips_disabled_mode(): void {
		// Switch to disabled mode BEFORE creating the post.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Clear any meta that may have been set during post creation.
		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );

		( new Rsvp( $post_id ) )->initialize_enabled();

		// Disabled mode writes no meta.
		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_enable_rsvp', true ),
			'Meta should not be written when mode is disabled.'
		);

		// Restore setting.
		Settings::get_instance()->set( 'rsvp_mode', 'all_on' );
	}

	/**
	 * Coverage for allows_open_rsvp method.
	 *
	 * @covers ::allows_open_rsvp
	 *
	 * @return void
	 */
	public function test_allows_open_rsvp(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// Sitewide disabled returns false regardless of per-event meta.
		Settings::get_instance()->set( 'enable_open_rsvp', false );
		update_post_meta( $post_id, 'gatherpress_enable_open_rsvp', 1 );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->allows_open_rsvp(),
			'Should return false when sitewide enable_open_rsvp is false, even with per-event meta enabled.'
		);

		// Sitewide enabled and meta not set defaults to true.
		Settings::get_instance()->set( 'enable_open_rsvp', true );
		delete_post_meta( $post_id, 'gatherpress_enable_open_rsvp' );
		$this->assertTrue(
			( new Rsvp( $post_id ) )->allows_open_rsvp(),
			'Should return true when sitewide is enabled and per-event meta is not set.'
		);

		// Sitewide enabled and per-event meta explicitly enabled returns true.
		update_post_meta( $post_id, 'gatherpress_enable_open_rsvp', 1 );
		$this->assertTrue(
			( new Rsvp( $post_id ) )->allows_open_rsvp(),
			'Should return true when sitewide is enabled and per-event meta is explicitly enabled.'
		);

		// Sitewide enabled and per-event meta explicitly disabled returns false.
		update_post_meta( $post_id, 'gatherpress_enable_open_rsvp', 0 );
		$this->assertFalse(
			( new Rsvp( $post_id ) )->allows_open_rsvp(),
			'Should return false when sitewide is enabled but per-event meta is explicitly disabled.'
		);

		// Restore the sitewide setting for other tests.
		Settings::get_instance()->set( 'enable_open_rsvp', true );
	}

	/**
	 * Identifier resolution: an unrecognized identifier yields null from
	 * get() and find(); email and user-ID identifiers resolve through
	 * their providers and round-trip a saved response.
	 *
	 * @covers ::get
	 * @covers ::find
	 * @covers ::resolve_identity
	 * @covers ::resolve_provider
	 *
	 * @return void
	 */
	public function test_get_and_find_resolve_identifiers(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp     = new Rsvp( $event_id );

		$this->assertNull( $rsvp->get( 'not-an-email-or-id' ), 'Unresolvable identifiers yield null.' );
		$this->assertNull( $rsvp->find( 'not-an-email-or-id' ) );
		$this->assertNull( $rsvp->get( 'unknown@example.test' ), 'A valid email with no response yields null.' );

		$user_id = $this->factory->user->create();
		$rsvp->save( $user_id, 'attending' );

		$row = $rsvp->get( $user_id );
		$this->assertSame( 'attending', $row['status'] );

		$state = $rsvp->find( $user_id );
		$this->assertInstanceOf( State::class, $state );
		$this->assertSame( Status::ATTENDING, $state->data->status );
	}

	/**
	 * The save pipeline clamps guests to the event's guest limit and
	 * forces anonymity off unless the event enables anonymous RSVPs.
	 *
	 * @covers ::save
	 * @covers ::process
	 * @covers ::constrain_rsvp_intent
	 *
	 * @return void
	 */
	public function test_save_constrains_guests_and_anonymity(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		update_post_meta( $event_id, 'gatherpress_max_guest_limit', 3 );

		$rsvp = new Rsvp( $event_id );

		$constrained = $rsvp->save( $this->factory->user->create(), 'attending', 1, 5 );

		$this->assertSame( 'attending', $constrained['status'] );
		$this->assertSame( 3, $constrained['guests'], 'Guests clamp to the event limit.' );
		$this->assertFalse( $constrained['anonymous'], 'Anonymity is off while the event disallows it.' );

		update_post_meta( $event_id, 'gatherpress_enable_anonymous_rsvp', true );

		$anonymous = $rsvp->save( $this->factory->user->create(), 'attending', 1 );

		$this->assertTrue( (bool) $anonymous['anonymous'], 'Anonymity persists once the event allows it.' );
	}

	/**
	 * When the attending limit is already reached, a first-time attending
	 * request lands on the waiting list with zero guests — and an explicit
	 * waiting-list request always drops its guests.
	 *
	 * @covers ::process
	 * @covers ::constrain_rsvp_intent
	 *
	 * @return void
	 */
	public function test_save_routes_to_waiting_list_when_limit_reached(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		update_post_meta( $event_id, 'gatherpress_max_attendance_limit', 1 );
		update_post_meta( $event_id, 'gatherpress_max_guest_limit', 5 );

		$first  = ( new Rsvp( $event_id ) )->save( $this->factory->user->create(), 'attending' );
		$second = ( new Rsvp( $event_id ) )->save( $this->factory->user->create(), 'attending', 0, 2 );

		$this->assertSame( 'attending', $first['status'] );
		$this->assertSame( 'waiting_list', $second['status'], 'A full event routes newcomers to the waiting list.' );
		$this->assertSame( 2, $second['guests'], 'Redirected responders keep their party size for later promotion.' );

		$explicit = ( new Rsvp( $event_id ) )->save( $this->factory->user->create(), 'waiting_list', 0, 3 );

		$this->assertSame( 'waiting_list', $explicit['status'] );
		$this->assertSame( 0, $explicit['guests'] );
	}

	/**
	 * An already-attending responder re-saving into a full event keeps
	 * their prior guest count instead of growing it.
	 *
	 * @covers ::constrain_rsvp_intent
	 *
	 * @return void
	 */
	public function test_save_keeps_prior_guests_when_full(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		// The limit counts the whole party: responder plus guests. A
		// limit of 4 admits one responder with two guests, and is then
		// exceeded the moment they ask to grow the party.
		update_post_meta( $event_id, 'gatherpress_max_attendance_limit', 4 );
		update_post_meta( $event_id, 'gatherpress_max_guest_limit', 5 );

		$user_id = $this->factory->user->create();

		$initial = ( new Rsvp( $event_id ) )->save( $user_id, 'attending', 0, 2 );
		$this->assertSame( 'attending', $initial['status'] );

		$resave = ( new Rsvp( $event_id ) )->save( $user_id, 'attending', 0, 4 );

		$this->assertSame( 'attending', $resave['status'], 'An attending responder stays attending.' );
		$this->assertSame( 2, $resave['guests'], 'A full event freezes the prior guest count.' );
	}

	/**
	 * Freeing a spot promotes the earliest waiting-list responder.
	 *
	 * @covers ::process
	 * @covers ::check_waiting_list
	 *
	 * @return void
	 */
	public function test_check_waiting_list_promotes_on_freed_spot(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		update_post_meta( $event_id, 'gatherpress_max_attendance_limit', 1 );

		$attendee_id = $this->factory->user->create();
		$waiter_id   = $this->factory->user->create();

		( new Rsvp( $event_id ) )->save( $attendee_id, 'attending' );
		( new Rsvp( $event_id ) )->save( $waiter_id, 'attending' );

		$this->assertSame(
			'waiting_list',
			( new Rsvp( $event_id ) )->get( $waiter_id )['status'],
			'The second responder starts on the waiting list.'
		);

		( new Rsvp( $event_id ) )->save( $attendee_id, 'not_attending' );

		$this->assertSame(
			'attending',
			( new Rsvp( $event_id ) )->get( $waiter_id )['status'],
			'Freeing a spot promotes the waiting-list responder.'
		);
	}

	/**
	 * A second responses() call within the cache TTL returns the cached
	 * array without rebuilding.
	 *
	 * @covers ::responses
	 *
	 * @return void
	 */
	public function test_responses_returns_cached_array(): void {
		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		( new Rsvp( $event_id ) )->save( $this->factory->user->create(), 'attending' );

		$rsvp  = new Rsvp( $event_id );
		$first = $rsvp->responses();

		$this->assertSame( 1, $first['attending']['count'] );
		$this->assertArrayHasKey(
			'commentId',
			$first['attending']['records'][0],
			'Records keep the camelCase contract the blocks consume.'
		);
		$this->assertArrayHasKey( 'userId', $first['attending']['records'][0] );
		$this->assertSame( $first, $rsvp->responses(), 'The cached array is served on repeat calls.' );
	}

	/**
	 * Process refuses invalid events, a removal round-trips to the
	 * default response, and providers only resolve for user and email
	 * identity types.
	 *
	 * @covers ::process
	 * @covers ::save
	 * @covers ::resolve_provider
	 *
	 * @return void
	 */
	public function test_process_degenerate_paths(): void {
		$user_id = $this->factory->user->create();
		$intent  = new Intent(
			new Data( new Identity( Identity_Type::WP_USER_ID, $user_id ), Status::ATTENDING ),
			new User()
		);

		$this->assertNull(
			( new Rsvp( 0 ) )->process( $intent ),
			'An invalid event never processes.'
		);

		$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$rsvp     = new Rsvp( $event_id );

		$rsvp->save( $user_id, 'attending' );
		$removed = $rsvp->save( $user_id, 'no_status' );

		$this->assertSame(
			'no_status',
			$removed['status'],
			'Removing a response returns the default no_status shape.'
		);
		$this->assertNull( $rsvp->get( $user_id ), 'The response no longer resolves after removal.' );

		$url_identity = new Identity( Identity_Type::URL, 'https://example.test/responder' );

		$this->assertNull(
			Utility::invoke_hidden_method( $rsvp, 'resolve_provider', array( $url_identity ) ),
			'Only user and email identities resolve to core providers.'
		);
	}
}
