<?php
/**
 * Occurrence integration with `WP_Query`.
 *
 * Short-circuits entirely when the site has no recurring events: an existing
 * site that never authors a recurring event must produce byte-identical SQL and
 * the same query count as before.
 *
 * The join is a `LEFT JOIN`, never an `INNER JOIN`. A non-recurring event has
 * no occurrence row, so an inner join would delete it from every list. The
 * `status = 'scheduled'` predicate lives in the join condition rather than the
 * `WHERE`, and ordering and range predicates use
 * `COALESCE( o.datetime_start_gmt, {events}.datetime_start_gmt )`, which is not
 * sargable. `EXPLAIN` reports `Using temporary; Using filesort` for the
 * expanded statement, against `Using filesort` alone for the same list
 * unexpanded, so the temporary table is what expansion costs on top of a sort
 * the list was already paying. That is the accepted trade.
 *
 * There are two shapes, and the difference between them is what identity the
 * result set can carry:
 *
 * - A compact-fields query, `'fields' => 'ids'` or `'id=>parent'`, is
 *   **folded**, not expanded. `WP_Query` returns before `the_posts` for either
 *   shape, so identity cannot ride along and an expanded compact list is a
 *   repeated bare post ID its caller cannot disambiguate. Folding joins the occurrence table for the range and ordering
 *   predicates and then groups back to one row per post. The live consumer is
 *   `Calendar\Setup::get_ical_list()`, reached through
 *   `Event\Query::get_events_list()`, and it still emits exactly one VEVENT per
 *   series, carrying an `RRULE`, with no two components sharing a UID. Reads that need per-occurrence identity go
 *   through `Occurrences::select_upcoming()`.
 * - Everything else is **expanded** into one row per occurrence, with identity
 *   stamped onto each by `attach_occurrences()`.
 *
 * One scope limit is deliberate: only queries carrying an upcoming/past bucket
 * are touched at all, because only those have the events-table join
 * `COALESCE()` falls back to. A plain Query Loop over an event post type
 * therefore still renders one entry per series, at its anchor date. That is a known
 * limit of the initial recurrence release.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Post;
use WP_Query;

/**
 * Class Query.
 *
 * Singleton owning the occurrence-aware clause and result filters.
 *
 * @since 0.36.0
 */
final class Query {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Autoloaded option recording whether the site has any recurring events.
	 *
	 * Recomputed authoritatively from storage on every lifecycle event. Never a
	 * query on the read path, and never an incrementing counter.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const HAS_RECURRING_OPTION = 'gatherpress_has_recurring_events';

