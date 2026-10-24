<?php
/**
 * Test class for Geo_Sync.
 *
 * @package GatherPress\Tests\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Geo_Sync;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Response;

/**
 * Class Test_Geo_Sync.
 *
 * @since 0.36.0
 *
 * @coversDefaultClass \GatherPress\Core\Geo_Sync
 */
class Test_Geo_Sync extends Base {

	/**
	 * `register_meta()` registers all four Geodata standard keys, read-only.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::register_meta
	 *
	 * @return void
	 */
	public function test_register_meta_registers_all_fields_read_only(): void {
		foreach ( Geo_Sync::FIELDS as $key ) {
			unregister_post_meta( Event::POST_TYPE, $key );
		}

		Geo_Sync::register_meta( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		foreach ( Geo_Sync::FIELDS as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert %s is registered.', $key )
			);
			$this->assertFalse(
				call_user_func( $meta[ $key ]['auth_callback'] ),
				sprintf( 'Failed to assert %s is read-only.', $key )
			);
		}
	}

	/**
	 * `register_meta()` registers `geo_public` as an integer, matching
	 * Simple Location's own 0/1/2 semantics; the other three as strings.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::register_meta
	 *
	 * @return void
	 */
	public function test_register_meta_types(): void {
		foreach ( Geo_Sync::FIELDS as $key ) {
			unregister_post_meta( Event::POST_TYPE, $key );
		}

		Geo_Sync::register_meta( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertSame( 'integer', $meta['geo_public']['type'] );
		$this->assertSame( 'string', $meta['geo_latitude']['type'] );
		$this->assertSame( 'string', $meta['geo_longitude']['type'] );
		$this->assertSame( 'string', $meta['geo_address']['type'] );
	}

	/**
	 * Maps venue information onto the `geo_*` keys, with geo_public as 1.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::build_values
	 *
	 * @return void
	 */
	public function test_build_values_from_information(): void {
		$values = Geo_Sync::build_values(
			array(
				'latitude'  => '40.7',
				'longitude' => '-74.0',
				'address'   => '1 Main St',
			),
			true
		);

		$this->assertSame(
			array(
				'geo_latitude'  => '40.7',
				'geo_longitude' => '-74.0',
				'geo_address'   => '1 Main St',
				'geo_public'    => 1,
			),
			$values
		);
	}

	/**
	 * Empty information clears the location, with geo_public as 0.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::build_values
	 *
	 * @return void
	 */
	public function test_build_values_from_empty_information(): void {
		$this->assertSame(
			array(
				'geo_latitude'  => '',
				'geo_longitude' => '',
				'geo_address'   => '',
				'geo_public'    => 0,
			),
			Geo_Sync::build_values( array(), false )
		);
	}

	/**
	 * Writes changed values and reports a change.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_persists_changed_values(): void {
		$post_id = $this->factory->post->create();
		$values  = Geo_Sync::build_values( array( 'latitude' => '1.5' ), true );

		$this->assertTrue( Geo_Sync::write( $post_id, $values ) );
		$this->assertSame( '1.5', get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Stores empty/zero values on a post that has no geo meta yet.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_stores_empty_values_when_missing(): void {
		$post_id = $this->factory->post->create();

		$this->assertTrue( Geo_Sync::write( $post_id, Geo_Sync::build_values( array(), false ) ) );
		$this->assertTrue( metadata_exists( 'post', $post_id, 'geo_public' ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Reports no change when every value is already current.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_skips_current_values(): void {
		$post_id = $this->factory->post->create();
		$values  = Geo_Sync::build_values( array( 'latitude' => '1.5' ), true );

		Geo_Sync::write( $post_id, $values );

		$this->assertFalse( Geo_Sync::write( $post_id, $values ) );
	}


	/**
	 * `on_venue_saved()` derives geo_* meta from the venue's own fields on
	 * a published venue.
	 *
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_on_venue_saved_derives_geo_meta_for_published_venue(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		update_post_meta( $venue->ID, 'gatherpress_latitude', '40.7128' );
		update_post_meta( $venue->ID, 'gatherpress_longitude', '-74.006' );
		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main St, New York, NY' );

		Geo_Sync::get_instance()->on_venue_saved( $venue->ID, $venue );

		$this->assertSame( '40.7128', get_post_meta( $venue->ID, 'geo_latitude', true ) );
		$this->assertSame( '-74.006', get_post_meta( $venue->ID, 'geo_longitude', true ) );
		$this->assertSame(
			'123 Main St, New York, NY',
			get_post_meta( $venue->ID, 'geo_address', true )
		);
		// get_post_meta() always returns strings — DB storage is text
		// regardless of the registered 'integer' meta type.
		$this->assertSame( '1', get_post_meta( $venue->ID, 'geo_public', true ) );
	}

	/**
	 * `on_venue_saved()` writes `geo_public` as `0` for a venue that isn't
	 * published (e.g. a draft).
	 *
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_on_venue_saved_writes_not_public_for_draft_venue(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get();

		Geo_Sync::get_instance()->on_venue_saved( $venue->ID, $venue );

		$this->assertSame( '0', get_post_meta( $venue->ID, 'geo_public', true ) );
	}

	/**
	 * `on_venue_saved()` is a no-op for a revision.
	 *
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_on_venue_saved_skips_revisions(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		$revision_id = wp_save_post_revision( $venue->ID );
		$this->assertIsInt( $revision_id, 'A revision should have been created.' );

		$revision = get_post( $revision_id );

		Geo_Sync::get_instance()->on_venue_saved( $revision_id, $revision );

		$this->assertSame(
			'',
			get_post_meta( $revision_id, 'geo_latitude', true ),
			'A revision save must not write geo meta.'
		);
	}

	/**
	 * `on_venue_saved()` is a no-op for an autosave. Built directly with
	 * `wp_insert_post()` rather than the wp-admin autosave flow, matching
	 * the shape `wp_is_post_autosave()` checks for.
	 *
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_on_venue_saved_skips_autosaves(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		$autosave_id = wp_insert_post(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $venue->ID,
				'post_name'   => "{$venue->ID}-autosave-v1",
				'post_title'  => $venue->post_title,
			)
		);
		$this->assertIsInt( $autosave_id, 'An autosave-shaped revision should have been created.' );
		$this->assertNotFalse(
			wp_is_post_autosave( $autosave_id ),
			'Failed to assert the fixture is actually recognized as an autosave.'
		);

		$autosave = get_post( $autosave_id );

		Geo_Sync::get_instance()->on_venue_saved( $autosave_id, $autosave );

		$this->assertSame(
			'',
			get_post_meta( $autosave_id, 'geo_latitude', true ),
			'An autosave save must not write geo meta.'
		);
	}

	/**
	 * `on_venue_saved()` ignores a post type without venue-information
	 * support.
	 *
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_on_venue_saved_ignores_unrelated_post_type(): void {
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		Geo_Sync::get_instance()->on_venue_saved( $post->ID, $post );

		$this->assertSame( '', get_post_meta( $post->ID, 'geo_latitude', true ) );
	}

	/**
	 * Create a venue with the given information, and a taxonomy term
	 * linking it up for `wp_set_post_terms()` calls.
	 *
	 * @param string $post_name Venue post_name (also used as the term slug source).
	 * @param array  $meta      Venue information to seed (address/latitude/longitude).
	 * @param string $status    Venue post status. Defaults to published.
	 *
	 * @return array{post: \WP_Post, term_slug: string}
	 */
	protected function create_linked_venue(
		string $post_name,
		array $meta = array(),
		string $status = 'publish'
	): array {
		$venue_setup = Venue_Setup::get_instance();
		$venue_post  = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_name'   => $post_name,
				'post_title'  => $post_name,
				'post_status' => $status,
			)
		)->get();

