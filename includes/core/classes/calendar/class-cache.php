<?php
/**
 * Caching for the iCalendar responses.
 *
 * Calendar clients subscribe to ICS feeds and poll them on their own schedule:
 * Outlook every 15 to 60 minutes, Apple Calendar as often as every 5 minutes,
 * Google Calendar daily. Every one of those hits rebuilt the whole payload,
 * event by event, to return bytes that had usually not changed. This file
 * defines the `Cache` class, which holds the rendered bodies and the timestamp
 * the responses are validated against.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.35.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Caching for the iCalendar responses.
 *
 * Invalidation is by version stamp rather than by deleting keys. Every cache
 * key carries the timestamp of the last calendar-relevant change, so a change
 * is one option write and every stale key is unreachable from that moment,
 * with no need to know which feeds a given event appeared in. The stamp is
 * also what `Last-Modified` reports and what conditional requests validate
 * against, so the HTTP layer and the object cache cannot disagree about how
 * fresh a response is.
 *
 * @since 0.35.0
 */
final class Cache {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Object cache group for rendered calendar payloads.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'gatherpress_calendar';

	/**
	 * Option holding the GMT timestamp of the last calendar-relevant change.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	const LAST_MODIFIED_OPTION = 'gatherpress_calendar_last_modified';

	/**
	 * Default seconds a client may reuse a calendar response without asking.
	 *
	 * Fifteen minutes matches the shortest polling interval the common clients
	 * use by default, so a subscriber's next poll is usually a conditional
	 * request that costs a 304 rather than a full render.
	 *
	 * @since 0.35.0
	 *
	 * @var int
	 */
	const DEFAULT_MAX_AGE = 15 * MINUTE_IN_SECONDS;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.35.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Everything a VEVENT is built from: the post itself, its datetimes and
		// venue meta, its terms, and its deletion. Each one stamps the calendar
		// rather than reasoning about which feeds it belongs to.
		add_action( 'save_post', array( $this, 'touch_for_post' ) );
		add_action( 'deleted_post', array( $this, 'touch_for_post' ) );
		add_action( 'updated_post_meta', array( $this, 'touch_for_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'touch_for_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'touch_for_meta' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'touch_for_terms' ), 10, 4 );
	}

	/**
	 * Seconds a client may reuse a calendar response without revalidating.
	 *
	 * @since 0.35.0
	 *
	 * @return int Max age in seconds. Zero disables client caching.
	 */
	public function get_max_age(): int {
		/**
		 * Filters how long calendar responses may be reused by clients and caches.
		 *
		 * Applies to the `Cache-Control` header on ICS responses and to how long
		 * a rendered body is kept in the object cache, so the two cannot drift.
		 * Return 0 to send `no-cache` and rebuild on every request.
		 *
		 * @since 0.35.0
		 *
		 * @param int $max_age Seconds a calendar response stays fresh.
		 *
		 * @return int Seconds a calendar response stays fresh.
		 */
		return max( 0, (int) apply_filters( 'gatherpress_calendar_max_age', self::DEFAULT_MAX_AGE ) );
	}

	/**
	 * GMT timestamp of the last calendar-relevant change.
	 *
	 * Seeds itself on first read so a site that has never edited an event still
	 * has a stable validator to hand out, rather than one that moves on every
	 * request and defeats the point.
	 *
	 * @since 0.35.0
	 *
	 * @return string Timestamp in `Y-m-d H:i:s` GMT.
	 */
	public function get_last_modified(): string {
		$last_modified = (string) get_option( self::LAST_MODIFIED_OPTION, '' );

		if ( '' === $last_modified ) {
			$last_modified = current_time( 'mysql', true );

			update_option( self::LAST_MODIFIED_OPTION, $last_modified, false );
		}

		return $last_modified;
	}

	/**
	 * Stamp the calendar as changed, invalidating every cached response.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function touch(): void {
		update_option( self::LAST_MODIFIED_OPTION, current_time( 'mysql', true ), false );
	}

	/**
	 * Return a cached calendar payload, rendering it on a miss.
	 *
	 * @since 0.35.0
	 *
	 * @param string   $key      Cache key, unique to the request's scope.
	 * @param callable $renderer Builds the payload when the cache misses.
	 *
	 * @return string The rendered payload.
	 */
	public function remember( string $key, callable $renderer ): string {
		$max_age = $this->get_max_age();

		if ( 0 === $max_age ) {
			return (string) $renderer();
		}

		$versioned_key = $this->get_versioned_key( $key );
		$cached        = wp_cache_get( $versioned_key, self::CACHE_GROUP );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$payload = (string) $renderer();

		wp_cache_set( $versioned_key, $payload, self::CACHE_GROUP, $max_age );

		return $payload;
	}

	/**
	 * Namespace a cache key with the current calendar version stamp.
	 *
	 * @since 0.35.0
	 *
	 * @param string $key Scope-specific key.
	 *
	 * @return string Versioned cache key.
	 */
	public function get_versioned_key( string $key ): string {
		return sprintf( '%s:%s', md5( $this->get_last_modified() ), $key );
	}

	/**
	 * Stamp the calendar when an event or venue post changes.
	 *
	 * @since 0.35.0
	 *
	 * @param int|string $post_id The post that changed.
	 *
	 * @return void
	 */
	public function touch_for_post( $post_id ): void {
		if ( $this->is_calendar_post_type( (string) get_post_type( (int) $post_id ) ) ) {
			$this->touch();
		}
	}

	/**
	 * Stamp the calendar when GatherPress meta on a calendar post changes.
	 *
	 * Datetimes and venue details are read while building a VEVENT but are
	 * written without touching the post row, so `save_post` alone would miss
	 * them.
	 *
	 * @since 0.35.0
	 *
	 * @param int|string $meta_id  Meta row ID (unused; part of the hook signature).
	 * @param int|string $post_id  The post the meta belongs to.
	 * @param string     $meta_key The meta key that changed.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's *_post_meta signature.
	 */
	public function touch_for_meta( $meta_id, $post_id, $meta_key = '' ): void {
		if (
			str_starts_with( (string) $meta_key, 'gatherpress_' )
			&& $this->is_calendar_post_type( (string) get_post_type( (int) $post_id ) )
		) {
			$this->touch();
		}
	}

	/**
	 * Stamp the calendar when a calendar post's terms change.
	 *
	 * Venue association and topics decide which feeds an event appears in, and
	 * term writes do not touch the post row either.
	 *
	 * @since 0.35.0
	 *
	 * @param int|string $object_id  The object whose terms changed.
	 * @param array      $terms      Terms set (unused; part of the hook signature).
	 * @param array      $tt_ids     Term taxonomy IDs (unused; part of the hook signature).
	 * @param string     $taxonomy   The taxonomy that changed.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's set_object_terms signature.
	 */
	public function touch_for_terms( $object_id, $terms = array(), $tt_ids = array(), $taxonomy = '' ): void {
		// Comment taxonomies share this hook: RSVP status changes do not alter
		// a VEVENT, so they must not invalidate every feed on the site.
		$taxonomy_object = get_taxonomy( (string) $taxonomy );

		if ( ! $taxonomy_object || in_array( 'comment', (array) $taxonomy_object->object_type, true ) ) {
			return;
		}

		$this->touch_for_post( $object_id );
	}

	/**
	 * Whether a post type contributes to calendar output.
	 *
	 * @since 0.35.0
	 *
	 * @param string $post_type The post type to test.
	 *
	 * @return bool True for event-bearing and venue post types.
	 */
	private function is_calendar_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, 'gatherpress-event-date' )
			|| post_type_supports( $post_type, 'gatherpress-venue-information' );
	}
}