	/**
	 * Read-only derived mirror meta key holding a rule's frequency.
	 *
	 * `refresh_has_recurring_events()` reads this key rather than the canonical
	 * `Meta::META_KEY` blob, because this mirror is written by the rule-meta
	 * derivation in `Meta::set_recurrence()` (via `Meta::write_mirrors()` in
	 * `class-meta.php`), which runs only after the canonical blob has landed
	 * and decoded into a valid, expandable rule. The lifecycle hooks below
	 * watch both keys for exactly this reason: a write to the canonical key
	 * alone can fire before this mirror exists.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const FREQUENCY_META_KEY = 'gatherpress_recurrence_frequency';

	/**
	 * Post statuses that never count as a live recurring event.
	 *
	 * WordPress retains post meta on trash, so a status-blind recompute keeps
	 * counting a trashed series and the sweep never learns the last recurrence
	 * is gone. Trash and auto-draft are excluded because neither is a live
	 * authoring state. Every other status, drafts and custom statuses
	 * included, deliberately stays active: their projections are still
	 * maintained by the sweep, and the read path applies its own `publish`
	 * filter separately.
	 *
	 * Shared with `Occurrences::select_series_needing_top_up()`, so the flag
	 * recompute and the top-up candidate selector always agree on what counts
	 * as live.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const INACTIVE_POST_STATUSES = array( 'trash', 'auto-draft' );

	/**
	 * SQL alias the occurrence table is joined under.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const OCCURRENCE_ALIAS = 'gatherpress_occurrence';

	/**
	 * SQL alias of the per-series sort table joined onto admin event lists.
	 *
	 * Distinct from `OCCURRENCE_ALIAS`: that one names the occurrence table
	 * itself, joined row-for-row to expand a list. This one names a derived
	 * table holding exactly one aggregate row per series, which is why it can
	 * be joined onto the admin list without multiplying its rows.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const ADMIN_SORT_ALIAS = 'gatherpress_admin_occurrence_sort';

	/**
	 * Anchor datetime columns a clause filter may have to rewrite.
	 *
	 * The two columns `Event\Query` reads off the events table: the start it
	 * orders on and the end it buckets Upcoming and Past on. Both are the
	 * series anchor's, and both are rewritten to the occurrence's own value
	 * wherever an occurrence relation is joined.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const ANCHOR_DATETIME_COLUMNS = array( 'datetime_start_gmt', 'datetime_end_gmt' );

	/**
	 * Prefix every occurrence column is aliased under in an expanded SELECT.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SELECT_ALIAS_PREFIX = 'gatherpress_occurrence_';

	/**
	 * SQL alias carrying `recurrence_id` back on a full result set's rows.
	 *
	 * A raw column artifact rather than the published API: `attach_occurrences()`
	 * is what turns it into `RESULT_PROPERTY`, so nothing downstream depends on
	 * the shape the database happened to hand back.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SELECT_ALIAS = self::SELECT_ALIAS_PREFIX . 'recurrence_id';

	/**
	 * Property carrying occurrence identity on each result object.
	 *
	 * Identity travels on the object, never by list position. A null value
	 * means the entry is a non-recurring event.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RESULT_PROPERTY = 'gatherpress_recurrence_id';

	/**
	 * Property carrying an occurrence's own datetime columns on each result object.
	 *
	 * The companion to `RESULT_PROPERTY`, and what makes the render path
	 * readable without a second query: identity alone would force every
	 * consumer to fetch the row it came from, once per loop iteration. The
	 * values ride along on the same object the identity does, so
	 * `Context::loop_occurrence()` is pure property access.
	 *
	 * The *columns* travel rather than just the date because an occurrence's
	 * time of day is read from the occurrence record, never recomposed from the
	 * anchor's time.
	 *
	 * A null value means the entry is a non-recurring event.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RESULT_DATETIME_PROPERTY = 'gatherpress_occurrence_datetime';

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
	 * Every lifecycle path that can change whether any post carries a
	 * recurrence rule recomputes the `HAS_RECURRING_OPTION` flag from storage.
	 * `transition_post_status` and `deleted_post` are scoped to posts whose
	 * post type declares `gatherpress-event-date` support, so a WXR import or
	 * an editor save does not pay a `wp_postmeta` query for every attachment,
	 * revision, and unrelated post type it touches. `import_end` already
	 * sweeps once per import for the bulk case. The three meta hooks watch
	 * both `Meta::META_KEY` and `FREQUENCY_META_KEY`, because the two are
	 * written by separate statements: the save request stores the canonical
	 * blob, and `Meta::set_recurrence()` then derives the mirror from it.
	 * Either write can be the one that completes a not-yet-recurring or
	 * no-longer-recurring transition.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			'transition_post_status',
			array( $this, 'maybe_refresh_has_recurring_events_for_transition' ),
			10,
			3
		);
		add_action( 'deleted_post', array( $this, 'maybe_refresh_has_recurring_events_for_deleted_post' ), 10, 2 );
		add_action( 'added_post_meta', array( $this, 'maybe_refresh_has_recurring_events_for_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'maybe_refresh_has_recurring_events_for_meta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'maybe_refresh_has_recurring_events_for_meta' ), 10, 3 );
		add_action( 'import_end', array( $this, 'refresh_has_recurring_events' ) );
		add_action( 'gatherpress_occurrences_changed', array( $this, 'invalidate_post_query_cache' ) );
		// Priority 11, strictly after Event\Query's own priority-10 clause
		// filters, so the events table is already joined and its ordering and
		// range predicates are there to be rewritten.
		add_filter( 'posts_clauses', array( $this, 'expand_event_clauses' ), 11, 2 );
		// Also priority 11, and registered after `expand_event_clauses()` so it
		// runs after it. `is_admin()` alone does not separate the two:
		// expansion deliberately carves admin-ajax back in because those
		// requests serve front-end reads, so this one excludes admin-ajax
		// explicitly and expansion is the only filter that runs there.
		add_filter( 'posts_clauses', array( $this, 'adjust_admin_occurrence_sorting' ), 11, 2 );
		add_filter( 'the_posts', array( $this, 'attach_occurrences' ), 10, 2 );
	}

	/**
	 * Refresh the has-recurring-events flag when a supported post's status changes.
	 *
	 * Covers publish, trash, untrash and draft transitions in one hook. Scoped
	 * to post types declaring `gatherpress-event-date` support, so attachments,
	 * revisions, and unrelated post types never trigger the recompute query.
	 *
	 * `$new_status` and `$old_status` are required by WordPress'
	 * `transition_post_status` signature and are deliberately unread: the flag
	 * is recomputed from the database, so which way the status moved does not
	 * change the answer.
	 *
	 * @since 0.36.0
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post whose status changed.
	 *
	 * @return void
	 */
	public function maybe_refresh_has_recurring_events_for_transition( $new_status, $old_status, WP_Post $post ): void {
		if ( post_type_supports( $post->post_type, 'gatherpress-event-date' ) ) {
			self::refresh_has_recurring_events();
		}
	}

	/**
	 * Refresh the has-recurring-events flag when a supported post is hard-deleted.
	 *
	 * Scoped the same way as `maybe_refresh_has_recurring_events_for_transition()`.
	 *
	 * `$post_id` is required by WordPress' `deleted_post` signature and is
	 * deliberately unread: the row is already gone by the time this runs, so
	 * the flag is recomputed rather than adjusted for one post.
	 *
	 * @since 0.36.0
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int     $post_id Post ID that was deleted.
	 * @param WP_Post $post    The deleted post.
	 *
	 * @return void
	 */
	public function maybe_refresh_has_recurring_events_for_deleted_post( $post_id, WP_Post $post ): void {
		if ( post_type_supports( $post->post_type, 'gatherpress-event-date' ) ) {
			self::refresh_has_recurring_events();
		}
	}

	/**
	 * Refresh the has-recurring-events flag when the recurrence rule meta changes.
	 *
	 * Filters `added_post_meta`, `updated_post_meta` and `deleted_post_meta` down
	 * to the canonical `Meta::META_KEY` blob and the `FREQUENCY_META_KEY` mirror,
	 * so writes to unrelated meta never trigger the recompute query, and a write
	 * to either half of the pair still catches a transition the other half's
	 * write alone could miss.
	 *
	 * `$meta_id` and `$post_id` are required by WordPress' `added_post_meta`,
	 * `updated_post_meta` and `deleted_post_meta` signatures and are
	 * deliberately unread: the flag is a site-wide recompute, so the key that
	 * changed decides whether to run and the post it belongs to does not.
	 *
	 * @since 0.36.0
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int|int[] $meta_id  Meta ID, or an array of meta IDs for `deleted_post_meta`.
	 * @param int       $post_id  Post ID the meta belongs to.
	 * @param string    $meta_key Meta key that changed.
	 *
	 * @return void
	 */
	public function maybe_refresh_has_recurring_events_for_meta( $meta_id, $post_id, $meta_key = '' ): void {
		if ( in_array( $meta_key, array( Meta::META_KEY, self::FREQUENCY_META_KEY ), true ) ) {
			self::refresh_has_recurring_events();
		}
	}

