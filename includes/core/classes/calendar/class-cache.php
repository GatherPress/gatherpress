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
 * Bodies are stored as transients rather than in the object cache directly, so
 * the win does not depend on the site having a persistent backend: with one,
 * transients are object cache entries anyway; without one, they persist in the
 * options table instead of evaporating at the end of the request.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;

/**
 * Caching for the iCalendar responses.
 *
 * Entries are namespaced by version stamp rather than deleted. Every cache key
 * carries the timestamp of the last calendar-relevant change, so a change is
 * one option write and every previous key becomes unreachable from that moment,
 * with no need to know which feeds a given event appeared in. The old entries
 * are not removed: they are stranded until their expiry passes, at which point
 * WordPress's own expired-transient cleanup collects them. That trade buys
 * O(1) invalidation on backends that handle delete-by-pattern badly.
 *
 * The stamp is also what `Last-Modified` reports and what conditional requests
 * validate against, so the HTTP layer and the stored bodies cannot disagree
 * about how fresh a response is.
 *
 * @since 0.36.0
 */
final class Cache {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Transient name prefix for rendered calendar payloads.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'gatherpress_calendar_';

	/**
	 * Option holding the GMT timestamp of the last calendar-relevant change.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const LAST_MODIFIED_OPTION = 'gatherpress_calendar_last_modified';

	/**
	 * Option holding a strictly increasing count of calendar-relevant changes.
	 *
	 * `Last-Modified` has one-second resolution, and it is the whole cache
	 * namespace: two changes inside one second produce the same stamp, so the
	 * second one resolves to a key the first already filled and is served the
	 * body it just invalidated. Canceling two occurrences of a series is one
	 * loop, well inside a second, so this is the ordinary case rather than a
	 * race. The counter separates them without pretending the HTTP validator has
	 * finer resolution than it does.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	const CHANGE_COUNT_OPTION = 'gatherpress_calendar_change_count';

	/**
	 * Default seconds a client may reuse a calendar response without asking.
	 *
	 * Fifteen minutes matches the shortest polling interval the common clients
	 * use by default, so a subscriber's next poll is usually a conditional
	 * request that costs a 304 rather than a full render.
	 *
	 * @since 0.36.0
	 *
	 * @var int
	 */
	const DEFAULT_MAX_AGE = 15 * MINUTE_IN_SECONDS;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Everything a VEVENT is built from: the post itself, its datetimes and
		// venue meta, its terms, and its deletion. Each one stamps the calendar
		// rather than reasoning about which feeds it belongs to.
		add_action( 'save_post', array( $this, 'mark_changed_for_post' ) );
		add_action( 'deleted_post', array( $this, 'mark_changed_for_post' ) );
		add_action( 'updated_post_meta', array( $this, 'mark_changed_for_meta' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'mark_changed_for_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'mark_changed_for_meta' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'mark_changed_for_terms' ), 10, 4 );
		// Occurrence rows are written by bare SQL that touches none of the
		// above, yet an `.ics` body is built from them: the aggregate feeds
		// select their bucket from the rows, and a canceled row becomes an
		// `EXDATE`.
		add_action( 'gatherpress_occurrences_changed', array( $this, 'mark_changed_for_occurrences' ) );
	}

	/**
	 * Seconds a client may reuse a calendar response without revalidating.
	 *
	 * @since 0.36.0
	 *
	 * @return int Max age in seconds. Zero disables client caching.
	 */
	public function get_max_age(): int {
		/**
		 * Filters how long calendar responses may be reused by clients and caches.
		 *
		 * Applies to the `Cache-Control` header on ICS responses and to how long
		 * a rendered body is kept server-side, so the two cannot drift.
		 * Return 0 to send `no-cache` and rebuild on every request.
		 *
		 * @since 0.36.0
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
	 * @since 0.36.0
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
	 * Stamp the calendar as changed.
	 *
	 * Cached responses are namespaced by this stamp, so a new value moves every
	 * lookup to a fresh key and strands the old entries until they expire.
	 *
	 * Both writes allocate in SQL rather than in PHP, for two properties a
	 * read-then-write cannot give. The validator is written as the greater of
	 * the clock and one second past the stored value, so the second change
	 * inside one second still moves it and a client revalidating with the
	 * first change's `Last-Modified` is never told 304 for a body missing the
	 * second. The counter increments its row in place, so two concurrent
	 * writers serialize on the row lock instead of both writing the same
	 * value they read before either wrote, which would leave a feed request
	 * between them a namespace it can fill with a stale body.
	 *
	 * A burst of stamps inside one second leans the validator a few seconds
	 * ahead of the clock, one per stamp. That is the trade for strict
	 * monotonicity at HTTP-date resolution, and it self-corrects on the first
	 * change after the burst, when the clock has caught up.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function mark_changed(): void {
		$this->advance_last_modified( current_time( 'mysql', true ) );
		$this->allocate_change_count();
	}

	/**
	 * Advance the stored validator past both the clock and its own last value.
	 *
	 * One statement, so the read and the write cannot interleave with another
	 * writer's. `GREATEST()` compares the two candidates as strings, which
	 * orders correctly for the zero-padded `Y-m-d H:i:s` form, and the
	 * `COALESCE()` catches a corrupt stored value: `DATE_ADD()` answers NULL
	 * for one, NULL poisons `GREATEST()`, and the clock takes over.
	 *
	 * @since 0.36.0
	 *
	 * @param string $now The current GMT time in `Y-m-d H:i:s` form.
	 *
	 * @return void
	 */
	private function advance_last_modified( string $now ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i ( option_name, option_value, autoload )
				VALUES ( %s, %s, 'off' )
				ON DUPLICATE KEY UPDATE option_value = COALESCE(
					GREATEST(
						%s,
						DATE_FORMAT(
							DATE_ADD( option_value, INTERVAL 1 SECOND ),
							'%%Y-%%m-%%d %%H:%%i:%%s'
						)
					),
					%s
				)",
				$wpdb->options,
				self::LAST_MODIFIED_OPTION,
				$now,
				$now,
				$now
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->flush_option_caches();
	}

	/**
	 * Allocate the next change count by incrementing the option row in place.
	 *
	 * One statement, so a concurrent allocation waits on the row lock and
	 * builds on this one's value rather than on the same starting point. A
	 * value that never took a detour through PHP cannot lose an increment to
	 * an interleaved writer.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	private function allocate_change_count(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i ( option_name, option_value, autoload )
				VALUES ( %s, '1', 'off' )
				ON DUPLICATE KEY UPDATE option_value = CAST( option_value AS SIGNED ) + 1",
				$wpdb->options,
				self::CHANGE_COUNT_OPTION
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->flush_option_caches();
	}

	/**
	 * Drop every cached copy of the two stamp options.
	 *
	 * The stamp is written by bare SQL, so the per-option cache and the
	 * missing-option memo still hold whatever they held before the write, and
	 * a read through either would resurrect the value the write just
	 * superseded. The memo is the dangerous half on a persistent object
	 * cache: `get_option()` consults it before touching the database, and a
	 * feed request always reads the counter before the first change allocates
	 * it, so without the delete the counter reads as zero forever while the
	 * row keeps incrementing. Both options are written `autoload => 'off'`,
	 * so the alloptions blob never carries them.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	private function flush_option_caches(): void {
		wp_cache_delete( self::LAST_MODIFIED_OPTION, 'options' );
		wp_cache_delete( self::CHANGE_COUNT_OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * How many calendar-relevant changes this site has recorded.
	 *
	 * Part of the cache namespace rather than of any response: it exists to
	 * separate two changes `Last-Modified` cannot, and a client never sees it.
	 *
	 * @since 0.36.0
	 *
	 * @return int The current count, starting at zero.
	 */
	public function get_change_count(): int {
		return max( 0, (int) get_option( self::CHANGE_COUNT_OPTION, 0 ) );
	}

	/**
	 * Return a cached calendar payload, rendering it on a miss.
	 *
	 * @since 0.36.0
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
		$cached        = get_transient( $versioned_key );

		if ( is_string( $cached ) ) {
			return $cached;
		}

		$payload = (string) $renderer();

		set_transient( $versioned_key, $payload, $max_age );

		return $payload;
	}

	/**
	 * Namespace a cache key with the current calendar version stamp.
	 *
	 * The scope key and the stamp are hashed together rather than concatenated
	 * so the result stays inside the 172-character ceiling on option names, no
	 * matter how long the caller's key grows. The change counter joins them
	 * because the stamp alone cannot separate two changes in one second, and a
	 * key that does not move is a cached body that does not either.
	 *
	 * @since 0.36.0
	 *
	 * @param string $key Scope-specific key.
	 *
	 * @return string Versioned transient name.
	 */
	public function get_versioned_key( string $key ): string {
		return self::TRANSIENT_PREFIX . md5( // NOSONAR.
			$this->get_last_modified() . ':' . $this->get_change_count() . ':' . $key
		);
	}

	/**
	 * Stamp the calendar when an event or venue post changes.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $post_id The post that changed.
	 *
	 * @return void
	 */
	public function mark_changed_for_post( $post_id ): void {
		if ( $this->is_calendar_post_type( (string) get_post_type( (int) $post_id ) ) ) {
			$this->mark_changed();
		}
	}

	/**
	 * Stamp the calendar when a series' occurrence rows change.
	 *
	 * The stamp is what moves both halves of the response cache at once: the
	 * stored bodies are namespaced by it, and it is what `Last-Modified` reports
	 * to a revalidating subscriber. Without it a canceled date is held behind a
	 * `304` until some unrelated write on the site happens to stamp the
	 * calendar, which may be never.
	 *
	 * The series' calendar revision advances with it. The stamp invalidates the
	 * *server's* copy, but a subscriber already holding the old component decides
	 * whether to replace it by comparing `SEQUENCE`, and an occurrence write
	 * leaves `post_modified_gmt` exactly where it was, and that number is
	 * otherwise derived from it. Without the advance a canceled date is correctly
	 * absent from the body and still on the subscriber's calendar.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $post_id The series post whose occurrence rows changed.
	 *
	 * @return void
	 */
	public function mark_changed_for_occurrences( $post_id ): void {
		if ( ! $this->is_calendar_post_type( (string) get_post_type( (int) $post_id ) ) ) {
			return;
		}

		Revision::get_instance()->advance( (int) $post_id );
		$this->mark_changed();
	}

	/**
	 * Stamp the calendar when GatherPress meta on a calendar post changes.
	 *
	 * Datetimes and venue details are read while building a VEVENT but are
	 * written without touching the post row, so `save_post` alone would miss
	 * them.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $meta_id  Meta row ID (unused; part of the hook signature).
	 * @param int|string $post_id  The post the meta belongs to.
	 * @param string     $meta_key The meta key that changed.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's *_post_meta signature.
	 */
	public function mark_changed_for_meta( $meta_id, $post_id, $meta_key = '' ): void {
		// The revision advance is itself part of a stamp already in progress:
		// `mark_changed_for_occurrences()` publishes it to every sibling and
		// then stamps once. Without this bail each sibling's meta write
		// re-enters `mark_changed()`, and the change count moves two or three
		// times per change instead of once.
		if ( Revision::META_KEY === (string) $meta_key ) {
			return;
		}

		if (
			str_starts_with( (string) $meta_key, 'gatherpress_' )
			&& $this->is_calendar_post_type( (string) get_post_type( (int) $post_id ) )
		) {
			$this->mark_changed();
		}
	}

	/**
	 * Stamp the calendar when a calendar post's terms change.
	 *
	 * Venue association and topics decide which feeds an event appears in, and
	 * term writes do not touch the post row either.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string        $object_id  The object whose terms changed.
	 * @param array<int|string> $terms      Terms set (unused; part of the hook signature).
	 * @param int[]             $tt_ids     Term taxonomy IDs (unused; part of the hook signature).
	 * @param string            $taxonomy   The taxonomy that changed.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's set_object_terms signature.
	 */
	public function mark_changed_for_terms( $object_id, $terms = array(), $tt_ids = array(), $taxonomy = '' ): void {
		// Comment taxonomies share this hook: RSVP status changes do not alter
		// a VEVENT, so they must not invalidate every feed on the site.
		$taxonomy_object = get_taxonomy( (string) $taxonomy );

		if ( ! $taxonomy_object || in_array( 'comment', (array) $taxonomy_object->object_type, true ) ) {
			return;
		}

		$this->mark_changed_for_post( $object_id );
	}

	/**
	 * Whether a post type contributes to calendar output.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type The post type to test.
	 *
	 * @return bool True for event-bearing and venue post types.
	 */
	private function is_calendar_post_type( string $post_type ): bool {
		return post_type_supports( $post_type, Event::SUPPORT )
			|| post_type_supports( $post_type, Venue::SUPPORT );
	}
}
