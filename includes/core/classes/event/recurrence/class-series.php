<?php
/**
 * Series resolver.
 *
 * The series-resolution seam, and the single most important extension point in the
 * subsystem. Every occurrence read passes through here to turn one post ID into
 * the set of post IDs that make up its series, so every occurrence query emits
 * `series_post_id IN (…)`.
 *
 * The durable relationship between the event posts a split produces is the
 * `_gatherpress_series` taxonomy on the **event posts**: a shared private
 * taxonomy term resolves every fragment of a split series in one lookup. An
 * occurrence stays a table row rather than a post, so the term relates the
 * few posts splitting created, never one record per date. A term on each
 * occurrence was rejected because it presupposes occurrences that are posts;
 * a dedicated Series post type, which is how The Events Calendar Pro models
 * recurrence, was rejected because that plugin needs it only to support
 * several rules per event, which this subsystem does not allow.
 *
 * Two properties of that choice are load-bearing and are asserted by tests
 * rather than left to the reader:
 *
 * - **No parent pointer.** Every post of a series carries the *same* term, so a
 *   series split twice resolves all three posts in one read. A
 *   `_gatherpress_series_parent` meta pointer would answer the same question by
 *   walking, and the series must not be a chain requiring traversal.
 * - **Nothing is written until the first split.** A recurring event that has
 *   never been split has no term, no term relationship and no meta. The save
 *   path stays free of series writes, and creating a term for every recurring
 *   event on the chance it might later split would be a per-event write on it.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Series.
 *
 * Singleton resolver mapping a post to the posts of its series.
 *
 * @since 0.36.0
 */
final class Series {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Taxonomy grouping the event posts a forward split produced.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_series';

	/**
	 * Option recording whether any series term exists on the site.
	 *
	 * The companion to `Query::HAS_RECURRING_OPTION`, and independent of it
	 * by necessity: a two-occurrence series split at its second date demotes
	 * both sides to plain events, which turns the recurring flag off while
	 * the series relationship persists in the taxonomy. Gating the taxonomy
	 * on the recurring flag alone therefore made the durable series vanish on
	 * the very next request. Autoloaded while `'1'`, kept but not autoloaded
	 * while `'0'`, and recomputed authoritatively from term storage on the
	 * term lifecycle, never incremented.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const HAS_SPLIT_SERIES_OPTION = 'gatherpress_has_split_series';

	/**
	 * Resolved series membership, memoized per post for the life of the request.
	 *
	 * A single front-end response resolves the same post many times, once per
	 * occurrence rendered plus once for every RSVP read on it, and the answer
	 * cannot change mid-request, because the only thing that changes it is a
	 * split, which is a write.
	 *
	 * Keyed `{blog_id}:{post_id}`; see `Context::$resolved`. The blog ID leads
	 * the key because post IDs are blog-local and this class is a singleton
	 * that survives `switch_to_blog()`: a request that resolves on one blog
	 * and then switches would otherwise be handed the first blog's membership
	 * set on the second blog, and the leaked post IDs would feed occurrence
	 * queries, calendar exports, redirects and revision writes there as
	 * foreign owners.
	 *
	 * @since 0.36.0
	 * @var array<string, int[]>
	 */
	protected array $memo = array();

