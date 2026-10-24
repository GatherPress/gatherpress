<?php
/**
 * Geodata standard (`geo_*`) logic for venues and events. Venues derive
 * geo_* from their own fields on save; events refresh lazily from their
 * venue on view, since a venue can have ~1,000 linked events. The venue
 * and event `Meta` classes own the hookups.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Post;
use WP_REST_Response;
use WP_Term;

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
	 * The Geodata standard meta keys, shared by the venue and event bands.
	 *
	 * @since 0.36.0
	 *
	 * @var string[]
	 */
	const FIELDS = array( 'geo_latitude', 'geo_longitude', 'geo_address', 'geo_public' );

	/**
	 * Venue meta keeping the locations a venue cleared from its events when it
	 * stopped being public, keyed by event post ID, until it is public again.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CLEARED_COPIES_META_KEY = '_gatherpress_geo_cleared_copies';

	/**
	 * Registers the read-only Geodata standard meta for a post type.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type The post type to register against.
	 *
	 * @return void
	 */
	public static function register_meta( string $post_type ): void {
		$string_args = array(
			'auth_callback'     => '__return_false',
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
		);

		register_post_meta( $post_type, 'geo_latitude', $string_args );
		register_post_meta( $post_type, 'geo_longitude', $string_args );
		register_post_meta( $post_type, 'geo_address', $string_args );

		register_post_meta(
			$post_type,
			'geo_public',
			array(
				'auth_callback'     => '__return_false',
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 0,
			)
		);
	}

	/**
	 * Builds the `geo_*` values from venue information.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $information Venue information; an empty array clears the location.
	 * @param bool                 $is_public   Whether the location may be shown publicly.
	 *
	 * @return array<string, string|int> The values keyed by meta key.
	 */
	public static function build_values( array $information, bool $is_public ): array {
		return array(
			'geo_latitude'  => (string) ( $information['latitude'] ?? '' ),
			'geo_longitude' => (string) ( $information['longitude'] ?? '' ),
			'geo_address'   => (string) ( $information['address'] ?? '' ),
			'geo_public'    => $is_public ? 1 : 0,
		);
	}

	/**
	 * Writes `geo_*` values to a post, skipping keys that are already current.
	 *
	 * @since 0.36.0
	 *
	 * @param int                       $post_id The post ID.
	 * @param array<string, string|int> $values  Values from {@see self::build_values()}.
	 *
	 * @return bool Whether any value changed.
	 */
	public static function write( int $post_id, array $values ): bool {
		$changed = false;

		foreach ( $values as $key => $value ) {
			$current = get_post_meta( $post_id, $key, true );
			// get_post_meta() always returns a string, so cast geo_public to int.
			$current = 'geo_public' === $key ? (int) $current : (string) $current;

			// A missing key reads as ''/0, so check existence too or a 0/'' value is never stored.
			if ( $current !== $value || ! metadata_exists( 'post', $post_id, $key ) ) {
				update_post_meta( $post_id, $key, $value );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Whether a post's location may be shown publicly.
	 *
	 * Only a published post without a password qualifies. Draft, pending,
	 * scheduled, private and password-protected posts keep their location
	 * but mark it not public.
	 *
	 * @since 0.36.0
	 * @since TBD A password-protected post is not public.
	 *
	 * @param WP_Post|null $post The post, or null when there is none.
	 *
	 * @phpstan-assert-if-true =WP_Post $post
	 *
	 * @return bool Whether the post is published without a password.
	 */
	private static function is_public( ?WP_Post $post ): bool {
		return $post instanceof WP_Post && 'publish' === $post->post_status && '' === $post->post_password;
	}

	/**
	 * Derives geo_* meta from a saved venue's own fields.
	 *
	 * A venue that isn't public also clears the location its events copied.
	 *
	 * @since 0.36.0
	 * @since TBD Clears the copies on its events when the venue isn't public.
	 *
	 * @param int     $post_id Post ID that just saved.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function on_venue_saved( int $post_id, WP_Post $post ): void {
		if (
			wp_is_post_revision( $post_id )
			|| wp_is_post_autosave( $post_id )
			|| ! post_type_supports( $post->post_type, Venue::SUPPORT )
		) {
			return;
		}

		$information = ( new Venue( $post_id ) )->get_information();
		$is_public   = self::is_public( $post );

		self::write( $post_id, self::build_values( $information, $is_public ) );

		// A venue that isn't public takes back the location its events copied,
		// past ones included, since maybe_refresh() never reaches those, and
		// gives it back once it is public again.
		if ( $is_public ) {
			$this->restore_event_copies( $post );
		} else {
			$this->clear_event_copies( $post );
		}
	}

	/**
	 * Clears the location every event copied from a venue.
	 *
	 * Writes what maybe_refresh() would for an event at a venue that isn't
	 * public: no location, and not public. The locations it clears are kept
	 * on the venue (`CLEARED_COPIES_META_KEY`) for `restore_event_copies()`,
	 * since a past event's location records where it happened. Only the first
	 * copy is kept: saving the venue again while it isn't public finds the
	 * events already blank.
	 *
	 * @since TBD
	 *
	 * @param WP_Post $venue The venue post.
	 *
	 * @return void
	 */
	private function clear_event_copies( WP_Post $venue ): void {
		$shadow_source = Shadow_Source::get_instance();
		$taxonomy      = $shadow_source->get_taxonomy( $venue->post_type );
		$term          = get_term_by(
			'slug',
			$shadow_source->term_slug_from_post_name( $venue->post_name ),
			$taxonomy
		);

		// A venue that was never published has no term, so no event points at it.
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$event_ids = get_objects_in_term( $term->term_id, $taxonomy );
		$event_ids = array_map( 'intval', is_array( $event_ids ) ? $event_ids : array() );

		// One query for every event's meta, rather than one per event in write().
		update_meta_cache( 'post', $event_ids );

		$cleared = get_post_meta( $venue->ID, self::CLEARED_COPIES_META_KEY, true );
		$cleared = is_array( $cleared ) ? $cleared : array();

		foreach ( $event_ids as $event_id ) {
			$location = array(
				'latitude'  => (string) get_post_meta( $event_id, 'geo_latitude', true ),
				'longitude' => (string) get_post_meta( $event_id, 'geo_longitude', true ),
				'address'   => (string) get_post_meta( $event_id, 'geo_address', true ),
			);

			if ( ! isset( $cleared[ $event_id ] ) && '' !== implode( '', $location ) ) {
				$cleared[ $event_id ] = $location;
			}

			self::write( $event_id, self::build_values( array(), false ) );
		}

		if ( array() !== $cleared ) {
			update_post_meta( $venue->ID, self::CLEARED_COPIES_META_KEY, $cleared );
		}
	}

	/**
	 * Gives events back the location a venue cleared from them.
	 *
	 * Each event at the venue gets the exact location it had, not the
	 * venue's current one, so a past event keeps the place it happened even
	 * if the venue's address changed in the meantime. Whether it is public
	 * follows the event's own status. An event that has since been deleted or
	 * moved to another venue is left as it is.
	 *
	 * @since TBD
	 *
	 * @param WP_Post $venue The venue post, now public.
	 *
	 * @return void
	 */
	private function restore_event_copies( WP_Post $venue ): void {
		$cleared = get_post_meta( $venue->ID, self::CLEARED_COPIES_META_KEY, true );

		if ( ! is_array( $cleared ) ) {
			return;
		}

		$shadow_source = Shadow_Source::get_instance();
		$taxonomy      = $shadow_source->get_taxonomy( $venue->post_type );
		$term_slug     = $shadow_source->term_slug_from_post_name( $venue->post_name );

		foreach ( $cleared as $event_id => $location ) {
			$event = get_post( (int) $event_id );

			if ( $event instanceof WP_Post && has_term( $term_slug, $taxonomy, $event ) ) {
				self::write( $event->ID, self::build_values( (array) $location, self::is_public( $event ) ) );
			}
		}

		delete_post_meta( $venue->ID, self::CLEARED_COPIES_META_KEY );
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
	 *
	 * @return WP_REST_Response
	 */
	public function maybe_refresh_on_rest( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
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
		if ( ( new Event( $event_id ) )->has_event_past() ) {
			return null;
		}

		$venue_post = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event_id );

		// Only a published venue without a password shares its location, so a
		// draft, private or protected venue's address never leaks through an event.
		$information = self::is_public( $venue_post )
			? ( new Venue( $venue_post->ID ) )->get_information()
			: array();

		// An event with no venue has no location to hide, so only its own
		// status counts.
		$is_public = self::is_public( get_post( $event_id ) )
			&& ( null === $venue_post || self::is_public( $venue_post ) );

		$desired = self::build_values( $information, $is_public );

		return self::write( $event_id, $desired ) ? $desired : null;
	}
}