		foreach ( $meta as $key => $value ) {
			update_post_meta( $venue_post->ID, 'gatherpress_' . $key, $value );
		}

		$term_slug = $venue_setup->term_slug_from_post_name( $venue_post->post_name );
		wp_insert_term( $venue_post->post_name, Venue::TAXONOMY, array( 'slug' => $term_slug ) );

		return array(
			'post'      => $venue_post,
			'term_slug' => $term_slug,
		);
	}

	/**
	 * Create an event, optionally linked to a venue, with given start/end
	 * datetimes.
	 *
	 * @param string      $start     Start datetime, MySQL format.
	 * @param string      $end       End datetime, MySQL format.
	 * @param string|null $term_slug Venue taxonomy term slug to link, or null for no venue.
	 * @param string      $status    Post status.
	 *
	 * @return \WP_Post
	 */
	protected function create_event(
		string $start,
		string $end,
		?string $term_slug = null,
		string $status = 'publish'
	) {
		$event = $this->mock->post(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => $status,
			)
		)->get();

		( new Event( $event->ID ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'America/New_York',
			)
		);

		if ( null !== $term_slug ) {
			wp_set_post_terms( $event->ID, $term_slug, Venue::TAXONOMY );
		}

		return $event;
	}

	/**
	 * `maybe_refresh()` writes fresh geo_* meta for an upcoming event
	 * linked to a venue.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_writes_fresh_values_for_upcoming_event(): void {
		$venue = $this->create_linked_venue(
			'test-maybe-refresh-venue',
			array(
				'latitude'  => '40.7128',
				'longitude' => '-74.006',
				'address'   => '123 Main St, New York, NY',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertSame(
			array(
				'geo_latitude'  => '40.7128',
				'geo_longitude' => '-74.006',
				'geo_address'   => '123 Main St, New York, NY',
				'geo_public'    => 1,
			),
			$result,
			'Failed to assert the fresh values are returned.'
		);
		$this->assertSame( '40.7128', get_post_meta( $event->ID, 'geo_latitude', true ) );
		$this->assertSame( '-74.006', get_post_meta( $event->ID, 'geo_longitude', true ) );
		$this->assertSame(
			'123 Main St, New York, NY',
			get_post_meta( $event->ID, 'geo_address', true )
		);
		// get_post_meta() always returns strings — DB storage is text
		// regardless of the registered 'integer' meta type.
		$this->assertSame( '1', get_post_meta( $event->ID, 'geo_public', true ) );
	}

	/**
	 * `maybe_refresh()` returns null and writes nothing for an event that
	 * has already happened — a past event's recorded location isn't
	 * retroactively updated.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_skips_past_event(): void {
		$venue = $this->create_linked_venue(
			'test-past-event-venue',
			array(
				'latitude'  => '40.7128',
				'longitude' => '-74.006',
				'address'   => '123 Main St, New York, NY',
			)
		);
		$event = $this->create_event( '2020-01-01 10:00:00', '2020-01-01 12:00:00', $venue['term_slug'] );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertNull( $result, 'Failed to assert a past event is skipped.' );
		$this->assertSame(
			'',
			get_post_meta( $event->ID, 'geo_latitude', true ),
			'Failed to assert no meta was written for a past event.'
		);
	}

	/**
	 * `maybe_refresh()` returns null and makes no write when the stored
	 * values already match the venue's current data.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_returns_null_when_already_current(): void {
		$venue = $this->create_linked_venue(
			'test-already-current-venue',
			array(
				'latitude'  => '40.7128',
				'longitude' => '-74.006',
				'address'   => '123 Main St, New York, NY',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		// Prime the meta so it already matches.
		Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertNull( $result, 'Failed to assert an already-current event returns null.' );
	}

	/**
	 * `maybe_refresh()` clears geo_* meta to empty when the event has no
	 * venue association (e.g. an online-only event), and geo_public still
	 * reflects the event's own publish status.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_clears_meta_without_a_venue(): void {
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', null );

		update_post_meta( $event->ID, 'geo_latitude', '40.7128' );
		update_post_meta( $event->ID, 'geo_longitude', '-74.006' );
		update_post_meta( $event->ID, 'geo_address', 'Stale Address' );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertSame(
			array(
				'geo_latitude'  => '',
				'geo_longitude' => '',
				'geo_address'   => '',
				'geo_public'    => 1,
			),
			$result
		);
		$this->assertSame( '', get_post_meta( $event->ID, 'geo_latitude', true ) );
	}

	/**
	 * `maybe_refresh()` leaves an event without a venue empty even while a
	 * venue is the global post, rather than reading that venue's location.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_ignores_the_global_venue_without_a_venue(): void {
		$venue = $this->create_linked_venue(
			'test-global-venue',
			array(
				'latitude'  => '48.8566',
				'longitude' => '2.3522',
				'address'   => 'Paris, France',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', null );

		$this->go_to( get_permalink( $venue['post'] ) );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertSame(
			'',
			$result['geo_address'],
			'Failed to assert the global venue post did not stand in for the missing venue.'
		);
	}

	/**
	 * Only a published post's location is public.
	 *
	 * Called directly because the sync methods reach it through same-class
	 * calls that xdebug does not trace.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_public
	 *
	 * @return void
	 */
	public function test_is_public(): void {
		$geo_sync = Geo_Sync::get_instance();

		$this->assertFalse(
			Utility::invoke_hidden_method( $geo_sync, 'is_public', array( null ) ),
			'Failed to assert a missing post is not public.'
		);

		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			$post = $this->mock->post( array( 'post_status' => $status ) )->get();

			$this->assertFalse(
				Utility::invoke_hidden_method( $geo_sync, 'is_public', array( $post ) ),
				sprintf( 'Failed to assert a %s post is not public.', $status )
			);
		}

		$this->assertTrue(
			Utility::invoke_hidden_method(
				$geo_sync,
				'is_public',
				array( $this->mock->post( array( 'post_status' => 'publish' ) )->get() )
			),
			'Failed to assert a published post is public.'
		);
	}

	/**
	 * `maybe_refresh()` writes `geo_public` as `0` for a draft event.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_sets_geo_public_zero_for_draft_event(): void {
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', null, 'draft' );

		// Seed a differing prior value so there's an actual change to detect.
		update_post_meta( $event->ID, 'geo_public', 1 );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertSame( 0, $result['geo_public'] );
		$this->assertSame( '0', get_post_meta( $event->ID, 'geo_public', true ) );
	}

	/**
	 * `maybe_refresh()` clears geo_* meta and forces `geo_public` to `0`
	 * for an event linked to a draft venue, even though the event itself
	 * is published — a draft venue's real location must not leak out.
	 *
	 * @covers ::maybe_refresh
	 *
	 * @return void
	 */
	public function test_maybe_refresh_hides_location_for_unpublished_venue(): void {
		$venue = $this->create_linked_venue(
			'test-draft-venue',
			array(
				'latitude'  => '40.7128',
				'longitude' => '-74.006',
				'address'   => '123 Main St, New York, NY',
			),
			'draft'
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		// Seed stale, visible values so the hide-on-refresh is an actual detectable change.
		update_post_meta( $event->ID, 'geo_latitude', '40.7128' );
		update_post_meta( $event->ID, 'geo_longitude', '-74.006' );
		update_post_meta( $event->ID, 'geo_address', '123 Main St, New York, NY' );
		update_post_meta( $event->ID, 'geo_public', 1 );

		$result = Geo_Sync::get_instance()->maybe_refresh( $event->ID );

		$this->assertSame(
			array(
				'geo_latitude'  => '',
				'geo_longitude' => '',
				'geo_address'   => '',
				'geo_public'    => 0,
			),
			$result
		);
		$this->assertSame( '', get_post_meta( $event->ID, 'geo_latitude', true ) );
		$this->assertSame( '0', get_post_meta( $event->ID, 'geo_public', true ) );
	}

	/**
	 * `maybe_refresh_on_view()` refreshes the queried event on a
	 * front-end single view.
	 *
	 * @covers ::maybe_refresh_on_view
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_view_refreshes_singular_supporting_post_type(): void {
		$venue = $this->create_linked_venue(
			'test-view-venue',
			array(
				'latitude'  => '51.5074',
				'longitude' => '-0.1278',
				'address'   => 'London, UK',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		$this->go_to( get_permalink( $event->ID ) );

		Geo_Sync::get_instance()->maybe_refresh_on_view();

		$this->assertSame( '51.5074', get_post_meta( $event->ID, 'geo_latitude', true ) );
	}

	/**
	 * `maybe_refresh_on_view()` is a no-op when the current request isn't
	 * a singular view (e.g. an archive).
	 *
	 * @covers ::maybe_refresh_on_view
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_view_skips_non_singular(): void {
		$venue = $this->create_linked_venue(
			'test-non-singular-venue',
			array(
				'latitude'  => '10',
				'longitude' => '20',
				'address'   => 'X',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		$this->go_to( home_url( '/' ) );

		Geo_Sync::get_instance()->maybe_refresh_on_view();

		$this->assertSame(
			'',
			get_post_meta( $event->ID, 'geo_latitude', true ),
			'Failed to assert nothing was written on a non-singular request.'
		);
	}

	/**
	 * `maybe_refresh_on_view()` is a no-op for a singular view of a post
	 * type without venue-assignment support.
	 *
	 * @covers ::maybe_refresh_on_view
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_view_skips_unsupported_post_type(): void {
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		$this->go_to( get_permalink( $post->ID ) );

		Geo_Sync::get_instance()->maybe_refresh_on_view();

		$this->assertSame( '', get_post_meta( $post->ID, 'geo_latitude', true ) );
	}

	/**
	 * `maybe_refresh_on_rest()` patches the response's `meta` in addition
	 * to persisting it, since `rest_prepare_` fires after serialization.
	 *
	 * @covers ::maybe_refresh_on_rest
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_rest_patches_response_meta(): void {
		$venue = $this->create_linked_venue(
			'test-rest-venue',
			array(
				'latitude'  => '35.6762',
				'longitude' => '139.6503',
				'address'   => 'Tokyo, Japan',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		$response       = new WP_REST_Response();
		$response->data = array(
			'id'   => $event->ID,
			'meta' => array(
				'geo_latitude' => '0',
			),
		);

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event );

		$this->assertSame( '35.6762', $result->data['meta']['geo_latitude'] );
		$this->assertSame( '139.6503', $result->data['meta']['geo_longitude'] );
		$this->assertSame( 'Tokyo, Japan', $result->data['meta']['geo_address'] );
		$this->assertSame( 1, $result->data['meta']['geo_public'] );
		$this->assertSame(
			'35.6762',
			get_post_meta( $event->ID, 'geo_latitude', true ),
			'Failed to assert the value was also persisted, not just patched into the response.'
		);
	}

	/**
	 * `maybe_refresh_on_rest()` leaves the response untouched when nothing
	 * changed (e.g. a past event, or already-current data).
	 *
	 * @covers ::maybe_refresh_on_rest
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_rest_leaves_response_untouched_when_nothing_changed(): void {
		$event = $this->create_event( '2020-01-01 10:00:00', '2020-01-01 12:00:00', null );

		$response       = new WP_REST_Response();
		$response->data = array(
			'id'   => $event->ID,
			'meta' => array(
				'geo_latitude' => 'untouched',
			),
		);

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event );

		$this->assertSame( 'untouched', $result->data['meta']['geo_latitude'] );
	}

	/**
	 * `maybe_refresh_on_rest()` doesn't error when the response has no
	 * `meta` entry in its data at all.
	 *
	 * @covers ::maybe_refresh_on_rest
	 *
	 * @return void
	 */
	public function test_maybe_refresh_on_rest_handles_missing_meta_in_response_data(): void {
		$venue = $this->create_linked_venue(
			'test-rest-no-meta-venue',
			array(
				'latitude'  => '1',
				'longitude' => '2',
				'address'   => 'X',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		$response       = new WP_REST_Response();
		$response->data = array( 'id' => $event->ID );

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event );

		$this->assertArrayNotHasKey( 'meta', $result->data );
		$this->assertSame(
			'1',
			get_post_meta( $event->ID, 'geo_latitude', true ),
			'Failed to assert the value was still persisted even though the response had no meta to patch.'
		);
	}

	/**
	 * A password-protected venue keeps its location to itself: its events get
	 * none, and its own copy is marked not public.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::is_public
	 * @covers ::maybe_refresh
	 * @covers ::on_venue_saved
	 *
	 * @return void
	 */
	public function test_password_protected_venue_keeps_its_location(): void {
		$venue = $this->create_linked_venue(
			'protected-venue',
			array(
				'latitude'  => '35.6762',
				'longitude' => '139.6503',
				'address'   => 'Tokyo, Japan',
			)
		);
		$event = $this->create_event( '2099-01-01 10:00:00', '2099-01-01 12:00:00', $venue['term_slug'] );

		wp_update_post(
			array(
				'ID'            => $venue['post']->ID,
				'post_password' => 'secret',
			)
		);

		$instance = Geo_Sync::get_instance();

		$instance->maybe_refresh( $event->ID );
		$instance->on_venue_saved( $venue['post']->ID, get_post( $venue['post']->ID ) );

		$this->assertSame( '', get_post_meta( $event->ID, 'geo_address', true ), 'The event gets no address.' );
		$this->assertSame( '', get_post_meta( $event->ID, 'geo_latitude', true ), 'The event gets no latitude.' );
		$this->assertSame(
			'Tokyo, Japan',
			get_post_meta( $venue['post']->ID, 'geo_address', true ),
			'The venue keeps its own address.'
		);
		$this->assertSame( '0', get_post_meta( $venue['post']->ID, 'geo_public', true ), 'The venue is not public.' );
	}

	/**
	 * Saving a venue that isn't public clears the location its events copied,
	 * past events included, while a public venue's events keep theirs.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::on_venue_saved
	 * @covers ::clear_event_copies
	 *
	 * @return void
	 */
	public function test_on_venue_saved_clears_copies_when_the_venue_is_not_public(): void {
		$information = array(
			'latitude'  => '35.6762',
			'longitude' => '139.6503',
			'address'   => 'Tokyo, Japan',
		);
		$venue       = $this->create_linked_venue( 'locking-venue', $information );
		$past        = $this->create_event( '2001-01-01 10:00:00', '2001-01-01 12:00:00', $venue['term_slug'] );
		$instance    = Geo_Sync::get_instance();

		Geo_Sync::write( $past->ID, Geo_Sync::build_values( $information, true ) );
		$instance->on_venue_saved( $venue['post']->ID, get_post( $venue['post']->ID ) );

		$this->assertSame(
			'Tokyo, Japan',
			get_post_meta( $past->ID, 'geo_address', true ),
			'A public venue leaves its events alone.'
		);

		$protected                = get_post( $venue['post']->ID );
		$protected->post_password = 'secret';

		$instance->on_venue_saved( $protected->ID, $protected );

		$this->assertSame( '', get_post_meta( $past->ID, 'geo_address', true ), 'The past event loses the address.' );
		$this->assertSame( '', get_post_meta( $past->ID, 'geo_latitude', true ), 'The past event loses the latitude.' );
		$this->assertSame( '0', get_post_meta( $past->ID, 'geo_public', true ), 'The past event is not public.' );

		Geo_Sync::write( $past->ID, Geo_Sync::build_values( $information, true ) );
		Utility::invoke_hidden_method( $instance, 'clear_event_copies', array( $protected ) );

		$this->assertSame( '', get_post_meta( $past->ID, 'geo_address', true ), 'A direct call clears the copy too.' );
	}

	/**
	 * A venue that was never published has no term, so there is nothing to clear.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::clear_event_copies
	 *
	 * @return void
	 */
	public function test_clear_event_copies_without_a_term(): void {
		$draft = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
				'post_name'   => 'never-published',
			)
		)->get();

		Utility::invoke_hidden_method( Geo_Sync::get_instance(), 'clear_event_copies', array( $draft ) );

		$this->assertNull( term_exists( '_never-published', Venue::TAXONOMY ), 'No term was created or touched.' );
	}

	/**
	 * A venue that stops being public and becomes public again gives each
	 * event back the exact location it had, even if the venue's address
	 * changed in between, so a past event keeps where it happened.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::on_venue_saved
	 * @covers ::clear_event_copies
	 * @covers ::restore_event_copies
	 *
	 * @return void
	 */
	public function test_on_venue_saved_restores_cleared_copies(): void {
		$information = array(
			'latitude'  => '35.6762',
			'longitude' => '139.6503',
			'address'   => 'Tokyo, Japan',
		);
		$venue       = $this->create_linked_venue( 'round-trip-venue', $information );
		$past        = $this->create_event( '2001-01-01 10:00:00', '2001-01-01 12:00:00', $venue['term_slug'] );
		$draft       = $this->create_event(
			'2001-02-01 10:00:00',
			'2001-02-01 12:00:00',
			$venue['term_slug'],
			'draft'
		);
		$instance    = Geo_Sync::get_instance();
		$post        = get_post( $venue['post']->ID );

		Geo_Sync::write( $past->ID, Geo_Sync::build_values( $information, true ) );
		Geo_Sync::write( $draft->ID, Geo_Sync::build_values( $information, false ) );

		$post->post_status = 'draft';
		$instance->on_venue_saved( $post->ID, $post );

		$this->assertSame( '', get_post_meta( $past->ID, 'geo_address', true ), 'The draft venue clears the copy.' );
		$this->assertSame(
			array( $past->ID, $draft->ID ),
			array_keys( get_post_meta( $post->ID, Geo_Sync::CLEARED_COPIES_META_KEY, true ) ),
			'The venue keeps the copies it cleared.'
		);

		update_post_meta( $post->ID, 'gatherpress_address', 'Osaka, Japan' );

		$post->post_status = 'publish';
		$instance->on_venue_saved( $post->ID, $post );

		$this->assertSame(
			'Tokyo, Japan',
			get_post_meta( $past->ID, 'geo_address', true ),
			'The past event keeps where it happened.'
		);
		$this->assertSame( '35.6762', get_post_meta( $past->ID, 'geo_latitude', true ) );
		$this->assertSame( '139.6503', get_post_meta( $past->ID, 'geo_longitude', true ) );
		$this->assertSame( '1', get_post_meta( $past->ID, 'geo_public', true ), 'A published event is public again.' );
		$this->assertSame( 'Tokyo, Japan', get_post_meta( $draft->ID, 'geo_address', true ) );
		$this->assertSame( '0', get_post_meta( $draft->ID, 'geo_public', true ), 'A draft event stays not public.' );
		$this->assertSame(
			'',
			get_post_meta( $post->ID, Geo_Sync::CLEARED_COPIES_META_KEY, true ),
			'The venue lets go of the copies once they are back.'
		);
	}

	/**
	 * Saving a venue again while it isn't public keeps the first copy, not the blank one.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::clear_event_copies
	 *
	 * @return void
	 */
	public function test_clear_event_copies_keeps_the_first_copy(): void {
		$information = array(
			'latitude'  => '48.8566',
			'longitude' => '2.3522',
			'address'   => 'Paris, France',
		);
		$venue       = $this->create_linked_venue( 'twice-hidden-venue', $information );
		$past        = $this->create_event( '2001-01-01 10:00:00', '2001-01-01 12:00:00', $venue['term_slug'] );
		$empty       = $this->create_event( '2001-03-01 10:00:00', '2001-03-01 12:00:00', $venue['term_slug'] );
		$instance    = Geo_Sync::get_instance();
		$post        = get_post( $venue['post']->ID );

		Geo_Sync::write( $past->ID, Geo_Sync::build_values( $information, true ) );

		Utility::invoke_hidden_method( $instance, 'clear_event_copies', array( $post ) );
		Utility::invoke_hidden_method( $instance, 'clear_event_copies', array( $post ) );

		$this->assertSame(
			array(
				$past->ID => array(
					'latitude'  => '48.8566',
					'longitude' => '2.3522',
					'address'   => 'Paris, France',
				),
			),
			get_post_meta( $post->ID, Geo_Sync::CLEARED_COPIES_META_KEY, true ),
			'The first copy is kept, and an event without a location has nothing to keep.'
		);
		$this->assertSame( '', get_post_meta( $empty->ID, 'geo_address', true ) );
	}

	/**
	 * An event deleted or moved to another venue since is left alone, and a
	 * venue with nothing kept does nothing.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::restore_event_copies
	 *
	 * @return void
	 */
	public function test_restore_event_copies_skips_events_no_longer_at_the_venue(): void {
		$venue    = $this->create_linked_venue( 'left-venue' );
		$other    = $this->create_linked_venue( 'other-venue' );
		$moved    = $this->create_event( '2001-01-01 10:00:00', '2001-01-01 12:00:00', $other['term_slug'] );
		$instance = Geo_Sync::get_instance();
		$location = array(
			'latitude'  => '1',
			'longitude' => '2',
			'address'   => 'Old Hall',
		);

		Utility::invoke_hidden_method( $instance, 'restore_event_copies', array( $venue['post'] ) );

		$this->assertSame( '', get_post_meta( $moved->ID, 'geo_address', true ), 'Nothing kept, nothing restored.' );

		update_post_meta(
			$venue['post']->ID,
			Geo_Sync::CLEARED_COPIES_META_KEY,
			array(
				$moved->ID => $location,
				999999     => $location,
			)
		);
		Utility::invoke_hidden_method( $instance, 'restore_event_copies', array( $venue['post'] ) );

		$this->assertSame( '', get_post_meta( $moved->ID, 'geo_address', true ), 'A moved event keeps what it has.' );
		$this->assertSame( '', get_post_meta( 999999, 'geo_address', true ), 'A deleted event gets nothing.' );
		$this->assertSame( '', get_post_meta( $venue['post']->ID, Geo_Sync::CLEARED_COPIES_META_KEY, true ) );
	}

	/**
	 * Saving a past event marks its location public or not, and keeps the location.
	 *
	 * @since TBD
	 *
	 * @covers ::on_event_saved
	 *
	 * @return void
	 */
	public function test_on_event_saved_marks_a_past_event_public_or_not(): void {
		$information = array(
			'latitude'  => '40.7128',
			'longitude' => '-74.006',
			'address'   => '123 Main St, New York, NY',
		);
		$venue       = $this->create_linked_venue( 'past-event-saved-venue', $information );
		$event       = $this->create_event( '2020-01-01 10:00:00', '2020-01-01 12:00:00', $venue['term_slug'] );

		Geo_Sync::write( $event->ID, Geo_Sync::build_values( $information, true ) );

		wp_update_post(
			array(
				'ID'            => $event->ID,
				'post_password' => 'secret',
			)
		);

		$this->assertSame(
			'0',
			get_post_meta( $event->ID, 'geo_public', true ),
			'Failed to assert a protected past event is not public.'
		);
		$this->assertSame(
			'123 Main St, New York, NY',
			get_post_meta( $event->ID, 'geo_address', true ),
			'Failed to assert the past event keeps its location.'
		);

		wp_update_post(
			array(
				'ID'            => $event->ID,
				'post_password' => '',
			)
		);

		$this->assertSame(
			'1',
			get_post_meta( $event->ID, 'geo_public', true ),
			'Failed to assert the past event is public again without the password.'
		);

		wp_update_post(
			array(
				'ID'          => $event->ID,
				'post_status' => 'draft',
			)
		);

		$this->assertSame(
			'0',
			get_post_meta( $event->ID, 'geo_public', true ),
			'Failed to assert an unpublished past event is not public.'
		);
	}

	/**
	 * Saving an event at a venue that isn't public marks its location not public.
	 *
	 * @since TBD
	 *
	 * @covers ::on_event_saved
	 *
	 * @return void
	 */
	public function test_on_event_saved_follows_the_venue(): void {
		$venue = $this->create_linked_venue( 'draft-event-saved-venue', array(), 'draft' );
		$event = $this->create_event( '2020-01-01 10:00:00', '2020-01-01 12:00:00', $venue['term_slug'] );

		update_post_meta( $event->ID, 'geo_public', 1 );

		Geo_Sync::get_instance()->on_event_saved( $event->ID, get_post( $event->ID ) );

		$this->assertSame( '0', get_post_meta( $event->ID, 'geo_public', true ) );
	}

	/**
	 * Saving a post type that can't have a venue writes nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::on_event_saved
	 *
	 * @return void
	 */
	public function test_on_event_saved_ignores_unrelated_post_type(): void {
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		Geo_Sync::get_instance()->on_event_saved( $post->ID, $post );

		$this->assertFalse( metadata_exists( 'post', $post->ID, 'geo_public' ) );
	}

	/**
	 * Covers `is_event_public()` directly.
	 *
	 * Called directly because `on_event_saved()` and `maybe_refresh()` reach it
	 * through same-class calls that xdebug does not trace.
	 *
	 * @since TBD
	 *
	 * @covers ::is_event_public
	 *
	 * @return void
	 */
	public function test_is_event_public(): void {
		$geo_sync    = Geo_Sync::get_instance();
		$event       = $this->mock->post( array( 'post_status' => 'publish' ) )->get();
		$protected   = $this->mock->post(
			array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		)->get();
		$venue       = $this->mock->post( array( 'post_status' => 'publish' ) )->get();
		$draft_venue = $this->mock->post( array( 'post_status' => 'draft' ) )->get();

		$this->assertTrue(
			Utility::invoke_hidden_method( $geo_sync, 'is_event_public', array( $event, null ) ),
			'Failed to assert a public event without a venue is public.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method( $geo_sync, 'is_event_public', array( $event, $venue ) ),
			'Failed to assert a public event at a public venue is public.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $geo_sync, 'is_event_public', array( $event, $draft_venue ) ),
			'Failed to assert an event at a venue that is not public is not public.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $geo_sync, 'is_event_public', array( $protected, null ) ),
			'Failed to assert a password-protected event is not public.'
		);
	}
}
