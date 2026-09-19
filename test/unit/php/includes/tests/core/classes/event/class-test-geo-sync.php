<?php
/**
 * Unit tests for GatherPress\Core\Event\Geo_Sync.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Geo_Sync;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Test_Geo_Sync.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Geo_Sync
 */
class Test_Geo_Sync extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Geo_Sync::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'template_redirect',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_on_view' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'register_rest_hook' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * `register_rest_hook()` wires the REST refresh filter for a post type
	 * with venue-assignment support.
	 *
	 * @covers ::register_rest_hook
	 *
	 * @return void
	 */
	public function test_register_rest_hook_wires_for_supporting_post_type(): void {
		Geo_Sync::get_instance()->register_rest_hook( Event::POST_TYPE );

		$this->assertNotFalse(
			has_filter(
				sprintf( 'rest_prepare_%s', Event::POST_TYPE ),
				array( Geo_Sync::get_instance(), 'maybe_refresh_on_rest' )
			),
			'REST refresh filter should wire for a post type with venue-assignment support.'
		);
	}

	/**
	 * `register_rest_hook()` skips a post type without venue-assignment
	 * support.
	 *
	 * @covers ::register_rest_hook
	 *
	 * @return void
	 */
	public function test_register_rest_hook_skips_unsupported_post_type(): void {
		Geo_Sync::get_instance()->register_rest_hook( 'post' );

		$this->assertFalse(
			has_filter( 'rest_prepare_post', array( Geo_Sync::get_instance(), 'maybe_refresh_on_rest' ) ),
			'REST refresh filter must not wire for a post type without venue-assignment support.'
		);
	}

	/**
	 * Create a published venue with the given information, and a taxonomy
	 * term linking it up for `wp_set_post_terms()` calls.
	 *
	 * @param string $post_name Venue post_name (also used as the term slug source).
	 * @param array  $meta      Venue information to seed (address/latitude/longitude).
	 *
	 * @return array{post: \WP_Post, term_slug: string}
	 */
	protected function create_linked_venue( string $post_name, array $meta = array() ): array {
		$venue_setup = Venue_Setup::get_instance();
		$venue_post  = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => $post_name,
				'post_title' => $post_name,
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
		$request        = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_event/' . $event->ID );

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event, $request );

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
		$request        = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_event/' . $event->ID );

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event, $request );

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
		$request        = new WP_REST_Request( 'GET', '/wp/v2/gatherpress_event/' . $event->ID );

		$result = Geo_Sync::get_instance()->maybe_refresh_on_rest( $response, $event, $request );

		$this->assertArrayNotHasKey( 'meta', $result->data );
		$this->assertSame(
			'1',
			get_post_meta( $event->ID, 'geo_latitude', true ),
			'Failed to assert the value was still persisted even though the response had no meta to patch.'
		);
	}
}
