<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Meta.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Meta;
use GatherPress\Tests\Base;
use stdClass;
use WP_REST_Request;

/**
 * Class Test_Meta.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Meta
 */
class Test_Meta extends Base {
	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Meta::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'register' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Calling `register()` on the built-in event post type registers
	 * both the event-date meta (gated on the `gatherpress-event-date`
	 * support) and the event-only meta (RSVP / attendance / online-event
	 * link). Also wires the REST readonly-strip filter.
	 *
	 * @covers ::register
	 * @covers ::register_event_date_meta
	 * @covers ::register_event_only_meta
	 *
	 * @return void
	 */
	public function test_register_on_event_post_type(): void {
		$instance = Meta::get_instance();

		// Wipe a representative key from each band so we can prove
		// re-registration restores both.
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_datetime' );
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_online_event_link' );

		$instance->register( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_datetime',
			$meta,
			'Event-date meta should be registered for a post type with gatherpress-event-date support.'
		);
		$this->assertArrayHasKey(
			'gatherpress_online_event_link',
			$meta,
			'Event-only meta should be registered when the post type matches Event::POST_TYPE.'
		);
		$this->assertNotFalse(
			has_filter(
				sprintf( 'rest_pre_insert_%s', Event::POST_TYPE ),
				array( $instance, 'filter_readonly_meta' )
			),
			'REST readonly-strip filter should be wired for the event-date band.'
		);
	}