	/**
	 * Invalidate cached `WP_Query` result sets after an occurrence write.
	 *
	 * Once the clauses below consult the occurrence table, an event query's
	 * result set depends on rows `WP_Query`'s own cache knows nothing about.
	 * That cache is keyed on the `posts` group's `last_changed` value, which
	 * core bumps from `clean_post_cache()`, which is a post write. An occurrence write
	 * touches no post, so nothing bumps it, and a site with a persistent object
	 * cache keeps serving the pre-cancellation post list until some unrelated
	 * edit happens to move it. Bumping the value strands every cached result set
	 * at once, which is the same shape of invalidation `Calendar\Cache` performs
	 * for the rendered bodies and cheaper than reasoning about which queries an
	 * occurrence appeared in.
	 *
	 * Measured, not assumed: without this, canceling every occurrence of a
	 * series and re-running the identical query returns the series from cache
	 * while the same SQL run directly does not.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function invalidate_post_query_cache(): void {
		wp_cache_set_last_changed( 'posts' );
	}

	/**
	 * Join the occurrence table into an event query's clauses.
	 *
	 * Filters `posts_clauses` at priority 11, after `Event\Query`'s own
	 * priority-10 filters.
	 *
	 * The join is a `LEFT JOIN` with `status` in the join condition, never an
	 * `INNER JOIN` and never `status` in the `WHERE`: either shape deletes
	 * every non-recurring event from every list. That alone is not sufficient
	 * though. A series whose occurrences are all canceled matches no join
	 * row, falls through with `NULL` occurrence columns, and would reappear at
	 * its anchor date as an ordinary event. The `NOT EXISTS` guard scopes that
	 * `NULL` fallback to posts with **no occurrence rows at all**, so a series
	 * with rows contributes only its scheduled ones while a non-recurring post
	 * contributes its single anchor row.
	 *
	 * The guard reads the occurrence table, matching
	 * `Occurrences::select_by_horizon()`, and deliberately not the rule
	 * mirror. Keying on the mirror instead asks "does this post have a rule",
	 * which is `true` for a post whose rule has not projected any rows yet, or
	 * whose rule legitimately produces none, such as an `until` that precedes
	 * the anchor, reachable by editing either end of that pair. Such a post would
	 * match no join row *and* be denied the `NULL` fallback, disappearing from
	 * every list, the archive, and the admin screen while still being a
	 * published event. Hiding a real event is strictly worse than showing it
	 * at its anchor date, so the fallback keys on rows. It is a `NOT EXISTS`
	 * semi-join rather than a `LEFT JOIN` because a join can multiply rows
	 * where a semi-join cannot.
	 *
	 * Ordering and range predicates are rewritten to
	 * `COALESCE( occurrence, anchor )`, which is not sargable. The measured
	 * plan is `Using temporary; Using filesort`, where the same list unexpanded
	 * reports `Using filesort` alone, so the temporary table is what the
	 * expansion adds. That is an accepted trade, pinned by
	 * `test_explain_plan_filesorts_and_keeps_the_occurrence_join_indexed()`.
	 *
	 * @since 0.36.0
	 *
	 * @param array    $pieces Query clauses keyed as `WP_Query` supplies them.
	 * @param WP_Query $query  Query being filtered.
	 *
	 * @return array The clauses, occurrence-expanded unless one of the guards above declined.
	 */
	public function expand_event_clauses( array $pieces, WP_Query $query ): array {
		global $wpdb;

		$events_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// Arm 1: a site that never authors a recurring event runs
		// byte-identical SQL and pays nothing.
		//
		// Arm 2 exempts admin queries: `edit.php` is a post-management screen.
		// Its rows carry Edit/Trash/View actions and bulk-action checkboxes
		// keyed by post ID, and `Event\Admin_List` renders its columns from the
		// post. Expanding it would emit one indistinguishable row per
		// occurrence, roughly fifty for a weekly series projected to the
		// twelve-month horizon, pushing every other event off the screen and
		// making a bulk action on "a row" act on the whole series.
		// Per-occurrence management belongs to a dedicated series screen, not
		// to the generic post list. `admin-ajax.php` is carved back out:
		// `is_admin()` is true for every admin-ajax request, but those serve
		// front-end reads, including logged-out ones, which have no rows to
		// manage and must see the same expanded list a page load renders.
		//
		// Arm 3 scopes the filter to queries `Event\Query` has already joined
		// the events table onto, the only case with an anchor column for
		// `COALESCE()` to fall back to. A plain Query Loop over an event post
		// type sets no upcoming/past bucket, so it gets no events join and is
		// not expanded: it shows one entry per series, at the series anchor
		// date. That is a known limit of the initial recurrence release, not
		// an oversight.
		//
		// Arm 4 is the multisite contract, and it has to be a positive check
		// rather than error handling downstream. `$wpdb` swallows a
		// missing-table error and `get_results()` returns `array()`, never
		// `null`, so there is no failure value for a caller to branch on. The
		// missing table is named in both the `LEFT JOIN` and the `NOT EXISTS`
		// subquery, so the whole statement fails and an ordinary,
		// non-recurring published event silently disappears from the list with
		// nothing reported anywhere. That is worse than a crash, because a crash
		// gets reported. Not expanding at all is what makes the stated contract
		// true: a blog without the table shows exactly what it would show with
		// no recurrence code present. It is deliberately the last arm, so a
		// site with no recurring events never pays for the check and its
		// byte-identical SQL is unaffected.
		if (
			! self::site_has_recurring_events()
			|| ( is_admin() && ! wp_doing_ajax() )
			|| ! str_contains( (string) $pieces['join'], $events_table )
			|| ! Occurrences::get_instance()->table_exists()
		) {
			return $pieces;
		}

		// A compact result set, `'ids'` or `'id=>parent'`, is folded rather
		// than expanded. `WP_Query` returns before `the_posts` for either
		// field shape, so occurrence identity cannot travel with the rows and
		// an expanded compact list is a repeated bare post ID its caller
		// cannot disambiguate. Folding gives both shapes the occurrence
		// table's answer to *which posts belong in the bucket* while keeping
		// one row per post, so the two shapes cannot diverge.
		if ( in_array( $query->get( 'fields' ), array( 'ids', 'id=>parent' ), true ) ) {
			return $this->fold_event_clauses( $pieces );
		}

		$alias = self::OCCURRENCE_ALIAS;

		$pieces['join'] .= $this->occurrence_join();

		$pieces['orderby'] = $this->coalesce_event_columns( (string) $pieces['orderby'], $events_table, $alias );

		// Expansion turns a list of posts into a list of occurrences, and two
		// occurrences of different series routinely share a start datetime,
		// which the ordering column alone cannot separate. MySQL's sort is not
		// stable, so a tied pair can be ordered one way for `LIMIT 0, 10` and
		// the other way for `LIMIT 10, 10`, putting one entry on two pages and
		// the other on none. The canonical list key breaks the tie
		// deterministically. Guarded on a non-empty clause for the same reason
		// the `groupby` below is: an `orderby` of `none` must stay unordered
		// rather than acquire an ORDER BY, and a filesort, it never had.
		//
		// Only the post ID half is conditional. `Event\Query`'s `datetime` and
		// `id` arms write that key themselves, in the direction the caller
		// asked for, because a site with no recurring events needs a total
		// ordering and never reaches this method. Appending a second post ID
		// key behind one already there could not change a single row's
		// position, and on a descending list it would read as a contradiction
		// of the key in front of it. The remaining arms, `title`, `modified`
		// and `rand`, order on nothing unique, so they still need the whole
		// `(post_id, recurrence_id)` list key from here.
		if ( '' !== (string) $pieces['orderby'] ) {
			if ( ! $this->orderby_has_post_id( (string) $pieces['orderby'] ) ) {
				$pieces['orderby'] .= $wpdb->prepare( ', %i.ID ASC', $wpdb->posts );
			}

			$pieces['orderby'] .= $wpdb->prepare( ', %i.recurrence_id ASC', $alias );
		}

		$pieces['where'] = $this->coalesce_event_columns( (string) $pieces['where'], $events_table, $alias )
			. $this->occurrence_scope_predicate();

		// WordPress groups on the post ID whenever a tax_query or meta_query
		// can duplicate rows. Collapsing on the post ID alone would collapse
		// every occurrence of a series into one entry, so the group widens to
		// the canonical list key, the `(post_id, recurrence_id)` tuple, which
		// de-duplicates in SQL, before LIMIT and before FOUND_ROWS.
		if ( '' !== (string) $pieces['groupby'] ) {
			$pieces['groupby'] .= $wpdb->prepare( ', %i.recurrence_id', $alias );
		}

		$pieces['fields'] .= $wpdb->prepare( ', %i.recurrence_id AS %i', $alias, self::SELECT_ALIAS );

		// The occurrence's own datetime columns travel with the row so the
		// render path can read them off the result object rather than
		// re-querying once per loop iteration. Selecting them costs nothing
		// beyond the bytes, since the row is already joined and already read.
		foreach ( Context::META_KEY_COLUMNS as $column ) {
			$pieces['fields'] .= $wpdb->prepare(
				', %i.%i AS %i',
				$alias,
				$column,
				self::SELECT_ALIAS_PREFIX . $column
			);
		}

		return $pieces;
	}

