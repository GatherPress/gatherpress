<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Meta.
 *
 * @package GatherPress\Core\Event
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Meta;
use GatherPress\Core\Geo_Sync;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;
use stdClass;
use WP_REST_Posts_Controller;
use WP_REST_Request;
use WP_REST_Response;

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
			array(
				'type'     => 'action',
				'name'     => 'template_redirect',
				'priority' => 10,
				'callback' => array( Geo_Sync::get_instance(), 'maybe_refresh_on_view' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( Geo_Sync::get_instance(), 'on_event_saved' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * `register()` on the built-in event post type registers all three
	 * meta bands (event-date, venue-geo, event-only) and the REST filter.
	 *
	 * @covers ::register
	 * @covers ::register_event_date_meta
	 * @covers ::register_venue_geo_meta
	 * @covers ::register_event_only_meta
	 *
	 * @return void
	 */
	public function test_register_on_event_post_type(): void {
		$instance = Meta::get_instance();

		// Wipe a representative key from each band so we can prove
		// re-registration restores all three.
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_datetime' );
		unregister_post_meta( Event::POST_TYPE, 'geo_latitude' );
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_online_event_link' );

		$instance->register( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_datetime',
			$meta,
			'Event-date meta should be registered for a post type with gatherpress-event-date support.'
		);
		$this->assertArrayHasKey(
			'geo_latitude',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_longitude',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_address',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_public',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
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
	 * `register()` registers the venue-geo band independently of the
	 * event-date band, on `gatherpress-venue` support alone.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::register
	 * @covers ::register_venue_geo_meta
	 *
	 * @return void
	 */
	public function test_register_on_venue_assignment_supporting_post_type(): void {
		$instance = Meta::get_instance();
		$test_pt  = 'test_venue_geo_meta';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Venue Geo Meta',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-venue' ),
			)
		);

		$instance->register( $test_pt );

		$meta = get_registered_meta_keys( 'post', $test_pt );

		$this->assertSame(
			10,
			has_filter( 'rest_prepare_' . $test_pt, array( Geo_Sync::get_instance(), 'maybe_refresh_on_rest' ) ),
			'The REST geo refresh should be wired for a post type with gatherpress-venue support.'
		);

		$this->assertArrayHasKey(
			'geo_latitude',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_longitude',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_address',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayHasKey(
			'geo_public',
			$meta,
			'Venue-geo meta should be registered for a post type with gatherpress-venue support.'
		);
		$this->assertArrayNotHasKey(
			'gatherpress_datetime',
			$meta,
			'Event-date meta must NOT register on a post type without gatherpress-event-date support.'
		);
		$this->assertNotFalse(
			has_filter( sprintf( 'rest_pre_insert_%s', $test_pt ), array( $instance, 'filter_readonly_meta' ) ),
			'REST readonly-strip filter should wire on venue-assignment-supporting post types.'
		);
		$this->assertTrue(
			post_type_supports( $test_pt, 'custom-fields' ),
			'custom-fields support should be auto-added so the REST controller exposes the meta field.'
		);

		unregister_post_type( $test_pt );
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
		$this->assertSame(
			20,
			has_filter( sprintf( 'rest_prepare_%s', $test_pt ), array( $instance, 'hide_password_protected_meta' ) ),
			'The password-protected meta filter should wire after the geo refresh on event-date-supporting post types.'
		);
		$this->assertTrue(
			post_type_supports( $test_pt, 'custom-fields' ),
			'custom-fields support should be auto-added so the REST controller exposes the meta field.'
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
			update_post_meta( $post_id, 'gatherpress_guest_limit', 5 ),
			'Should allow meta update for guest_limit.'
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
			update_post_meta( $post_id, 'gatherpress_capacity', 100 ),
			'Should allow meta update for capacity.'
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
				'geo_latitude'                   => '40.7128',
				'geo_longitude'                  => '-74.006',
				'geo_address'                    => '123 Main St',
				'geo_public'                     => 1,
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
		$this->assertArrayNotHasKey( 'geo_latitude', $filtered_meta );
		$this->assertArrayNotHasKey( 'geo_longitude', $filtered_meta );
		$this->assertArrayNotHasKey( 'geo_address', $filtered_meta );
		$this->assertArrayNotHasKey( 'geo_public', $filtered_meta );
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
				'gatherpress_guest_limit'           => 5,
				'gatherpress_capacity'              => 100,
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
		$this->assertArrayHasKey( 'gatherpress_guest_limit', $filtered_meta );
		$this->assertArrayHasKey( 'gatherpress_capacity', $filtered_meta );
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
				'geo_latitude'                   => '40.7128',
				'geo_longitude'                  => '-74.006',
				'geo_address'                    => '123 Main St',
				'geo_public'                     => 1,
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

	/**
	 * The all-day flag is registered with the rest of the event-date band.
	 *
	 * Every post type supporting `gatherpress-event-date` gets it, not only
	 * the canonical event post type.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::register_event_date_meta
	 *
	 * @return void
	 */
	public function test_registers_the_all_day_meta(): void {
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_is_all_day' );
		unregister_post_meta( Event::POST_TYPE, 'gatherpress_show_timezone' );

		Meta::get_instance()->register( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_is_all_day',
			$meta,
			'Failed to assert the all-day flag is registered.'
		);

		$this->assertFalse(
			$meta['gatherpress_is_all_day']['default'],
			'Failed to assert an event is timed by default.'
		);

		// Writable, unlike the datetime keys derived from gatherpress_datetime.
		$this->assertSame(
			'boolean',
			$meta['gatherpress_is_all_day']['type'],
			'Failed to assert the flag is a boolean.'
		);

		$this->assertArrayHasKey(
			'gatherpress_show_timezone',
			$meta,
			'Failed to assert the timezone preference is registered.'
		);

		$this->assertSame(
			'',
			$meta['gatherpress_show_timezone']['default'],
			'Failed to assert an event leaves the timezone to the block by default.'
		);
	}

	/**
	 * A password-protected event's date and place are blanked for viewers who
	 * can't see the event, and kept for those who can.
	 *
	 * @since TBD
	 *
	 * @covers ::hide_password_protected_meta
	 *
	 * @return void
	 */
	public function test_hide_password_protected_meta(): void {
		$instance  = Meta::get_instance();
		$protected = $this->factory()->post->create(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		);
		$open      = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$meta      = array(
			'gatherpress_datetime_start' => '2026-11-20 18:00:00',
			'gatherpress_timezone'       => 'UTC',
			'geo_address'                => '1 Main St',
			'gatherpress_is_all_day'     => false,
		);
		$hide      = static function ( int $post_id, string $password = '' ) use ( $instance, $meta ): array {
			$request = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_events/' . $post_id );

			if ( '' !== $password ) {
				$request->set_param( 'password', $password );
			}

			return $instance->hide_password_protected_meta(
				new WP_REST_Response( array( 'meta' => $meta ) ),
				get_post( $post_id ),
				$request
			)->data['meta'];
		};

		wp_set_current_user( 0 );

		$hidden = $hide( $protected );

		$this->assertSame( '', $hidden['gatherpress_datetime_start'], 'The date should be blank without a password.' );
		$this->assertSame( '', $hidden['gatherpress_timezone'], 'The timezone should be blank without a password.' );
		$this->assertSame( '', $hidden['geo_address'], 'The address should be blank without a password.' );
		$this->assertFalse( $hidden['gatherpress_is_all_day'], 'Other meta should be left alone.' );
		$this->assertSame( '', $hide( $protected, 'wrong' )['geo_address'], 'A wrong password hides the place.' );
		$this->assertSame(
			$meta['gatherpress_datetime_start'],
			$hide( $protected, 'secret' )['gatherpress_datetime_start'],
			'The REST password argument should reveal the date.'
		);
		$this->assertSame( $meta['geo_address'], $hide( $open )['geo_address'], 'An open event keeps its place.' );

		add_filter( 'post_password_required', '__return_false' );
		$with_cookie = $hide( $protected );
		remove_filter( 'post_password_required', '__return_false' );

		$this->assertSame(
			$meta['gatherpress_datetime_start'],
			$with_cookie['gatherpress_datetime_start'],
			'A viewer who entered the password should get the date.'
		);

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame(
			$meta['geo_address'],
			$hide( $protected )['geo_address'],
			'An Editor should keep the place, so the editor loads what it saves.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * The REST API blanks a password-protected event's date on every post type
	 * with event-date support, not only the built-in event post type.
	 *
	 * @since TBD
	 *
	 * @covers ::register_event_date_meta
	 * @covers ::hide_password_protected_meta
	 *
	 * @return void
	 */
	public function test_rest_response_hides_a_protected_events_date_on_any_event_post_type(): void {
		$test_pt = 'test_protected_event';

		register_post_type(
			$test_pt,
			array(
				'label'        => 'Test Protected Event',
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'test-protected-events',
				'supports'     => array( 'title', 'gatherpress-event-date' ),
			)
		);

		// Creating the server fires rest_api_init, so registering the post
		// type's routes afterwards is allowed.
		rest_get_server();
		( new WP_REST_Posts_Controller( $test_pt ) )->register_routes();

		$post_id = $this->factory()->post->create(
			array(
				'post_type'     => $test_pt,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => '2026-11-20 18:00:00',
				'datetime_end'   => '2026-11-20 20:00:00',
				'timezone'       => 'UTC',
			)
		);

		wp_set_current_user( 0 );

		$route   = sprintf( '/wp/v2/test-protected-events/%d', $post_id );
		$without = rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();
		$request = new WP_REST_Request( 'GET', $route );

		$request->set_param( 'password', 'secret' );

		$with = rest_do_request( $request )->get_data();

		unregister_post_type( $test_pt );

		$this->assertSame( '', $without['meta']['gatherpress_datetime_start'], 'Anonymous viewers get no date.' );
		$this->assertSame(
			'2026-11-20 18:00:00',
			$with['meta']['gatherpress_datetime_start'],
			'The right password should return the date.'
		);
	}

	/**
	 * The place stays hidden on a read where the geo refresh writes fresh
	 * venue values into the response.
	 *
	 * @since TBD
	 *
	 * @covers ::register_event_date_meta
	 * @covers ::hide_password_protected_meta
	 *
	 * @return void
	 */
	public function test_rest_response_hides_a_protected_events_refreshed_place(): void {
		// Another test in this class leaves the event meta unregistered, and the
		// point here is what the registered keys do in a response.
		Meta::get_instance()->register( Event::POST_TYPE );

		$venue = $this->factory()->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_name'   => 'protected-event-venue',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $venue, 'gatherpress_address', 'Tokyo, Japan' );
		update_post_meta( $venue, 'gatherpress_latitude', '35.6762' );
		update_post_meta( $venue, 'gatherpress_longitude', '139.6503' );

		$event_id = $this->factory()->post->create(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);

		( new Event( $event_id ) )->save_datetimes(
			array(
				'datetime_start' => '2099-01-01 10:00:00',
				'datetime_end'   => '2099-01-01 12:00:00',
				'timezone'       => 'UTC',
			)
		);
		wp_set_post_terms(
			$event_id,
			Venue_Setup::get_instance()->term_slug_from_post_name( 'protected-event-venue' ),
			Venue::TAXONOMY
		);

		wp_set_current_user( 0 );

		$route   = sprintf( '/wp/v2/gatherpress_events/%d', $event_id );
		$without = rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();

		$this->assertSame(
			'Tokyo, Japan',
			get_post_meta( $event_id, 'geo_address', true ),
			'The read should have refreshed the stored place.'
		);
		$this->assertSame( '', $without['meta']['geo_address'], 'Anonymous viewers get no address.' );
		$this->assertSame( '', $without['meta']['geo_latitude'], 'Anonymous viewers get no latitude.' );

		$request = new WP_REST_Request( 'GET', $route );

		$request->set_param( 'password', 'secret' );

		$this->assertSame(
			'Tokyo, Japan',
			rest_do_request( $request )->get_data()['meta']['geo_address'],
			'The right password should return the address.'
		);
	}

	/**
	 * An open event at a password-protected venue keeps the location it copied
	 * from that venue out of REST, and keeps its date.
	 *
	 * @since TBD
	 *
	 * @covers ::hide_password_protected_meta
	 *
	 * @return void
	 */
	public function test_hide_password_protected_meta_for_a_protected_venue(): void {
		register_taxonomy_for_object_type( Venue::TAXONOMY, Event::POST_TYPE );

		$instance     = Meta::get_instance();
		$meta         = array(
			'gatherpress_datetime_start' => '2026-11-20 18:00:00',
			'geo_address'                => '1 Main St',
			'geo_latitude'               => '35.6762',
			'geo_public'                 => 1,
		);
		$at_venue     = function ( string $password ): int {
			$venue_id = $this->factory->post->create(
				array(
					'post_type'     => Venue::POST_TYPE,
					'post_status'   => 'publish',
					'post_password' => $password,
				)
			);
			$event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

			wp_set_post_terms( $event_id, ( new Venue( $venue_id ) )->get_term_slug(), Venue::TAXONOMY );

			return $event_id;
		};
		$hide         = static function ( int $post_id ) use ( $instance, $meta ): array {
			return $instance->hide_password_protected_meta(
				new WP_REST_Response( array( 'meta' => $meta ) ),
				get_post( $post_id ),
				new WP_REST_Request( 'GET', '/wp/v2/gatherpress_events/' . $post_id )
			)->data['meta'];
		};
		$at_protected = $at_venue( 'secret' );
		$at_open      = $at_venue( '' );

		wp_set_current_user( 0 );

		$hidden = $hide( $at_protected );

		$this->assertSame( '', $hidden['geo_address'], 'The copied address should be blank.' );
		$this->assertSame( '', $hidden['geo_latitude'], 'The copied latitude should be blank.' );
		$this->assertSame(
			$meta['gatherpress_datetime_start'],
			$hidden['gatherpress_datetime_start'],
			'The date stays.'
		);
		$this->assertSame( 1, $hidden['geo_public'], 'The public flag is left alone.' );
		$this->assertSame( $meta, $hide( $at_open ), 'An open venue\'s event keeps its location.' );

		// The event's own Author keeps the venue, but not the location copied
		// from a venue they can't see.
		$author = $this->factory->user->create( array( 'role' => 'author' ) );

		wp_update_post(
			array(
				'ID'          => $at_protected,
				'post_author' => $author,
			)
		);
		wp_set_current_user( $author );

		$this->assertSame( '', $hide( $at_protected )['geo_address'], 'The event\'s Author gets no copied address.' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( $meta, $hide( $at_protected ), 'An Editor sees the location.' );

		wp_set_current_user( 0 );
	}
}
