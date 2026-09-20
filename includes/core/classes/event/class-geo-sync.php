<?php
/**
 * Keeps an event's Geodata standard meta (geo_latitude/longitude/address/
 * public) in sync with its venue, checked lazily on view rather than
 * pushed on every venue save — a venue can have ~1,000 linked events.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Geo_Sync.
 *
 * @since 0.36.0
 */
final class Geo_Sync {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor — wires hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for the lazy view-time geo refresh.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'template_redirect', array( $this, 'maybe_refresh_on_view' ) );
		add_action( 'registered_post_type', array( $this, 'register_rest_hook' ) );
	}

	/**
	 * Wires the REST refresh filter for a post type with venue-assignment
	 * support.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function register_rest_hook( string $post_type ): void {
		if ( ! post_type_supports( $post_type, Venue::ASSIGNMENT_SUPPORT ) ) {
			return;
		}

		add_filter( sprintf( 'rest_prepare_%s', $post_type ), array( $this, 'maybe_refresh_on_rest' ), 10, 3 );
	}

	/**
	 * Refreshes the queried event's geo meta before a single front-end
	 * view renders.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function maybe_refresh_on_view(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_type = get_post_type();

		if ( ! $post_type || ! post_type_supports( $post_type, Venue::ASSIGNMENT_SUPPORT ) ) {
			return;
		}

		$this->maybe_refresh( get_queried_object_id() );
	}

	/**
	 * Refreshes the event's geo meta and patches the in-flight response —
	 * `rest_prepare_` fires after `meta` is already serialized, so
	 * persisting alone wouldn't show up until the next request.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Post          $post     The post being returned.
	 * @param WP_REST_Request  $request  Request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response
	 */
	public function maybe_refresh_on_rest(
		WP_REST_Response $response,
		WP_Post $post,
		WP_REST_Request $request
	): WP_REST_Response {
		unset( $request );

		$fresh = $this->maybe_refresh( $post->ID );

		if ( null !== $fresh && isset( $response->data['meta'] ) && is_array( $response->data['meta'] ) ) {
			foreach ( $fresh as $key => $value ) {
				$response->data['meta'][ $key ] = $value;
			}
		}

		return $response;
	}

	/**
	 * Refreshes one event's geo_* meta from its venue if stale. Skipped
	 * for events that already happened.
	 *
	 * @since 0.36.0
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return array<string, string|int>|null The fresh values when a
	 *                                        refresh happened; null when
	 *                                        skipped or already current.
	 */
	public function maybe_refresh( int $event_id ): ?array {
		$event = new Event( $event_id );

		if ( $event->has_event_past() ) {
			return null;
		}

		$venue_post  = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event_id );
		$has_venue   = $venue_post instanceof WP_Post;
		$venue       = new Venue( $has_venue ? $venue_post->ID : 0 );
		$information = $venue->get_information();

		// A draft/private venue's real location must not leak through a
		// published event just because the event itself is public. An
		// event with no venue at all has nothing to hide.
		$venue_hides_location = $has_venue && 'publish' !== $venue_post->post_status;

		$desired = array(
			'geo_latitude'  => $venue_hides_location ? '' : $information['latitude'],
			'geo_longitude' => $venue_hides_location ? '' : $information['longitude'],
			'geo_address'   => $venue_hides_location ? '' : $information['address'],
			'geo_public'    => ( ! $venue_hides_location && 'publish' === get_post_status( $event_id ) ) ? 1 : 0,
		);

		$changed = false;

		foreach ( $desired as $key => $value ) {
			$current = get_post_meta( $event_id, $key, true );
			// get_post_meta() always returns a string — cast geo_public to int.
			$current = 'geo_public' === $key ? (int) $current : (string) $current;

			if ( $current !== $value ) {
				update_post_meta( $event_id, $key, $value );
				$changed = true;
			}
		}

		return $changed ? $desired : null;
	}
}