	/**
	 * Report whether an ORDER BY clause already orders on the posts-table ID.
	 *
	 * Arm 4 of the guard above scopes this filter to clauses `Event\Query`
	 * wrote, and `Event\Query` writes its ordering unquoted, through
	 * `esc_sql()` rather than through a `%i` placeholder, so the unquoted
	 * rendering is the only one that can appear here.
	 *
	 * @since 0.36.0
	 *
	 * @param string $clause The ORDER BY clause as it stands.
	 *
	 * @return bool True when the clause already carries a posts-table ID key.
	 */
	private function orderby_has_post_id( string $clause ): bool {
		global $wpdb;

		return str_contains( $clause, $wpdb->posts . '.ID' );
	}

	/**
	 * Date, order and bucket an admin event list by each post's shown occurrence.
	 *
	 * `expand_event_clauses()` exempts the admin, so `edit.php` keeps one row
	 * per post. That is the right row count and the wrong date: every clause
	 * `Event\Query::adjust_event_sql()` writes reads the events table's own
	 * `datetime_start_gmt` / `datetime_end_gmt`, which is the series **anchor**,
	 * the date the series first ran. A weekly series anchored last January
	 * therefore sinks to the bottom of a date-ordered list all year, and the
	 * Upcoming and Past view links file it under Past while the column beside
	 * it shows a date tomorrow.
	 *
	 * All three of the list's date decisions are rewritten here off **one**
	 * relation, `Occurrences::display_occurrence_relation_sql()`, so none of
	 * them can contradict another: the ordering (`orderby`), the Upcoming/Past
	 * bucket predicate (`where`), and, through the same relation reached in
	 * PHP by `Occurrences::select_display_for_series()`, the date the column
	 * renders. `Admin_List::get_event_counts()` joins the same relation, so the
	 * view-link counts agree with the rows the view returns.
	 *
	 * The relation is a derived table of exactly one row per post, not the
	 * occurrence table itself, so it cannot multiply the list's rows the way a
	 * plain occurrence join would. It carries the chosen occurrence's own
	 * paired start and end rather than two independent aggregates, which is
	 * what lets the bucket predicate compare an end against now while the
	 * ordering compares that same occurrence's start.
	 *
	 * The relation is **per post, not per series**, and the date column is
	 * scoped the same way (`Admin_List::render_datetime_column()` passes the
	 * row's own post ID). The two agree by construction. Resolving a series
	 * across its sibling posts here would render every row of a split series
	 * with an identical date, and the sibling set comes from the
	 * `gatherpress_series_post_ids` PHP filter, which SQL cannot consult.
	 *
	 * Every aggregate is taken over the same `status = scheduled` set, so a
	 * canceled occurrence never becomes the date a series sorts, buckets or
	 * reads by. A post with no scheduled rows joins to nothing, and every
	 * rewritten clause falls back through `COALESCE()` to the anchor, which is
	 * the only date such a post has.
	 *
	 * The guards, in the order they are cheapest: this is admin-only and never
	 * admin-ajax, because front-end lists, including the ones admin-ajax
	 * serves, get real expansion instead; a site with no recurring events runs
	 * byte-identical SQL; the query has to be for an event post type; the
	 * clauses have to actually carry an anchor datetime column, which is false
	 * on an All view sorted by title, author or RSVP count and makes this free
	 * on those screens; the relation must not already be joined, since a
	 * second copy under the same alias is `ERROR 1066 Not unique table/alias`;
	 * and the occurrence table has to exist on this blog, which is the
	 * multisite contract `expand_event_clauses()` states at length.
	 *
	 * @since 0.36.0
	 *
	 * @param array    $pieces Query clauses keyed as `WP_Query` supplies them.
	 * @param WP_Query $query  Query being filtered.
	 *
	 * @return array The clauses, modified only for admin event lists that read an anchor datetime.
	 */
	public function adjust_admin_occurrence_sorting( array $pieces, WP_Query $query ): array {
		global $wpdb;

		$events_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$alias        = self::ADMIN_SORT_ALIAS;

		if (
			! is_admin()
			|| wp_doing_ajax() // Admin-ajax serves front-end reads; those get real expansion.
			|| ! self::site_has_recurring_events()
			|| ! $this->is_event_query( $query )
			|| ! $this->carries_anchor_datetime( $pieces, $events_table )
			|| str_contains( (string) ( $pieces['join'] ?? '' ), $alias )
			|| ! Occurrences::get_instance()->table_exists()
		) {
			return $pieces;
		}

		$pieces['join'] .= ' LEFT JOIN '
			. Occurrences::get_instance()->display_occurrence_relation_sql( current_time( 'mysql', true ) )
			. $wpdb->prepare( ' AS %i ON %i.ID = %i.series_post_id', $alias, $wpdb->posts, $alias );

		foreach ( array( 'orderby', 'where' ) as $clause ) {
			$pieces[ $clause ] = $this->coalesce_event_columns(
				(string) ( $pieces[ $clause ] ?? '' ),
				$events_table,
				$alias
			);
		}

		return $pieces;
	}