	/**
	 * `register()` on a post type that declares `gatherpress-event-date`
	 * but isn't `Event::POST_TYPE` registers only the event-date meta —
	 * the event-only meta band is identity-bound to the canonical post
	 * type.
	 *
	 * @covers ::register
	 * @covers ::register_event_date_meta
	 *
	 * @return void
	 */
	public function test_register_on_event_date_supporting_post_type(): void {
		$instance = Meta::get_instance();
		$test_pt  = 'test_event_date_meta';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Event Date Meta',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-event-date' ),
			)
		);

		$instance->register( $test_pt );

		$meta = get_registered_meta_keys( 'post', $test_pt );

		$this->assertArrayHasKey(
			'gatherpress_datetime',
			$meta,
			'Event-date meta should be registered for a post type with gatherpress-event-date support.'
		);
		$this->assertArrayHasKey(
			'gatherpress_datetime_start',
			$meta,
			'Derived datetime meta should be registered.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_online_event_link',
			$meta,
			'Event-only meta must NOT register on a non-Event::POST_TYPE post type, even with event-date support.'
		);
		$this->assertNotFalse(
			has_filter( sprintf( 'rest_pre_insert_%s', $test_pt ), array( $instance, 'filter_readonly_meta' ) ),
			'REST readonly-strip filter should wire on event-date-supporting post types.'
		);

		unregister_post_type( $test_pt );
	}

	/**
	 * `register()` on a post type that declares neither
	 * `gatherpress-event-date` nor matches `Event::POST_TYPE` is a no-op:
	 * no meta registers and no REST filter wires.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_unsupported_post_type(): void {
		$instance = Meta::get_instance();

		$instance->register( 'post' );

		$this->assertFalse(
			has_filter( 'rest_pre_insert_post', array( $instance, 'filter_readonly_meta' ) ),
			'No filter should wire for a post type without event-date support.'
		);
	}

	/**
	 * Auth callbacks for both meta bands let an editor-capable user
	 * write the editor-writable keys via `update_post_meta()`.
	 *
	 * @covers ::register
	 * @covers ::register_event_date_meta
	 * @covers ::register_event_only_meta
	 *
	 * @return void
	 */
	public function test_register_post_meta_auth_callbacks(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_datetime_start', '2024-01-01 10:00:00' ),
			'Should allow meta update with edit_posts capability.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_datetime_start_gmt', '2024-01-01 10:00:00' ),
			'Should allow meta update for datetime_start_gmt.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_datetime_end', '2024-01-01 12:00:00' ),
			'Should allow meta update for datetime_end.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_datetime_end_gmt', '2024-01-01 12:00:00' ),
			'Should allow meta update for datetime_end_gmt.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_timezone', 'America/New_York' ),
			'Should allow meta update for timezone.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 ),
			'Should allow meta update for max_guest_limit.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true ),
			'Should allow meta update for enable_anonymous_rsvp.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_online_event_link', 'https://example.com' ),
			'Should allow meta update for online_event_link.'
		);
		$this->assertNotFalse(
			update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 ),
			'Should allow meta update for max_attendance_limit.'
		);
	}

	/**
	 * `filter_readonly_meta` strips the derived datetime keys (which have
	 * `auth_callback => __return_false`) from REST request meta so a
	 * co-submitted PATCH for the writable `gatherpress_datetime` doesn't
	 * fail the whole request.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_removes_readonly_keys(): void {
		$instance = Meta::get_instance();

		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime'           => '{"dateTimeStart":"2025-01-01 10:00:00"}',
				'gatherpress_datetime_start'     => '2025-01-01 10:00:00',
				'gatherpress_datetime_start_gmt' => '2025-01-01 15:00:00',
				'gatherpress_datetime_end'       => '2025-01-01 12:00:00',
				'gatherpress_datetime_end_gmt'   => '2025-01-01 17:00:00',
				'gatherpress_timezone'           => 'America/New_York',
				'gatherpress_online_event_link'  => 'https://example.com',
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 123;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );
		$this->assertEquals( 123, $result->ID );

		$filtered_meta = $request->get_param( 'meta' );

		$this->assertArrayNotHasKey( 'gatherpress_datetime_start', $filtered_meta );
		$this->assertArrayNotHasKey( 'gatherpress_datetime_start_gmt', $filtered_meta );
		$this->assertArrayNotHasKey( 'gatherpress_datetime_end', $filtered_meta );
		$this->assertArrayNotHasKey( 'gatherpress_datetime_end_gmt', $filtered_meta );
		$this->assertArrayNotHasKey( 'gatherpress_timezone', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_datetime', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_online_event_link', $filtered_meta );
	}

	/**
	 * `filter_readonly_meta` returns the prepared post unchanged when
	 * the request has no `meta` param.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_with_null_meta(): void {
		$instance = Meta::get_instance();
		$request  = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );

		$prepared_post     = new stdClass();
		$prepared_post->ID = 456;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );
		$this->assertNull( $request->get_param( 'meta' ) );
	}

	/**
	 * `filter_readonly_meta` leaves an empty meta array empty.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_with_empty_meta(): void {
		$instance = Meta::get_instance();
		$request  = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param( 'meta', array() );

		$prepared_post     = new stdClass();
		$prepared_post->ID = 789;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );

		$filtered_meta = $request->get_param( 'meta' );
		$this->assertIsArray( $filtered_meta );
		$this->assertEmpty( $filtered_meta );
	}

	/**
	 * `filter_readonly_meta` leaves writable-only payloads untouched.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_with_only_writable_keys(): void {
		$instance = Meta::get_instance();
		$request  = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime'              => '{"dateTimeStart":"2025-01-01 10:00:00"}',
				'gatherpress_online_event_link'     => 'https://example.com/meeting',
				'gatherpress_enable_anonymous_rsvp' => true,
				'gatherpress_max_guest_limit'       => 5,
				'gatherpress_max_attendance_limit'  => 100,
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 101;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );

		$filtered_meta = $request->get_param( 'meta' );

		$this->assertCount( 5, $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_datetime', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_online_event_link', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_enable_anonymous_rsvp', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_max_guest_limit', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_max_attendance_limit', $filtered_meta );
	}

	/**
	 * `filter_readonly_meta` strips an all-readonly payload to empty.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_with_only_readonly_keys(): void {
		$instance = Meta::get_instance();
		$request  = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );
		$request->set_param(
			'meta',
			array(
				'gatherpress_datetime_start'     => '2025-01-01 10:00:00',
				'gatherpress_datetime_start_gmt' => '2025-01-01 15:00:00',
				'gatherpress_datetime_end'       => '2025-01-01 12:00:00',
				'gatherpress_datetime_end_gmt'   => '2025-01-01 17:00:00',
				'gatherpress_timezone'           => 'America/New_York',
			)
		);

		$prepared_post     = new stdClass();
		$prepared_post->ID = 202;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );

		$filtered_meta = $request->get_param( 'meta' );

		$this->assertIsArray( $filtered_meta );
		$this->assertEmpty( $filtered_meta );
	}
}