	/**
	 * Series terms of posts mid-hard-delete, keyed by post ID.
	 *
	 * The term has to be read on `before_delete_post`, while the relationship
	 * row still exists, and acted on at `after_delete_post`, once WordPress
	 * has removed it: `wp_delete_post()` deletes the relationships but never
	 * the term, so the last fragment of a deleted split series would otherwise
	 * strand one unreachable term row pair per series, forever.
	 *
	 * @since 0.36.0
	 * @var array<int, int>
	 */
	protected array $terms_pending_deletion = array();

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for the series taxonomy.
	 *
	 * Priority 11 on `registered_post_type`, matching `Recurrence\Meta`. That is
	 * after the default priority 10 a post type normally registers at, so a
	 * companion plugin's own `add_post_type_support()` call has already landed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'register' ), 11 );
		add_action( 'before_delete_post', array( $this, 'remember_series_term' ), 10, 2 );
		add_action( 'after_delete_post', array( $this, 'maybe_delete_orphan_term' ) );
		// The split-series flag recomputes on the term lifecycle itself rather
		// than inside the callers that create or delete terms, so every path
		// that writes one keeps it true: the first join, the last fragment's
		// orphan-term deletion, and a rolled-back split's own term removal.
		// Neither hook can fire on a site with no series terms, so a site with
		// neither flag pays nothing for them.
		add_action( 'created_' . self::TAXONOMY, array( $this, 'refresh_has_split_series' ) );
		add_action( 'delete_' . self::TAXONOMY, array( $this, 'refresh_has_split_series' ) );
	}

	/**
	 * Note the series term of a supported post about to be hard-deleted.
	 *
	 * On a site with no recurring events the taxonomy is never registered, so
	 * the term read answers a `WP_Error` without touching the database and
	 * nothing is remembered: an ordinary event's deletion stays free of series
	 * work.
	 *
	 * @since 0.36.0
	 *
	 * @param int     $post_id Post ID being deleted.
	 * @param WP_Post $post    The post itself.
	 *
	 * @return void
	 */
	public function remember_series_term( int $post_id, WP_Post $post ): void {
		if ( ! post_type_supports( $post->post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		$term_id = $this->term_id_for_post( $post_id );

		if ( 0 !== $term_id ) {
			$this->terms_pending_deletion[ $post_id ] = $term_id;
		}
	}

	/**
	 * Delete a series term once its last member post is gone.
	 *
	 * Idempotent by construction: the pending entry is consumed before the
	 * membership read, and a term that still has members, or was already
	 * deleted by the sibling's own deletion, is left alone. A term with
	 * members is the surviving-fragment case, and deleting it would break the
	 * survivors' resolution.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID that was deleted.
	 *
	 * @return void
	 */
	public function maybe_delete_orphan_term( int $post_id ): void {
		if ( ! isset( $this->terms_pending_deletion[ $post_id ] ) ) {
			return;
		}

		$term_id = $this->terms_pending_deletion[ $post_id ];

		unset( $this->terms_pending_deletion[ $post_id ] );

		// A `WP_Error` (the taxonomy vanished mid-request) and a non-empty
		// member list both mean the term is not this callback's to delete.
		$members = get_objects_in_term( array( $term_id ), self::TAXONOMY );

		if ( is_array( $members ) && array() === $members ) {
			wp_delete_term( $term_id, self::TAXONOMY );
		}

		// The deletion changed membership whether or not the term survived
		// it: a fragment is gone either way, and a memo flushed only on the
		// orphan branch kept naming deleted posts for the rest of a bulk
		// delete's request while `Revision::advance()` wrote meta to them.
		$this->flush_memo();
	}

	/**
	 * Register the series taxonomy on a post type, when the site can have a series.
	 *
	 * The flag check lives on the second guard, and it is not a
	 * micro-optimization: `WP_Query` primes term caches through
	 * `update_object_term_cache()`, which reads `get_object_taxonomies()` and
	 * issues **one** query naming every taxonomy registered for the post type.
	 * Registering this taxonomy unconditionally would therefore change the SQL
	 * text of a query that runs on every event listing, on a site that has never
	 * authored a recurring event, producing a byte-for-byte difference where
	 * such a site is promised none.
	 *
	 * Either flag admits the registration. The recurring flag covers every
	 * site that could split; the split flag covers the site whose every
	 * fragment has since demoted to a plain event, where the durable series
	 * relationship must stay readable after the recurring flag returns to
	 * `'0'`.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Post type that was just registered.
	 *
	 * @return void
	 */
	public function register( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' )
			|| ! ( Query::site_has_recurring_events() || self::site_has_split_series() )
		) {
			return;
		}

		self::register_taxonomy_for( $post_type );
	}

	/**
	 * Report whether any split series exists on the site.
	 *
	 * Read off the autoloaded-options cache directly rather than through
	 * `get_option()`, and the difference is the whole point of the flag. On a
	 * site that has never split, the option does not exist, and `get_option()`
	 * answers a missing option with one `wp_options` SELECT per request before
	 * the not-found cache absorbs it. This read happens inside `register()` on
	 * every request, so that SELECT would be a byte-for-byte SQL difference on
	 * exactly the sites promised none. The alloptions cache is already loaded
	 * on every request, the option autoloads whenever its value is `'1'`, and
	 * an option that is absent from that cache, whether missing or stored as
	 * a non-autoloaded `'0'`, authoritatively means the site has no split
	 * series, because every series term write recomputes it.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when at least one series term exists.
	 */
	public static function site_has_split_series(): bool {
		$options = wp_load_alloptions();

		return '1' === ( $options[ self::HAS_SPLIT_SERIES_OPTION ] ?? '0' );
	}

	/**
	 * Recompute the has-split-series option from term storage.
	 *
	 * Authoritative rather than incremental, exactly as
	 * `Query::refresh_has_recurring_events()` is for its flag: the option is
	 * derived from what is stored, so a lost or duplicated lifecycle event
	 * cannot desynchronize it. The read goes straight to the term-taxonomy
	 * table because the recompute runs from term-lifecycle hooks that also
	 * fire while the taxonomy is mid-teardown, where `get_terms()` would
	 * answer a `WP_Error` instead of the storage truth.
	 *
	 * The single writer of the option, and the autoload state is part of the
	 * write: `'1'` autoloads because `site_has_split_series()` reads the
	 * alloptions cache on every request, while `'0'` is written without
	 * autoload, because a site that split once and then deleted every
	 * fragment would otherwise carry one alloptions row per request forever
	 * for an answer an absent row already gives. The row itself is kept
	 * rather than deleted, because a deleted option hands the next
	 * `get_option()` read one `wp_options` SELECT for the miss, on exactly
	 * the byte-identical-SQL surface the flag exists to protect.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public static function refresh_has_split_series(): void {
		global $wpdb;

		// A lifecycle-triggered recompute, not a read path query; caching it
		// would only cache the flag it is itself in the process of producing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE taxonomy = %s LIMIT 1',
				$wpdb->term_taxonomy,
				self::TAXONOMY
			)
		);

		update_option( self::HAS_SPLIT_SERIES_OPTION, $has ? '1' : '0', $has );
	}

	/**
	 * Register the series taxonomy, or attach it to one more post type.
	 *
	 * Called both from the `registered_post_type` hook and directly from
	 * `Splitter`, which has to be able to write a term on the very first split
	 * performed on a site whose flag was still `'0'` when post types registered.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Post type to attach the taxonomy to.
	 *
	 * @return void
	 */
	public static function register_taxonomy_for( string $post_type ): void {
		if ( taxonomy_exists( self::TAXONOMY ) ) {
			register_taxonomy_for_object_type( self::TAXONOMY, $post_type );

			return;
		}

		register_taxonomy(
			self::TAXONOMY,
			$post_type,
			array(
				'hierarchical'       => false,
				'labels'             => array( 'name' => __( 'Series', 'gatherpress' ) ),
				'public'             => false,
				'publicly_queryable' => false,
				'query_var'          => false,
				'rewrite'            => false,
				'show_admin_column'  => false,
				'show_in_menu'       => false,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => false,
				'show_tagcloud'      => false,
				'show_ui'            => false,
			)
		);
	}

	/**
	 * Resolve a post to every post ID in its series.
	 *
	 * The result passes through the `gatherpress_series_post_ids` filter, which
	 * remains the seam by which an integration can widen a series beyond what
	 * the taxonomy records: this class is `final` with a `protected` constructor,
	 * so no test can mock or subclass it.
	 *
	 * The filtered result is normalized rather than returned verbatim: IDs are
	 * cast to integers, the resolving post is re-added, duplicates are removed
	 * and the set is sorted ascending. An integration returning only a
	 * companion ID would otherwise strip the guaranteed self-membership, and
	 * every occurrence query consuming the set would silently skip the
	 * resolving post's own rows.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to resolve.
	 *
	 * @return int[] Every post ID in the series, ascending, always including `$post_id`.
	 */
	public function resolve_post_ids( int $post_id ): array {
		$post_ids = array( $post_id );

		// With neither recurring events nor a split series on the site the
		// taxonomy is never registered, so no term relationship can exist and
		// none is read. The split flag keeps a fully demoted split resolving
		// after the recurring flag has returned to '0'.
		if ( ( Query::site_has_recurring_events() || self::site_has_split_series() )
			&& taxonomy_exists( self::TAXONOMY )
		) {
			$post_ids = $this->resolve_from_taxonomy( $post_id );
		}

		/**
		 * Filters the post IDs that make up an event's series.
		 *
		 * The result is normalized after filtering: values are cast to integer,
		 * the resolved post ID is always re-added, duplicates are dropped and
		 * the set is sorted ascending.
		 *
		 * @since 0.36.0
		 *
		 * @param int[] $post_ids Post IDs in the series, default the posts sharing this post's series term.
		 * @param int   $post_id  The post ID being resolved.
		 *
		 * @return int[] Post IDs in the series.
		 */
		$post_ids = (array) apply_filters( 'gatherpress_series_post_ids', $post_ids, $post_id );

		$post_ids[] = $post_id;
		$post_ids   = array_values( array_unique( array_map( 'intval', $post_ids ) ) );

		sort( $post_ids );

		return $post_ids;
	}

	/**
	 * Read a post's series membership off the taxonomy.
	 *
	 * A post with no series term is a series of one, which is the whole of a
	 * site that has never split anything. That answer costs one cached term
	 * read, never a term insert.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to resolve.
	 *
	 * @return int[] Every post ID sharing this post's series term, ascending.
	 */
	protected function resolve_from_taxonomy( int $post_id ): array {
		$key = get_current_blog_id() . ':' . $post_id;

		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$term_id = $this->term_id_for_post( $post_id );

		if ( 0 === $term_id ) {
			$this->memo[ $key ] = array( $post_id );

			return $this->memo[ $key ];
		}

		// `get_objects_in_term()` answers a `WP_Error` only for an unregistered
		// taxonomy, and `resolve_post_ids()` has already established that this
		// one is registered before calling here. The union is narrowed rather
		// than branched on, so no arm is left that no fixture can reach.
		$post_ids = array_map( 'intval', (array) get_objects_in_term( array( $term_id ), self::TAXONOMY ) );

		// The resolving post is always a member of its own series, even if the
		// relationship row is missing: returning a set that excludes it would
		// make every occurrence query silently skip its own rows.
		$post_ids[] = $post_id;
		$post_ids   = array_values( array_unique( $post_ids ) );

		sort( $post_ids );

		$this->memo[ $key ] = $post_ids;

		return $post_ids;
	}

	/**
	 * Get the series term one post belongs to.
	 *
	 * `get_the_terms()` rather than `wp_get_object_terms()` so the read is served
	 * from the object term cache `WP_Query` primes for the whole loop, instead of
	 * one query per rendered occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to read.
	 *
	 * @return int The term ID, or 0 when the post belongs to no series term.
	 */
	public function term_id_for_post( int $post_id ): int {
		$terms = get_the_terms( $post_id, self::TAXONOMY );

		// `get_the_terms()` answers `false` for a post with no terms and a
		// `WP_Error` for an unregistered taxonomy. The array test has to come
		// first: `isset()` on an offset of an object that is not `ArrayAccess`
		// is a fatal `Error` in PHP 8, not a falsy read, so testing the offset
		// alone crashed on the error shape rather than absorbing it.
		return is_array( $terms ) && isset( $terms[0] ) ? (int) $terms[0]->term_id : 0;
	}

	/**
	 * Record that two event posts belong to one series.
	 *
	 * The term comes into existence here, at the moment of the first split, and
	 * every later split of **either** post joins that same term rather than
	 * pointing at its predecessor. That is what makes a thrice-split series one
	 * read instead of a walk.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id  Post the series already existed on.
	 * @param int $forward_post_id Post the split just created.
	 *
	 * @return int The series term ID, or 0 when the term could not be created.
	 */
	public function join( int $origin_post_id, int $forward_post_id ): int {
		self::register_taxonomy_for( (string) get_post_type( $origin_post_id ) );

		$term_id = $this->term_id_for_post( $origin_post_id );
		$minted  = false;
		$landed  = true;

		if ( 0 === $term_id ) {
			$term_id = $this->create_term_for( $origin_post_id );
			$minted  = 0 !== $term_id;
			$landed  = $minted && $this->set_series_term( $origin_post_id, $term_id );
		}

		// Every write of the join must land, the relationship rows as much as
		// the term itself. Reporting a term whose relationship write failed
		// left the forward post outside the series with no error anywhere,
		// while the caller treated the join phase as complete.
		if ( ! $landed || ! $this->set_series_term( $forward_post_id, $term_id ) ) {
			// A term minted by this call must not survive its failed join.
			// This frame is the only one that knows the term is new: the
			// caller records its term undo only after a non-zero return, and
			// the orphan-term sweep fires only when a member post is deleted,
			// which a member-less term never sees. The deletion also fires
			// the term lifecycle hook that recomputes the split-series flag
			// `create_term_for()` just turned on, so a failed first split
			// leaves the flag off rather than permanently widening four read
			// paths on a site that never split.
			if ( $minted ) {
				wp_remove_object_terms( $origin_post_id, array( $term_id ), self::TAXONOMY );
				wp_delete_term( $term_id, self::TAXONOMY );
				$this->flush_memo();
			}

			return 0;
		}

		$this->flush_memo();

		return $term_id;
	}

	/**
	 * Attach the series term to one post, reporting whether the write landed.
	 *
	 * `wp_set_object_terms()` reports failure two ways: a `WP_Error`, and an
	 * empty result list, which is what it returns after silently *skipping* a
	 * numeric term ID that `term_exists()` could not confirm. Both mean the
	 * post is not in the series.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post to attach the term to.
	 * @param int $term_id The series term.
	 *
	 * @return bool True when the relationship write landed.
	 */
	protected function set_series_term( int $post_id, int $term_id ): bool {
		$result = wp_set_object_terms( $post_id, array( $term_id ), self::TAXONOMY );

		return is_array( $result ) && array() !== $result;
	}

	/**
	 * Create the series term naming one post.
	 *
	 * The slug names the post the series started on, which is stable and
	 * readable; nothing derives meaning from it, so a later split of a later post
	 * does not rename it.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post the series started on.
	 *
	 * @return int The term ID, or 0 when the term could neither be created nor recovered.
	 */
	protected function create_term_for( int $post_id ): int {
		$slug   = sprintf( 'series-%d', $post_id );
		$result = wp_insert_term( $slug, self::TAXONOMY, array( 'slug' => $slug ) );

		if ( ! is_wp_error( $result ) ) {
			return (int) $result['term_id'];
		}

		// A term left behind by an earlier split whose relationship rows were
		// removed (a restored-from-trash post, a partial import) is recovered
		// rather than treated as a failure. `wp_insert_term()` reports the
		// existing term's ID in the error data.
		$existing = $result->get_error_data( 'term_exists' );

		return is_numeric( $existing ) ? (int) $existing : 0;
	}

	/**
	 * Discard the request-scoped membership memo.
	 *
	 * Production needs this on exactly one path, a split, which changes
	 * membership inside the request that performs it. Tests split and re-read
	 * inside one PHP lifetime and need it for the same reason.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function flush_memo(): void {
		$this->memo = array();
	}
}