	/**
	 * Report whether either rewritable clause reads an anchor datetime column.
	 *
	 * The admin list only needs the occurrence relation when something in the
	 * query actually compares or orders on the events table's own datetimes:
	 * the `orderby` does on a date-sorted list, and the `where` does on the
	 * Upcoming and Past views. An All view sorted by title reads neither, and
	 * pays nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param array  $pieces       Query clauses keyed as `WP_Query` supplies them.
	 * @param string $events_table Unprefixed-format events table name.
	 *
	 * @return bool True when at least one anchor datetime column is present.
	 */
	private function carries_anchor_datetime( array $pieces, string $events_table ): bool {
		$clauses = (string) ( $pieces['orderby'] ?? '' ) . ' ' . (string) ( $pieces['where'] ?? '' );

		foreach ( self::ANCHOR_DATETIME_COLUMNS as $column ) {
			foreach ( $this->anchor_column_renderings( $events_table, $column ) as $rendering ) {
				if ( str_contains( $clauses, $rendering ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * List both renderings one anchor datetime column can appear under.
	 * Fold an `'ids'` event query onto the occurrence table without multiplying rows.
	 *
	 * A recurring series must appear in the aggregate site-wide, archive,
	 * venue and taxonomy feeds. Those feeds are built by
	 * `Calendar\Setup::get_ical_list()` from `Event\Query::get_events_list()`,
	 * which asks for `'fields' => 'ids'`. Left unexpanded, its
	 * `upcoming` bucket is selected from each series' *anchor*. A series whose
	 * anchor has passed is therefore in no aggregate feed at all, which is every
	 * recurring series from its second date onward.
	 *
	 * Folding rather than expanding is the whole point, and it is the opposite
	 * operation. The join is the same one `expand_event_clauses()` adds, and the
	 * same range and ordering rewrites apply, so the bucket is decided by the
	 * series' scheduled occurrences; the `GROUP BY` on the post ID then
	 * collapses the result back to one row per post. That matters beyond
	 * tidiness: a series shares one `UID` across its whole recurrence set (RFC
	 * 5545 section 3.8.4.4), so one component per occurrence would repeat that
	 * identifier once per date with nothing but `RECURRENCE-ID` to tell the
	 * copies apart, producing a feed that overrides every instance of a rule it
	 * also carries. One component carrying an `RRULE` is how a series is representable
	 * at all.
	 *
	 * Ordering is aggregated for the same reason it is grouped. See
	 * `aggregate_orderby()`.
	 *
	 * @since 0.36.0
	 *
	 * @param array $pieces Query clauses keyed as `WP_Query` supplies them.
	 *
	 * @return array The clauses, folded onto the occurrence table.
	 */
	private function fold_event_clauses( array $pieces ): array {
		global $wpdb;

		$events_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		$pieces['join'] .= $this->occurrence_join();

		$pieces['where'] = $this->coalesce_event_columns(
			(string) $pieces['where'],
			$events_table,
			self::OCCURRENCE_ALIAS
		) . $this->occurrence_scope_predicate();

		$pieces['orderby'] = $this->aggregate_orderby(
			$this->coalesce_event_columns( (string) $pieces['orderby'], $events_table, self::OCCURRENCE_ALIAS )
		);

		// WordPress already groups on the post ID whenever a tax_query or
		// meta_query can duplicate rows, which the venue and taxonomy feeds do.
		// Where it has not, the join above is what duplicates them, so the
		// grouping is added here. Either way the group key is the post ID and
		// the result is one row per series.
		if ( '' === (string) $pieces['groupby'] ) {
			$pieces['groupby'] = $wpdb->prepare( '%i.ID', $wpdb->posts );
		}

		return $pieces;
	}

	/**
	 * Wrap a grouped query's ordering expression in an aggregate.
	 *
	 * A folded query orders by a column belonging to the joined occurrence rows
	 * while grouping them away, so without an aggregate the value MySQL sorts on
	 * is whichever row of the group it happened to read. That is stable within a
	 * statement, arbitrary between them, and enough to move an entry between
	 * pages of a paginated feed. `MIN()` on an ascending sort picks the series'
	 * *next* occurrence and `MAX()` on a descending one picks its most recent,
	 * which is what each bucket means. Only rows that passed the range predicate
	 * are in the group, so the aggregate cannot reach outside the bucket.
	 *
	 * Left alone when the ordering does not reference the occurrence table:
	 * `RAND()`, the post ID, and the post title are all functionally dependent
	 * on the group key or deliberately unordered, and aggregating them would
	 * either change nothing or defeat them.
	 *
	 * The aggregate alone is not a total order, which is the same defect the
	 * expanded path solves with the canonical list key. Two series whose next
	 * scheduled occurrence falls at the same instant tie on the aggregate, MySQL
	 * does not sort stably, and a tied pair can be ordered one way for
	 * `LIMIT 0, 10` and the other for `LIMIT 10, 10`, which repeats one series
	 * across two pages of a feed and drops the other from both. The group key is
	 * unique per row of a folded result and is what breaks the tie; nothing
	 * finer is needed, because folding has already collapsed the occurrences.
	 *
	 * @since 0.36.0
	 *
	 * @param string $orderby The `orderby` clause, already `COALESCE()`-rewritten.
	 *
	 * @return string The clause, aggregated when it needs to be.
	 */
	private function aggregate_orderby( string $orderby ): string {
		global $wpdb;

		if ( ! str_contains( $orderby, 'COALESCE(' ) ) {
			return $orderby;
		}

		// Key by key, never the clause as a whole: an integration can append a
		// tie-breaker between the event rewrite and this fold, and wrapping
		// both keys in one aggregate is `MIN( <key> ASC, <key> )`, a syntax
		// error that empties every aggregate feed. Only the keys the fold
		// rewrote reference the grouped-away occurrence rows; the rest are
		// functionally dependent on the group key and pass through unchanged,
		// which also keeps the clause valid under `ONLY_FULL_GROUP_BY`.
		$keys = array_map(
			array( $this, 'aggregate_orderby_key' ),
			$this->split_orderby( $orderby )
		);

		return implode( ', ', $keys ) . $wpdb->prepare( ', %i.ID ASC', $wpdb->posts );
	}

	/**
	 * Aggregate one ordering key when it references the occurrence columns.
	 *
	 * `MIN()` on an ascending key picks the series' next occurrence and
	 * `MAX()` on a descending one its most recent, which is what each bucket
	 * means. A key without the fold's `COALESCE()` marker orders by a value
	 * functionally dependent on the group key and needs no aggregate.
	 *
	 * @since 0.36.0
	 *
	 * PHPMD reads this as an unused private method. It is called once, as
	 * `array( $this, 'aggregate_orderby_key' )` handed to `array_map()` in the
	 * folded ordering rewrite, and PHPMD does not resolve a method named inside
	 * a callable array.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 *
	 * @param string $key One ordering key, e.g. `<expression> ASC`.
	 *
	 * @return string The key, aggregated when it needs to be.
	 */
	private function aggregate_orderby_key( string $key ): string {
		$key = trim( $key );

		if ( ! str_contains( $key, 'COALESCE(' ) ) {
			return $key;
		}

		$expression = trim( (string) preg_replace( '/\s+(ASC|DESC)\s*$/i', '', $key ) );
		$descending = (bool) preg_match( '/\s+DESC\s*$/i', $key );

		return sprintf(
			'%s( %s ) %s',
			$descending ? 'MAX' : 'MIN',
			$expression,
			$descending ? 'DESC' : 'ASC'
		);
	}

	/**
	 * Split an `ORDER BY` clause into keys on its top-level commas.
	 *
	 * The commas inside `COALESCE( a, b )` separate arguments, not keys, so a
	 * plain explode would cut the fold's own rewrite in half. Parenthesis
	 * depth is what tells the two apart.
	 *
	 * @since 0.36.0
	 *
	 * @param string $orderby The full ordering clause.
	 *
	 * @return string[] The individual keys, in order.
	 */
	private function split_orderby( string $orderby ): array {
		$keys    = array();
		$current = '';
		$depth   = 0;

		foreach ( str_split( $orderby ) as $character ) {
			if ( ',' === $character && 0 === $depth ) {
				$keys[]  = $current;
				$current = '';

				continue;
			}

			if ( '(' === $character ) {
				++$depth;
			} elseif ( ')' === $character ) {
				$depth = max( 0, $depth - 1 );
			}

			$current .= $character;
		}

		$keys[] = $current;

		return $keys;
	}

	/**
	 * The `LEFT JOIN` bringing a series' scheduled occurrences onto its post row.
	 *
	 * Never an `INNER JOIN`, and `status` lives in the join condition rather
	 * than the `WHERE`: either shape deletes every non-recurring event from
	 * every list, and moving `status` to the `WHERE` additionally lets a
	 * fully-canceled series fall through the `NULL` branch and reappear at its
	 * anchor date.
	 *
	 * @since 0.36.0
	 *
	 * @return string The join fragment, with a leading space.
	 */
	private function occurrence_join(): string {
		global $wpdb;

		return $wpdb->prepare(
			' LEFT JOIN %i AS %i ON %i.ID = %i.series_post_id AND %i.status = %s',
			sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ),
			self::OCCURRENCE_ALIAS,
			$wpdb->posts,
			self::OCCURRENCE_ALIAS,
			self::OCCURRENCE_ALIAS,
			Occurrences::STATUS_SCHEDULED
		);
	}

	/**
	 * The predicate scoping the join's `NULL` fallback to posts with no occurrence rows.
	 *
	 * A series whose occurrences are all canceled matches no join row, falls
	 * through with `NULL` occurrence columns, and would reappear at its anchor
	 * date as an ordinary event. This scopes that fallback to posts with **no
	 * occurrence rows at all**, so a series with rows contributes only its
	 * scheduled ones while a non-recurring post contributes its single anchor
	 * row.
	 *
	 * The guard reads the occurrence table, matching
	 * `Occurrences::select_by_horizon()`, and deliberately not the rule mirror.
	 * Keying on the mirror instead asks "does this post have a rule", which is
	 * `true` for a post whose rule has not projected any rows yet, or whose rule
	 * legitimately produces none, such as an `until` that precedes the anchor,
	 * reachable by editing either end of that pair. Such a post would match no
	 * join row *and* be denied the `NULL` fallback, disappearing from every
	 * list, the archive, and the admin screen while still being a published
	 * event. Hiding a real event is strictly worse than showing it at its anchor
	 * date, so the fallback keys on rows. It is a `NOT EXISTS` semi-join rather
	 * than a `LEFT JOIN` because a join can multiply rows where a semi-join
	 * cannot.
	 *
	 * @since 0.36.0
	 *
	 * @return string The predicate fragment, with a leading ` AND `.
	 */
	private function occurrence_scope_predicate(): string {
		global $wpdb;

		return $wpdb->prepare(
			' AND ( %i.series_post_id IS NOT NULL OR NOT EXISTS ('
			. ' SELECT 1 FROM %i AS %i WHERE %i.series_post_id = %i.ID ) )',
			self::OCCURRENCE_ALIAS,
			sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ),
			self::OCCURRENCE_ALIAS . '_rows',
			self::OCCURRENCE_ALIAS . '_rows',
			$wpdb->posts
		);
	}

	/**
	 * Rewrite the anchor datetime columns of one clause as `COALESCE()` fallbacks.
	 *
	 * `Event\Query` writes the ORDER BY column unquoted and the WHERE column
	 * back-quoted, because the latter goes through `$wpdb->prepare()`'s `%i`
	 * placeholder. Both have to be recognized, and both have to be rewritten.
	 *
	 * @since 0.36.0
	 *
	 * @param string $events_table Unprefixed-format events table name.
	 * @param string $column       One anchor datetime column name.
	 *
	 * @return string[] The unquoted and back-quoted renderings, in that order.
	 */
	private function anchor_column_renderings( string $events_table, string $column ): array {
		return array(
			sprintf( '%s.%s', $events_table, $column ),
			sprintf( '`%s`.`%s`', $events_table, $column ),
		);
	}

	/**
	 * Rewrite the anchor datetime columns of one clause as `COALESCE()` fallbacks.
	 *
	 * Both renderings of each column are rewritten, per
	 * `anchor_column_renderings()`. Comparing on `COALESCE( occurrence, anchor )`
	 * is what keeps `Event\Query::get_datetime_comparison_column()`'s "is a
	 * running event still upcoming" semantics intact for occurrences.
	 *
	 * The alias is a parameter because two callers rewrite the same clauses
	 * against two different relations: `expand_event_clauses()` against the
	 * occurrence table joined row-for-row, and
	 * `adjust_admin_occurrence_sorting()` against the one-row-per-post display
	 * relation. Both name a start and an end column, which is the whole
	 * requirement.
	 *
	 * @since 0.36.0
	 *
	 * @param string $clause       One SQL clause, either `orderby` or `where`.
	 * @param string $events_table Unprefixed-format events table name.
	 * @param string $alias        Alias of the joined relation supplying the occurrence datetimes.
	 *
	 * @return string The clause with both anchor columns wrapped.
	 */
	private function coalesce_event_columns( string $clause, string $events_table, string $alias ): string {
		$search  = array();
		$replace = array();

		foreach ( self::ANCHOR_DATETIME_COLUMNS as $column ) {
			$coalesce = sprintf( 'COALESCE( %s.%s, %s.%s )', $alias, $column, $events_table, $column );

			foreach ( $this->anchor_column_renderings( $events_table, $column ) as $rendering ) {
				$search[]  = $rendering;
				$replace[] = $coalesce;
			}
		}

		return str_replace( $search, $replace, $clause );
	}

	/**
	 * Stamp occurrence identity onto a query's results.
	 *
	 * Filters `the_posts` at priority 10. Every result set reaching this filter
	 * is a list of `WP_Post` objects, because `WP_Query` returns before
	 * `the_posts` for both the `'ids'` and the `'id=>parent'` field shapes, so
	 * the plugin's own `Event\Query::get_events_list()` contract is untouched, and
	 * `Occurrences::select_upcoming()` remains the occurrence-aware read API
	 * for GatherPress's own lists.
	 *
	 * @since 0.36.0
	 *
	 * @param array    $posts Results as `WP_Post` objects.
	 * @param WP_Query $query Query being filtered.
	 *
	 * @return array The results, each stamped with its occurrence unless a guard above declined.
	 */
	public function attach_occurrences( array $posts, WP_Query $query ): array {
		if ( ! self::site_has_recurring_events() || ! $this->is_event_query( $query ) ) {
			return $posts;
		}

		return array_map( array( $this, 'stamp_occurrence' ), $posts );
	}

	/**
	 * Report whether a query asks for a post type that supports event dates.
	 *
	 * Scoped through `get_post_types_by_support()` rather than against a
	 * hardcoded post type slug, because recurrence belongs to the
	 * `gatherpress-event-date` support.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Query $query Query being filtered.
	 *
	 * @return bool True when the query targets at least one event post type.
	 */
	private function is_event_query( WP_Query $query ): bool {
		$requested = array_filter( array_map( 'strval', (array) $query->get( 'post_type' ) ) );

		return array() !== array_intersect( $requested, get_post_types_by_support( 'gatherpress-event-date' ) );
	}

	/**
	 * Publish one result row's occurrence identity onto a clone of it.
	 *
	 * The clone is the point, and it is load-bearing on the path that is easy
	 * to overlook. `WP_Query` calls `update_post_caches()` only when
	 * `$is_unfiltered_query` holds *and* `$unfiltered_posts === $this->posts`.
	 * On an expanded query the appended `fields` column already falsifies the
	 * first half. But `attach_occurrences()` also runs on event queries where
	 * `expand_event_clauses()` bailed (a plain Query Loop, which gets no events
	 * join), and there `fields` is untouched and `$is_unfiltered_query` is
	 * `true`. Stamping in place would keep the identity comparison true and
	 * send occurrence-stamped `WP_Post` objects into the shared post cache for
	 * every later reader of those IDs. Returning fresh objects breaks the
	 * comparison, so core takes `_prime_post_caches()` instead.
	 *
	 * Do not "simplify" this to an in-place assignment.
	 *
	 * @since 0.36.0
	 *
	 * PHPMD reads this as an unused private method. It is called once, as
	 * `array_map( array( $this, 'stamp_occurrence' ), $posts )` in
	 * `attach_occurrences()`, and PHPMD does not resolve a method named inside
	 * a callable array.
	 *
	 * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
	 *
	 * @param WP_Post $post One result row.
	 *
	 * @return WP_Post A clone carrying `RESULT_PROPERTY` and `RESULT_DATETIME_PROPERTY`.
	 */
	private function stamp_occurrence( WP_Post $post ): WP_Post {
		$values     = get_object_vars( $post );
		$identifier = $values[ self::SELECT_ALIAS ] ?? null;
		$datetime   = array();

		unset( $values[ self::SELECT_ALIAS ] );

		// The raw aliases are consumed here and never published, matching how
		// `SELECT_ALIAS` is consumed: nothing downstream depends on the shape
		// the database happened to hand back.
		foreach ( Context::META_KEY_COLUMNS as $column ) {
			$column_alias = self::SELECT_ALIAS_PREFIX . $column;

			$datetime[ $column ] = $values[ $column_alias ] ?? null;

			unset( $values[ $column_alias ] );
		}

		$values[ self::RESULT_PROPERTY ]          = ( null === $identifier ) ? null : (string) $identifier;
		$values[ self::RESULT_DATETIME_PROPERTY ] = ( null === $identifier ) ? null : $datetime;

		return new WP_Post( (object) $values );
	}

	/**
	 * Report whether the site has any recurring events.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when at least one event carries a recurrence rule.
	 */
	public static function site_has_recurring_events(): bool {
		return '1' === get_option( self::HAS_RECURRING_OPTION, '0' );
	}

	/**
	 * Recompute the has-recurring-events option from storage.
	 *
	 * Authoritative rather than incremental: the option is derived from what is
	 * stored, so a lost or duplicated lifecycle event cannot desynchronize it.
	 * Reads the rule meta rather than the occurrence table, because the meta is
	 * written the moment a rule is saved while the occurrence table is
	 * populated by a separate projection step. Reading the table here could
	 * observe a rule before its occurrences are projected and write a false
	 * `'0'`, which would hide every recurring event from every query on the
	 * site.
	 *
	 * The meta count is joined to `wp_posts` and scoped away from
	 * `INACTIVE_POST_STATUSES`, because WordPress keeps post meta on trash:
	 * without the join, trashing the site's last recurring event leaves the
	 * frequency mirror in `wp_postmeta` and the flag stuck at `'1'` forever.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public static function refresh_has_recurring_events(): void {
		global $wpdb;

		$status_placeholders = implode( ', ', array_fill( 0, count( self::INACTIVE_POST_STATUSES ), '%s' ) );

		$sql = 'SELECT 1 FROM %i frequency_meta'
			. ' INNER JOIN %i live_post ON live_post.ID = frequency_meta.post_id'
			. " WHERE frequency_meta.meta_key = %s AND frequency_meta.meta_value != ''"
			. " AND live_post.post_status NOT IN ( {$status_placeholders} )"
			. ' LIMIT 1';

		// A lifecycle-triggered recompute, not a read path query; caching it
		// would only cache the flag it is itself in the process of producing.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from %i/%s placeholders only.
		$has = (bool) $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				array_merge(
					array( $wpdb->postmeta, $wpdb->posts, self::FREQUENCY_META_KEY ),
					self::INACTIVE_POST_STATUSES
				)
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		update_option( self::HAS_RECURRING_OPTION, $has ? '1' : '0', true );
	}
}
