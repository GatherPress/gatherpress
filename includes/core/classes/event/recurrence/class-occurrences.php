<?php
/**
 * Occurrence persistence.
 *
 * Owns every read and write of `{prefix}gatherpress_event_occurrences`, whose
 * primary key is the composite `(series_post_id, recurrence_id)`. There is no
 * autoincrement column, which is what makes that identity structural rather than a
 * convention. No insertion-order identifier exists to leak into a URL or an
 * RSVP link.
 *
 * Every read takes an array of post IDs resolved through
 * `Series::resolve_post_ids()` and emits `series_post_id IN (…)`. A query
 * written as `series_post_id = %d` forecloses a future forward split. Mutations (`project()`,
 * `set_status()`, `delete_for_post()`, `get()`) operate on exactly one post's
 * own rows, so `series_post_id = %d` there is correct rather than a violation.
 * The array contract governs series-wide reads, not single-post writes. `delete_for_post()`
 * is deliberately per-post, not per-series: one rule per event post
 * means every call site (`delete_post`, an expand-failure clear) only ever
 * needs to clear the post it was handed. A genuine series-wide delete is a
 * different, not-yet-needed method (`delete_for_series( array $post_ids )`),
 * added when the forward split actually requires one.
 *
 * Cancellation is the `status` column on an occurrence row. The rule
 * is never mutated to express it.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Setup as Core_Setup;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use InvalidArgumentException;
use WP_Error;

/**
 * Class Occurrences.
 *
 * Singleton repository, matching the shape of `Rsvp\Query`.
 *
 * Over PHPMD's size thresholds, deliberately and with the maintainers' call
 * recorded: this is the one repository for the occurrence table, and every
 * method on it is a read or a write against that table. Splitting it to satisfy
 * a line and method count would put statements against one table behind two
 * class names without making any of them simpler, and the split would have to
 * be designed rather than mechanical. Refactoring it remains open for the
 * maintainers; the suppression states the position rather than hiding it.
 *
 * @since 0.36.0
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
final class Occurrences {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Occurrence table name format, taking the table prefix.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const TABLE_FORMAT = '%sgatherpress_event_occurrences';

	/**
	 * Status of an occurrence that is going ahead.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const STATUS_SCHEDULED = 'scheduled';

	/**
	 * Status of an occurrence that has been canceled.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const STATUS_CANCELED = 'canceled';

	/**
	 * How many months ahead of "now" an open-ended rule is projected.
	 *
	 * A `never`-ending rule has no natural horizon, so one is imposed here
	 * rather than expanding toward `Expander::MAX_ITERATIONS`. The horizon is
	 * measured from `max( $anchor_start, now )`, not from the anchor alone. An
	 * anchor-relative horizon computes the same fixed window every time
	 * `project()` runs, so a long-running series eventually projects entirely
	 * into the past and a top-up re-run is a guaranteed no-op. `until`
	 * is not bounded by this constant either: `Expander::expand()` treats a
	 * non-count rule's horizon as its stopping point, so an `UNTIL` far beyond
	 * this window is truncated at the horizon, same as `never`.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const PROJECTION_HORIZON_MONTHS = 12;

	/**
	 * How many days of margin before the horizon a series is topped up.
	 *
	 * A series is treated as needing a top-up once its latest projected
	 * occurrence is within this many days of `resolve_horizon()`'s own
	 * horizon, rather than waiting until the horizon has already been
	 * reached. Filterable via `gatherpress_recurrence_top_up_margin_days`.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const TOP_UP_MARGIN_DAYS = 30;

	/**
	 * Maximum number of series re-projected by one sweep.
	 *
	 * `top_up()` calls `project()` once per candidate, and each `project()`
	 * call can upsert a full horizon's worth of rows for one series (e.g. a
	 * weekly rule over a 12-month horizon is ~52 rows). A batch this size
	 * bounds one sweep's worst case to roughly the same order of magnitude
	 * as `Venue\Map\Prewarm::CONTENT_SCAN_BATCH_SIZE`'s heavier-per-row scan,
	 * scaled down for `project()`'s multi-row-per-candidate cost. Filterable
	 * via `gatherpress_recurrence_top_up_batch_size`.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const TOP_UP_BATCH_SIZE = 50;

	/**
	 * Maximum occurrence rows carried by one INSERT statement.
	 *
	 * Bounds the prepared statement size so a projection can never approach
	 * `max_allowed_packet`, whose MySQL 5.7 default is 4 MB. The worst-case
	 * prepared row is about 400 bytes: a 20-digit `series_post_id`, a
	 * 15-character quoted `recurrence_id`, four 19-character quoted
	 * datetimes, a `timezone` column of up to 255 characters, and the
	 * punctuation between them. One thousand such rows is roughly 0.4 MB,
	 * a tenth of that floor, while typical rows (30-character timezone
	 * names) come to about 0.15 MB per statement.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const UPSERT_CHUNK_SIZE = 1000;

	/**
	 * How long a lazy repair attempt is suppressed for one series.
	 *
	 * Bounds `maybe_repair_stale_series()` to at most one attempt per series
	 * per window, regardless of how many reads hit that series while the
	 * scheduled sweep has not yet caught up to it.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const LAZY_REPAIR_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Transient key format for the lazy-repair debounce, taking a post ID.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const LAZY_REPAIR_TRANSIENT_FORMAT = 'gatherpress_projected_%d';

	/**
	 * Maximum number of distinct series one read attempts to lazily repair.
	 *
	 * `maybe_lazy_repair()` runs synchronously inside a front-end read, so a
	 * listing page surfacing many distinct stale series must not turn into
	 * many synchronous `project()` calls inside one request. The scheduled
	 * sweep is the primary top-up path, and this is a same-request safety net
	 * that is not expected to carry the bulk of the work. Filterable via
	 * `gatherpress_recurrence_lazy_repair_batch_size`.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const LAZY_REPAIR_READ_BATCH_SIZE = 1;

	/**
	 * Posts whose occurrence rows still need a late projection pass.
	 *
	 * Two writers land a post here, mirroring
	 * `Recurrence\Meta::$pending_recurrence`. `maybe_project()` queues a post
	 * whose blob was empty at `wp_after_insert_post` time, because REST,
	 * duplication, and import can all write the blob after the insert
	 * completes. And `maybe_queue_projection()` queues a post on any write to
	 * the blob itself, which is what catches writers that never fire the save
	 * hook at all: WP-CLI, an importer updating an existing post, or a direct
	 * `update_post_meta()` call. Rather than guess, the post is noted here
	 * and decided again on `shutdown`, once every write this request is going
	 * to make has already happened. Any `project()` call that runs after the
	 * queuing write removes the entry again, so the ordinary editor save
	 * projects once, not twice, and so does a series that
	 * `Recurrence\Meta::resolve_pending_revalidation()` re-projects at
	 * `shutdown` priority 15.
	 *
	 * Each value is whether the post already had valid recurrence mirrors at
	 * the moment it was deferred. It is captured before `Meta`'s own deferred
	 * resolution has a chance to touch them, since both classes read the same
	 * meta key at the same point in the same `wp_after_insert_post` firing.
	 * The "a site with no recurring events pays nothing" guarantee turns
	 * on this: an ordinary event that was never recurring, and still is not
	 * at shutdown, must resolve without ever querying the occurrence table.
	 * Only a post that *was* recurring justifies the cleanup query when its
	 * rule turns out to be gone.
	 *
	 * @since 0.36.0
	 * @var array<int, bool>
	 */
	protected array $pending_projection = array();

	/**
	 * Whether the missing-table self-heal already ran this request.
	 *
	 * `maybe_install_missing_table()` installs at most once per request:
	 * a second write failing after a successful install is a real failure to
	 * surface, not a reason to run DDL in a loop.
	 *
	 * @since 0.36.0
	 * @var bool
	 */
	protected bool $table_heal_attempted = false;

	/**
	 * Whether the occurrence table exists, memoized per fully-qualified table name.
	 *
	 * Keyed by the resolved table name rather than held in a single slot, so
	 * `switch_to_blog()` re-decides for the blog it switched to instead of
	 * carrying the previous blog's answer across with it. The memo is
	 * request-scoped on purpose: a persistent cache would have to be
	 * invalidated from every path that can create the table, including ones
	 * outside this plugin, and a stale `true` reintroduces exactly the failure
	 * it exists to prevent.
	 *
	 * @since 0.36.0
	 * @var array<string, bool>
	 */
	protected array $table_exists = array();

	/**
	 * Class constructor.
	 *
	 * Bootstraps `Projection_Cron` here rather than from `Recurrence\Setup`:
	 * the sweep/lazy-repair scheduler it owns exists solely to keep this
	 * class's own `project()` re-running over time, so wiring it up
	 * alongside the class it serves keeps that relationship in one file
	 * instead of splitting it across this class and the subsystem's shared
	 * `Setup::instantiate_classes()`.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();

		Projection_Cron::get_instance();
	}

	/**
	 * Set up hooks for occurrence projection and cleanup.
	 *
	 * `maybe_project()` is registered at priority 20 on `wp_after_insert_post`,
	 * strictly after `Recurrence\Meta::set_recurrence()`'s default-priority-10
	 * handling of the same event, so the mirrors `Rule::from_post()` reads are
	 * already whatever this save produced by the time projection runs.
	 *
	 * The three meta hooks watch writes to the canonical blob itself, the
	 * same watcher `Recurrence\Meta` registers, so a writer that fires no
	 * save hook still gets its occurrence rows reconciled on `shutdown`. The
	 * callbacks bail on the meta-key comparison alone for every other key.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'wp_after_insert_post', array( $this, 'maybe_project' ), 20 );
		add_action( 'delete_post', array( $this, 'maybe_delete_for_post' ) );
		add_action( 'added_post_meta', array( $this, 'maybe_queue_projection' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'maybe_queue_projection' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'maybe_queue_projection' ), 10, 3 );
	}

	/**
	 * Project a post's recurrence rule after a save, deferring when the blob is not there yet.
	 *
	 * Mirrors `Recurrence\Meta::set_recurrence()`'s own deferred-to-`shutdown`
	 * handling of the identical race (the datetime write's #2116 shape, once
	 * more here): reading the raw blob rather than the mirrors is what makes
	 * the two classes' decisions agree on whether this pass is safe to
	 * project from immediately.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID that was just saved.
	 *
	 * @return void
	 */
	public function maybe_project( int $post_id ): void {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			return;
		}

		$data = get_post_meta( $post_id, Meta::META_KEY, true );

		if ( ! empty( $data ) ) {
			// This projection consumes the blob as it stands right now, so a
			// reconciliation queued by an earlier write this request (a blob
			// landed before the save hook fired, or a creation-time deferral
			// followed by a second save) is already satisfied. `project()`
			// drops the queue entry itself, for every caller rather than only
			// this one. A blob write after this point re-queues the post.
			$this->project( $post_id );

			return;
		}

		// Captured now, before Meta's own deferred resolution can touch the
		// mirrors this request. See the $pending_projection property
		// docblock for why this is what keeps an ordinary, never-recurring
		// save from ever querying the occurrence table.
		$this->pending_projection[ $post_id ] = Rule::from_post( $post_id ) instanceof Rule;

		add_action( 'shutdown', array( $this, 'resolve_pending_projection' ), 20 );
	}

	/**
	 * Queue a late projection when the recurrence blob itself is written.
	 *
	 * The occurrence-row counterpart to
	 * `Recurrence\Meta::maybe_queue_reconciliation()`: a blob replaced or
	 * removed by a writer that never fires `wp_after_insert_post` (WP-CLI, an
	 * importer updating an existing post, a direct `update_post_meta()` call)
	 * would otherwise leave the projected rows describing the old rule
	 * forever. Whether the post was recurring is captured at queue time,
	 * before `Meta`'s own shutdown pass can clear the mirrors, so an
	 * ordinary never-recurring post still resolves without ever touching the
	 * occurrence table while a genuinely removed rule gets its rows cleaned
	 * up. The capture is unconditional, matching `maybe_project()`'s own
	 * deferral: the mirrors only ever change on the save hook (which removes
	 * the entry) or at shutdown (after all queuing), so the latest capture
	 * and the earliest are the same value on every reachable path.
	 *
	 * @since 0.36.0
	 *
	 * @param int|int[] $meta_id  Meta ID, or an array of meta IDs for `deleted_post_meta`.
	 * @param int       $post_id  Post ID the meta belongs to.
	 * @param string    $meta_key Meta key that changed.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function maybe_queue_projection( $meta_id, $post_id, $meta_key = '' ): void {
		$post_id = (int) $post_id;

		if (
			Meta::META_KEY !== $meta_key
			|| ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' )
		) {
			return;
		}

		$this->pending_projection[ $post_id ] = Rule::from_post( $post_id ) instanceof Rule;

		add_action( 'shutdown', array( $this, 'resolve_pending_projection' ), 20 );
	}

	/**
	 * Project every post whose occurrence rows still need reconciling.
	 *
	 * Runs on `shutdown` at priority 20, strictly after
	 * `Recurrence\Meta::resolve_pending_recurrence()`'s own default-priority-10
	 * `shutdown` resolution. That resolution is registered dynamically per post,
	 * so the priority gap guarantees the ordering rather than registration order.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function resolve_pending_projection(): void {
		$pending                  = $this->pending_projection;
		$this->pending_projection = array();

		foreach ( $pending as $post_id => $was_recurring ) {
			// The post can be gone by shutdown, after a duplicate that failed
			// or an insert rolled back once this hook had run.
			if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
				continue;
			}

			$this->run_projection( $post_id, $was_recurring );
		}
	}

	/**
	 * Delete a post's occurrence rows when it is hard-deleted, if supported.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @return void
	 */
	public function maybe_delete_for_post( int $post_id ): void {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			return;
		}

		$this->delete_for_post( $post_id );
	}

	/**
	 * Derive an occurrence identifier from its local start.
	 *
	 * The RFC 5545 `RECURRENCE-ID` form, always the occurrence's local start in
	 * `Ymd\THis`. Never an all-day `Ymd` form, never a `Z`-suffixed UTC form.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start Occurrence start in the series timezone.
	 *
	 * @return string The occurrence identifier.
	 */
	public static function recurrence_id( DateTimeImmutable $start ): string {
		return $start->format( 'Ymd\THis' );
	}

	/**
	 * Select upcoming occurrences and non-recurring events as one ordered list.
	 *
	 * Upcoming is inclusive: an entry that has started but not yet ended
	 * still counts, matching `Event\Query::get_datetime_comparison_column()`'s
	 * admin-list semantics. The buckets split at the effective end, so a
	 * running entry appears here and never in `select_past()`.
	 *
	 * Returns value objects rather than bare IDs, so identity travels on the
	 * object and no index-correspondence contract exists between caller and
	 * callee.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $limit Maximum entries to return.
	 * @param array $args  Optional query arguments, including `status`.
	 *
	 * @return Occurrence_Ref[] Ordered ascending by start.
	 */
	public function select_upcoming( int $limit, array $args = array() ): array {
		return $this->select_by_horizon( $limit, $args, true );
	}

	/**
	 * Select past occurrences and non-recurring events as one ordered list.
	 *
	 * Past is exclusive of running entries: only what has already ended
	 * qualifies, the other half of `select_upcoming()`'s inclusive bound.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $limit Maximum entries to return.
	 * @param array $args  Optional query arguments, including `status`.
	 *
	 * @return Occurrence_Ref[] Ordered descending by start.
	 */
	public function select_past( int $limit, array $args = array() ): array {
		return $this->select_by_horizon( $limit, $args, false );
	}

	/**
	 * Shared query behind `select_upcoming()` and `select_past()`.
	 *
	 * `LEFT JOIN`s the occurrence table onto every post of a
	 * `gatherpress-event-date`-supporting type, so a non-recurring event (no
	 * occurrence row at all) still produces one result via
	 * `COALESCE( scheduled_occurrence.datetime_start_gmt, events.datetime_start_gmt )`,
	 * while a recurring series produces one result per matching occurrence
	 * row. The `status` predicate lives in the join condition, never in
	 * `WHERE`. That alone is not sufficient: without the `NOT EXISTS`
	 * guard below, a post whose occurrence rows all fail the status filter
	 * (a fully canceled series) would *also* fall through the `NULL`
	 * `scheduled_occurrence` branch and reappear as if it were a
	 * non-recurring event at its original anchor date. The guard scopes the
	 * `NULL` fallback to posts with **no occurrence rows at all**, so a
	 * canceled series is correctly absent rather than misrepresented.
	 *
	 * The upcoming/past split reads the effective *end*, while ordering
	 * stays on the effective start. Upcoming is inclusive of running entries
	 * and past excludes them, matching
	 * `Event\Query::get_datetime_comparison_column()`: splitting on the
	 * start instead demotes every event and occurrence into the past bucket
	 * the moment it begins, while it is still exactly what an upcoming list
	 * should be showing.
	 *
	 * The boundary predicate itself belongs in `WHERE`, not `HAVING`: the
	 * statement has no `GROUP BY` and no aggregate, so `HAVING` would only
	 * materialize the whole joined result into a temporary table before
	 * filtering it, a measured 17x scan cost for an identical result set.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $limit    Maximum entries to return.
	 * @param array $args     Optional query arguments, including `status`.
	 * @param bool  $upcoming True for ascending/unfinished, false for descending/ended.
	 *
	 * @return Occurrence_Ref[] Ordered by effective start.
	 */
	protected function select_by_horizon( int $limit, array $args, bool $upcoming ): array {
		global $wpdb;

		// MySQL rejects a negative LIMIT as a syntax error, which $wpdb
		// swallows into an empty result plus a poisoned last_error. Zero
		// legitimately selects nothing.
		$limit = max( 0, $limit );

		$post_types = get_post_types_by_support( 'gatherpress-event-date' );

		if ( array() === $post_types ) {
			return array();
		}

		$status = (string) ( $args['status'] ?? self::STATUS_SCHEDULED );

		$events_table      = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$occurrences_table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		$type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$comparison        = $upcoming ? '>=' : '<';
		$order             = $upcoming ? 'ASC' : 'DESC';

		// The no-recurring-events guard and the table-less-blog contract, on this read API as well as
		// on the `posts_clauses` filter. Both arms must be checked here and not
		// only in `Query::expand_event_clauses()`: this method is the public
		// occurrence-aware read entry point, so a caller reaching it directly
		// would otherwise emit SQL naming the occurrence table on a site with
		// no recurring events. On a blog where the table is absent it would
		// return an empty set rather than the anchor rows, because the missing
		// table poisons the `LEFT JOIN` / `NOT EXISTS` pair below.
		//
		// The fallback keeps the same projection and ordering with the
		// occurrence join removed entirely, so non-recurring events are
		// returned exactly as they would be with none of this code present.
		// That includes the `ID` tie-breaker: `effective_start_gmt` alone is
		// not a total order, so two events sharing one start instant can swap
		// places between two identical reads and a paginated list repeats or
		// drops one. Only the `recurrence_id` leg of the joined order is
		// dropped, because this arm has no occurrence rows to name.
		//
		// The upcoming/past split reads the event's own end as
		// `effective_end_gmt`, the same boundary the joined arm applies, while
		// ordering stays on the effective start. Splitting on the start
		// instead demotes a running event into the past bucket the moment it
		// begins, and only on sites where nothing happens to recur.
		if ( ! Query::site_has_recurring_events() || ! $this->table_exists() ) {
			// The join is INNER and the boundary lives in WHERE on the real
			// column, not in HAVING on its alias: the alias here *is* the
			// column, and a post with no events-table row fails the boundary
			// in either direction (`NULL` compares as unknown), so a LEFT
			// JOIN filtered on the joined column returns the same rows while
			// forcing a temporary table and blocking the events-table index
			// from narrowing the scan.
			$sql = 'SELECT %i.ID AS post_id, NULL AS recurrence_id,'
				. ' %i.datetime_start_gmt AS effective_start_gmt,'
				. ' %i.datetime_end_gmt AS effective_end_gmt'
				. ' FROM %i'
				. ' INNER JOIN %i ON %i.ID = %i.post_id'
				. " WHERE %i.post_type IN ( {$type_placeholders} ) AND %i.post_status = %s"
				. " AND %i.datetime_end_gmt {$comparison} %s"
				. " ORDER BY effective_start_gmt {$order}, %i.ID {$order}"
				. ' LIMIT %d';

			$values = array_merge(
				array(
					$wpdb->posts,
					$events_table,
					$events_table,
					$wpdb->posts,
					$events_table,
					$wpdb->posts,
					$events_table,
					$wpdb->posts,
				),
				$post_types,
				array(
					$wpdb->posts,
					'publish',
					$events_table,
					current_time( 'mysql', true ),
					$wpdb->posts,
					$limit,
				)
			);

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from %i/%s/%d placeholders only.
			$anchor_rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

			// No lazy repair on this arm: there are no occurrence rows to be
			// stale, and a site with no recurring events forbids the write it would attempt.
			return array_map( array( $this, 'row_to_ref' ), $anchor_rows );
		}

		$sql = 'SELECT %i.ID AS post_id, scheduled_occurrence.recurrence_id AS recurrence_id,'
			. ' COALESCE( scheduled_occurrence.datetime_start_gmt, %i.datetime_start_gmt ) AS effective_start_gmt,'
			. ' COALESCE( scheduled_occurrence.datetime_end_gmt, %i.datetime_end_gmt ) AS effective_end_gmt'
			. ' FROM %i'
			. ' LEFT JOIN %i ON %i.ID = %i.post_id'
			. ' LEFT JOIN %i AS scheduled_occurrence ON %i.ID = scheduled_occurrence.series_post_id'
			. ' AND scheduled_occurrence.status = %s'
			. " WHERE %i.post_type IN ( {$type_placeholders} ) AND %i.post_status = %s"
			. ' AND ( scheduled_occurrence.recurrence_id IS NOT NULL'
			. ' OR NOT EXISTS ( SELECT 1 FROM %i WHERE series_post_id = %i.ID ) )'
			// The boundary predicate repeats the COALESCE against the real
			// columns rather than referencing the effective_end_gmt alias
			// from HAVING. With no GROUP BY and no aggregate anywhere in the
			// statement, HAVING only forces the whole joined result set
			// through a temporary table before filtering; WHERE discards
			// rows as they are produced. Measured on 999 events and 10,000
			// occurrence rows: Handler_read_rnd_next 10,600 under HAVING
			// against 600 here, for the same rows. The non-sargable COALESCE
			// itself stays, per the accepted filesort trade.
			. " AND COALESCE( scheduled_occurrence.datetime_end_gmt, %i.datetime_end_gmt ) {$comparison} %s"
			// The sort key alone is not unique: any number of events can
			// share one start instant, and one series contributes many rows
			// with the same key only by coincidence. Ties under a
			// LIMIT are resolved by whatever order the plan happens to
			// produce, so a paginated list can repeat or drop an entry after
			// an index or statistics change. post_id then recurrence_id makes
			// the order total, and both tie-breakers follow the primary
			// direction so page N+1 continues where page N stopped in either
			// direction.
			. " ORDER BY effective_start_gmt {$order}, %i.ID {$order},"
			. " scheduled_occurrence.recurrence_id {$order}"
			. ' LIMIT %d';

		$values = array_merge(
			array(
				$wpdb->posts,
				$events_table,
				$events_table,
				$wpdb->posts,
				$events_table,
				$wpdb->posts,
				$events_table,
				$occurrences_table,
				$wpdb->posts,
				$status,
				$wpdb->posts,
			),
			$post_types,
			array(
				$wpdb->posts,
				'publish',
				$occurrences_table,
				$wpdb->posts,
				$events_table,
				current_time( 'mysql', true ),
				$wpdb->posts,
				$limit,
			)
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from %i/%s/%d placeholders only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		// No `null` guard: `get_results()` with `ARRAY_A` returns an array on
		// every path that runs a query, including a failed one. The only
		// `null` it can produce is for an empty query string. A guard here
		// would be a branch claiming to handle a failure it cannot observe.
		$refs = array_map( array( $this, 'row_to_ref' ), $rows );

		// Lazy repair only runs off an upcoming-events read. A past-only
		// read has nothing forward-looking to repair, and gating
		// it here keeps select_past() genuinely read-only.
		if ( $upcoming ) {
			$this->maybe_lazy_repair( $refs );
		}

		return $refs;
	}

	/**
	 * Debounced lazy repair: attempt one repair per stale series
	 * encountered by an upcoming-events read, at most once per
	 * `LAZY_REPAIR_TTL` window.
	 *
	 * Short-circuits on `Query::site_has_recurring_events()` first, so a
	 * site with no recurring events never pays even the transient lookup.
	 *
	 * A ref carrying a non-null `recurrence_id` names an occurrence row and
	 * therefore a series. A ref carrying a null one is ambiguous: it is
	 * either an ordinary non-recurring event, which has nothing to repair, or
	 * a series whose rows are *gone*, which `select_by_horizon()` renders
	 * through its rowless fallback and which is exactly the case repair
	 * exists for. The `end_type` mirror separates the two. Nothing on this
	 * path primes the meta cache on its own, since `select_by_horizon()`
	 * reads through raw `$wpdb`, so the refs' post meta is primed here in
	 * one batched `update_meta_cache()` call rather than the per-ref query
	 * each cold `get_post_meta()` would otherwise issue.
	 *
	 * The per-read cap is applied *after* filtering out series already
	 * suppressed by their debounce transient, not before: refs arrive in
	 * `datetime_start_gmt` order, not staleness order, so slicing the raw
	 * post ID list first lets a series that is currently suppressed (or was
	 * already repaired earlier in this same read) occupy a batch slot and
	 * starve every series behind it out of every read for the rest of the
	 * transient's `LAZY_REPAIR_TTL` window. A fresh series sorting first
	 * would otherwise permanently block a genuinely stale one sorting after
	 * it. `maybe_repair_stale_series()` still re-checks the transient itself
	 * (a second read within the same request could otherwise double-attempt
	 * a series this method already cleared), so this filter is an
	 * optimization that keeps the cap meaningful, not the sole guard.
	 *
	 * @since 0.36.0
	 *
	 * @param Occurrence_Ref[] $refs Refs produced by this read.
	 *
	 * @return void
	 */
	protected function maybe_lazy_repair( array $refs ): void {
		if ( ! Query::site_has_recurring_events() ) {
			return;
		}

		$ref_post_ids = array_values( array_unique( wp_list_pluck( $refs, 'post_id' ) ) );

		if ( array() !== $ref_post_ids ) {
			update_meta_cache( 'post', $ref_post_ids );
		}

		$post_ids = array();

		foreach ( $refs as $ref ) {
			if ( null !== $ref->recurrence_id || $this->has_recurrence_rule( $ref->post_id ) ) {
				$post_ids[ $ref->post_id ] = true;
			}
		}

		$unsuppressed = array_values(
			array_filter(
				array_keys( $post_ids ),
				static function ( $post_id ) {
					return false === get_transient( sprintf( self::LAZY_REPAIR_TRANSIENT_FORMAT, $post_id ) );
				}
			)
		);

		/**
		 * Filters how many distinct stale series one read attempts to lazily repair.
		 *
		 * @since 0.36.0
		 *
		 * @param int $batch_size Batch size, default `Occurrences::LAZY_REPAIR_READ_BATCH_SIZE`.
		 *
		 * @return int Batch size.
		 */
		$limit = max(
			0,
			(int) apply_filters( 'gatherpress_recurrence_lazy_repair_batch_size', self::LAZY_REPAIR_READ_BATCH_SIZE )
		);

		foreach ( array_slice( $unsuppressed, 0, $limit ) as $post_id ) {
			$this->maybe_repair_stale_series( $post_id );
		}
	}

	/**
	 * Report whether a post carries a recurrence rule at all.
	 *
	 * Reads the `end_type` mirror, the same key `is_series_stale()` and
	 * `select_series_needing_top_up()` treat as the presence test for a rule,
	 * so all three agree on what counts as a series. `get_post_meta()` is
	 * served from the post meta cache `maybe_lazy_repair()` primes in one
	 * batch for the whole read.
	 *
	 * Public because `Rsvp_Occurrence::requires_explicit_scope()` keys the
	 * classic RSVP form's scope requirement on the same presence test, so the
	 * renderer and the submission handler cannot drift from what the lazy
	 * repair treats as a series.
	 *
	 * Public because the admin events list marks a row as recurring off the
	 * rule rather than off the occurrence rows. A series whose rows are all
	 * canceled, or whose projection has not run yet, still repeats, and a list
	 * that dropped the marker in either case would be telling the reader the
	 * event is a one-off.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to check.
	 *
	 * @return bool True when the post has a non-empty recurrence end-type mirror.
	 */
	public function has_recurrence_rule( int $post_id ): bool {
		return '' !== (string) get_post_meta( $post_id, 'gatherpress_recurrence_end_type', true );
	}

	/**
	 * Attempt one repair for a series, then suppress further attempts for
	 * `LAZY_REPAIR_TTL`.
	 *
	 * The transient governs attempt frequency only. It is never read as an
	 * oracle for whether the series is actually stale. When it is missing,
	 * this checks real storage via `is_series_stale()` rather than assuming
	 * either state, so a fresh series costs one cheap `SELECT` and no write,
	 * and a genuinely stale one gets exactly one `project()` call per window.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID encountered by a read.
	 *
	 * @return void
	 */
	protected function maybe_repair_stale_series( int $post_id ): void {
		$transient_key = sprintf( self::LAZY_REPAIR_TRANSIENT_FORMAT, $post_id );

		if ( false !== get_transient( $transient_key ) ) {
			return;
		}

		set_transient( $transient_key, time(), self::LAZY_REPAIR_TTL );

		if ( $this->is_series_stale( $post_id ) ) {
			$this->project( $post_id );
		}
	}

	/**
	 * Report whether a series' projected horizon has run short.
	 *
	 * Two end types are already complete by design and are never reported
	 * stale. `COUNT` is always complete. `UNTIL` is complete once its latest
	 * projected occurrence has reached the rule's own `until` bound, which
	 * `has_reached_until()` checks. Re-projecting either would be a no-op at
	 * best and a rewrite of the same rows forever at worst. An empty end type
	 * means the post carries no recurrence rule at all. A series with a rule
	 * but zero projected rows (a failed projection, a partial restore) is
	 * reported stale, since it would otherwise be invisible to both the sweep
	 * and the lazy repair forever. The one exception is an `UNTIL`-bounded
	 * series whose `until` is already in the past, which can only ever expand
	 * to nothing and would otherwise be re-projected fruitlessly forever. That exception is
	 * the same one `select_series_needing_top_up()` encodes in SQL, in a
	 * deliberately one-day-lenient form because SQL cannot see each series'
	 * timezone; this per-series check is the precise one, and a boundary-day
	 * series the SQL still selects settles into agreement after one
	 * projection.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID to check.
	 *
	 * @return bool True when the series has no projected rows at all, or its
	 *              latest projected occurrence is within `TOP_UP_MARGIN_DAYS`
	 *              of the horizon, or beyond it.
	 */
	protected function is_series_stale( int $post_id ): bool {
		global $wpdb;

		$end_type = (string) get_post_meta( $post_id, 'gatherpress_recurrence_end_type', true );

		if ( '' === $end_type || Rule::END_TYPE_COUNT === $end_type ) {
			return false;
		}

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT MAX(datetime_start_gmt) AS latest_gmt, MAX(datetime_start) AS latest_local'
					. ' FROM %i WHERE series_post_id = %d',
				$table,
				$post_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$latest_gmt = null === $row ? null : $row['latest_gmt'];

		// One conditional chain assigning a single result keeps this method
		// at two returns (php:S1142): a rowless series is stale unless its
		// until already expired, a reached until is complete, and everything
		// else compares the latest projected start against the cutoff.
		if ( null === $latest_gmt ) {
			$stale = ! $this->is_expired_until( $post_id, $end_type );
		} elseif ( $this->has_reached_until( $post_id, $end_type, (string) $row['latest_local'] ) ) {
			$stale = false;
		} else {
			$stale = $latest_gmt < $this->resolve_top_up_cutoff()->format( Event::DATETIME_FORMAT );
		}

		return $stale;
	}

	/**
	 * Report whether a rule is `UNTIL`-bounded with an `until` already past.
	 *
	 * Distinct from `has_reached_until()`, which asks whether the projected
	 * rows have caught up with the bound: this asks whether the bound itself
	 * is behind us, which is the only thing a series with no rows at all can
	 * be asked. Such a series expands to nothing however many times it is
	 * projected, so it is complete rather than stale.
	 *
	 * "Behind us" is measured in the series' own timezone, never UTC's.
	 * `until` is a wall-clock calendar rule, and whenever the series' local
	 * date and UTC's date differ a UTC comparison misclassifies the boundary
	 * day: a western-zone series still on its final local day would be
	 * declared expired here and its last occurrence never reprojected.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id  Series post ID.
	 * @param string $end_type One of the `Rule::END_TYPE_*` constants.
	 *
	 * @return bool True when the series is `UNTIL`-bounded and its `until` is in the past.
	 */
	protected function is_expired_until( int $post_id, string $end_type ): bool {
		if ( Rule::END_TYPE_UNTIL !== $end_type ) {
			return false;
		}

		$until = (string) get_post_meta( $post_id, 'gatherpress_recurrence_until', true );

		if ( '' === $until ) {
			return false;
		}

		$today = ( new DateTimeImmutable( 'now', $this->resolve_series_timezone( $post_id ) ) )->format( 'Y-m-d' );

		return $until < $today;
	}

	/**
	 * Resolve the timezone a series' calendar rules are expressed in.
	 *
	 * Reads the same event datetime `Utility::normalize_timezone_string()`
	 * path `resolve_anchor()` uses, so the two agree on what the series'
	 * timezone is. Falls back to UTC when the stored value cannot construct
	 * a `DateTimeZone` at all, e.g. when a misbehaving `gatherpress_timezone`
	 * filter hands back garbage; a boundary comparison in UTC is then no
	 * worse than the state the series is already in.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID.
	 *
	 * @return DateTimeZone The series timezone, or UTC when it cannot be resolved.
	 */
	protected function resolve_series_timezone( int $post_id ): DateTimeZone {
		$event    = new Event( $post_id );
		$datetime = $event->get_datetime();

		$timezone_name = Utility::normalize_timezone_string( (string) $datetime['timezone'] );

		try {
			return new DateTimeZone( $timezone_name );
		} catch ( Exception $e ) {
			return new DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Report whether an `UNTIL`-bounded series has already reached its `until` date.
	 *
	 * Compares only the date portion, since `gatherpress_recurrence_until` is
	 * stored as a bare `Y-m-d` (RFC 5545's `UNTIL` is date-only in this
	 * plugin's authoring model) while `$latest_local` carries a time. Once
	 * the latest projected occurrence's local date is on or after `until`,
	 * `Expander::expand()`'s own `past_until()` guard means nothing further
	 * will ever be produced, so the series is complete rather than stale.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id      Series post ID.
	 * @param string $end_type     One of the `Rule::END_TYPE_*` constants.
	 * @param string $latest_local Latest projected occurrence's local start, `Y-m-d H:i:s`.
	 *
	 * @return bool True when the series is `UNTIL`-bounded and has reached its `until` date.
	 */
	protected function has_reached_until( int $post_id, string $end_type, string $latest_local ): bool {
		if ( Rule::END_TYPE_UNTIL !== $end_type ) {
			return false;
		}

		$until = (string) get_post_meta( $post_id, 'gatherpress_recurrence_until', true );

		if ( '' === $until ) {
			return false;
		}

		return substr( $latest_local, 0, 10 ) >= $until;
	}

	/**
	 * Resolve the cutoff below which a series' latest occurrence counts as stale.
	 *
	 * `TOP_UP_MARGIN_DAYS` before `resolve_horizon()`'s own horizon (measured
	 * from "now" in UTC, since this is not anchored to any one series'
	 * timezone), so a top-up happens with margin to spare rather than only
	 * once the horizon has already been reached.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The cutoff datetime, in UTC.
	 */
	protected function resolve_top_up_cutoff(): DateTimeImmutable {
		$months = $this->resolve_horizon_months();

		/**
		 * Filters how many days of margin before the horizon a series is topped up.
		 *
		 * @since 0.36.0
		 *
		 * @param int $days Number of days, default `Occurrences::TOP_UP_MARGIN_DAYS`.
		 *
		 * @return int Number of days.
		 */
		$margin_days = (int) apply_filters( 'gatherpress_recurrence_top_up_margin_days', self::TOP_UP_MARGIN_DAYS );

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		return $now->modify( sprintf( '+%d months', $months ) )->modify( sprintf( '-%d days', $margin_days ) );
	}

	/**
	 * Select series whose projected horizon is running short.
	 *
	 * A maintenance aggregate for the scheduled sweep, distinct from the
	 * event-listing joins in `select_by_horizon()`: this produces
	 * `series_post_id` candidates only, never rows for display, so
	 * `GROUP BY` / `HAVING MAX()` is the correct tool here rather than a
	 * violation of the "never aggregate a result set of event rows" rule.
	 *
	 * Candidate selection is driven from the `end_type` mirror in
	 * `wp_postmeta`, with the occurrence table `LEFT JOIN`ed on, rather than
	 * driven from the occurrence table itself. Driving it from occurrence
	 * rows made a series with a valid rule and *zero* rows structurally
	 * unselectable. A partial restore, a projection that failed halfway, and a
	 * manual `DELETE` all produce that shape. The only candidates were series
	 * that already had at least one row, so precisely the series that most
	 * needed repair could never be repaired. `MAX( o.datetime_start_gmt ) IS NULL`
	 * is therefore treated as maximally stale rather than as "not a series".
	 *
	 * Candidacy is a positive admission, in SQL: only the two end types a
	 * projection can actually extend, `never` and `until`, are ever
	 * candidates. A negative "not `count`, not blank" predicate admitted
	 * anything else the mirror might hold, so a corrupted mirror carrying an
	 * unrecognized value, which `Rule::is_valid()` rejects and which can
	 * therefore never gain rows, became a permanent top-priority candidate
	 * rewritten by every sweep forever. The positive form excludes, all at
	 * once: an empty mirror (no rule at all, in parity with
	 * `is_series_stale()`'s empty-end-type branch), `COUNT`-bounded rules
	 * (complete by design; their fixed, final occurrence only ever falls
	 * further behind `resolve_top_up_cutoff()` as real time passes), and any
	 * unrecognized value. Also excluded: a post whose status is in
	 * `Query::INACTIVE_POST_STATUSES`, because WordPress keeps post meta on
	 * trash and a meta-only candidate query would re-project a trashed series
	 * on every sweep for as long as any other live recurrence keeps the sweep
	 * enabled; `UNTIL`-bounded rules whose latest projected occurrence has
	 * already reached their `until` mirror; and rowless `UNTIL`-bounded rules
	 * whose `until` is already in the past, which can only ever expand to
	 * nothing.
	 *
	 * Cost, stated because it is structural: the aggregate joins every
	 * occurrence row of every live candidate series through a temp table and
	 * filesort to return at most `$limit` IDs, so one sweep scales as live
	 * series times occurrences per series, not as the batch size.
	 *
	 * `ORDER BY MAX( o.datetime_start_gmt ) ASC` rotates the batch by
	 * staleness rather than by `series_post_id`. Without it, `LIMIT` alone
	 * deterministically returns the same subset (MySQL's default row order
	 * for an unordered `GROUP BY` tracks storage/insertion order, i.e. the
	 * lowest post IDs) on every sweep. A site with more stale candidates
	 * than one batch can starve a newer, more-overdue series behind older,
	 * lower-ID ones indefinitely. Rowless series sort first, since SQL `ASC`
	 * orders `NULL` before every value, which is also the priority they
	 * deserve. The trailing `post_id` tie-breaker makes that order total:
	 * every rowless candidate shares the same `NULL` sort key, so without it
	 * the batch boundary among them is whatever the plan happens to emit.
	 *
	 * @since 0.36.0
	 *
	 * @param int $limit Maximum number of series to return.
	 *
	 * @return int[] Series post IDs needing a top-up, most-overdue first.
	 */
	public function select_series_needing_top_up( int $limit ): array {
		global $wpdb;

		// Same clamp as select_by_horizon(): a negative LIMIT is a MySQL
		// syntax error silently swallowed by $wpdb.
		$limit = max( 0, $limit );

		$table  = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$cutoff = $this->resolve_top_up_cutoff()->format( Event::DATETIME_FORMAT );

		// The rowless UNTIL bound is one calendar day behind UTC's date on
		// purpose. Each series' `until` is a wall-clock date in its own
		// timezone, which this site-wide query cannot see, and a series'
		// local date can trail UTC's by up to a day (UTC-12). Bounding on
		// UTC's own date would permanently drop a western-zone series whose
		// final local day is still running; see is_expired_until() for the
		// per-series precise form of the same comparison. The leniency is
		// self-limiting: a genuinely expired boundary series projects its
		// final rows once, gains rows, and completes via the
		// reached-`until` predicate below.
		$today = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '-1 day' )
			->format( 'Y-m-d' );

		// The joined meta columns are aliased (et_value, until_value) rather
		// than referenced as bare `end_type_meta.meta_value` /
		// `until_meta.meta_value` in GROUP BY and HAVING. A bare reference to
		// a non-aggregate joined column used only in HAVING, once three
		// tables are joined, is rejected as "Unknown column" by both MariaDB
		// and MySQL 8. This is not engine-specific, and putting the raw
		// (unaliased) columns in the SELECT list only worked around it
		// because both were literally named `meta_value`, which itself
		// breaks under a site running with ONLY_FULL_GROUP_BY (WordPress
		// strips that mode on connect, but a site filtering
		// `incompatible_sql_modes` back in would silently get an empty
		// candidate list forever). Aliasing costs nothing and is correct
		// under every SQL mode.
		$status_placeholders = implode( ', ', array_fill( 0, count( Query::INACTIVE_POST_STATUSES ), '%s' ) );

		$sql = 'SELECT end_type_meta.post_id AS series_post_id,'
			. ' end_type_meta.meta_value AS et_value, until_meta.meta_value AS until_value'
			. ' FROM %i end_type_meta'
			. ' INNER JOIN %i live_post ON live_post.ID = end_type_meta.post_id'
			. " AND live_post.post_status NOT IN ( {$status_placeholders} )"
			. ' LEFT JOIN %i until_meta'
			. ' ON until_meta.post_id = end_type_meta.post_id AND until_meta.meta_key = %s'
			. ' LEFT JOIN %i o ON o.series_post_id = end_type_meta.post_id'
			. ' WHERE end_type_meta.meta_key = %s'
			. ' AND end_type_meta.meta_value IN ( %s, %s )'
			. ' GROUP BY end_type_meta.post_id, et_value, until_value'
			. ' HAVING ( MAX( o.datetime_start_gmt ) IS NULL OR MAX( o.datetime_start_gmt ) < %s )'
			. ' AND ('
			. '     et_value != %s'
			. '     OR until_value IS NULL'
			. '     OR until_value = %s'
			. '     OR ('
			. '         MAX( o.datetime_start ) IS NULL AND until_value >= %s'
			. '     )'
			. '     OR DATE( MAX( o.datetime_start ) ) < until_value'
			. ' )'
			. ' ORDER BY MAX( o.datetime_start_gmt ) ASC, end_type_meta.post_id ASC'
			. ' LIMIT %d';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from %i/%s/%d placeholders only.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				$sql,
				array_merge(
					array( $wpdb->postmeta, $wpdb->posts ),
					Query::INACTIVE_POST_STATUSES,
					array(
						$wpdb->postmeta,
						'gatherpress_recurrence_until',
						$table,
						'gatherpress_recurrence_end_type',
						Rule::END_TYPE_NEVER,
						Rule::END_TYPE_UNTIL,
						$cutoff,
						Rule::END_TYPE_UNTIL,
						'',
						$today,
						$limit,
					)
				)
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return array_map( 'intval', $rows );
	}

	/**
	 * Re-project every series whose horizon is running short, up to a batch limit.
	 *
	 * The entry point the scheduled sweep (`Projection_Cron::run_sweep()`)
	 * calls. `project()` is idempotent, so calling it again for a candidate
	 * genuinely extends its horizon without disturbing rows the rule still
	 * produces, past occurrences included. `Expander::expand()` regenerates
	 * those identically because it always walks forward from the series' own
	 * anchor, never from "now".
	 *
	 * @since 0.36.0
	 *
	 * @param int $limit Maximum series to top up, or 0 to use the
	 *                    `gatherpress_recurrence_top_up_batch_size` filter default.
	 *
	 * @return int Number of series whose projection succeeded. A candidate
	 *             whose write failed is not counted, so a sweep that wrote
	 *             nothing reports zero.
	 */
	public function top_up( int $limit = 0 ): int {
		if ( $limit <= 0 ) {
			/**
			 * Filters the maximum number of series topped up by one scheduled sweep.
			 *
			 * @since 0.36.0
			 *
			 * @param int $batch_size Batch size, default `Occurrences::TOP_UP_BATCH_SIZE`.
			 *
			 * @return int Batch size.
			 */
			$limit = max(
				1,
				(int) apply_filters( 'gatherpress_recurrence_top_up_batch_size', self::TOP_UP_BATCH_SIZE )
			);
		}

		$post_ids = $this->select_series_needing_top_up( $limit );
		$topped   = 0;

		foreach ( $post_ids as $post_id ) {
			if ( ! is_wp_error( $this->project( $post_id ) ) ) {
				++$topped;
			}
		}

		return $topped;
	}

	/**
	 * Convert one `select_by_horizon()` result row into an `Occurrence_Ref`.
	 *
	 * @since 0.36.0
	 *
	 * @param array $row Result row keyed `post_id`, `recurrence_id`, `effective_start_gmt`.
	 *
	 * @return Occurrence_Ref The value object carrying occurrence identity.
	 */
	protected function row_to_ref( array $row ): Occurrence_Ref {
		return new Occurrence_Ref(
			(int) $row['post_id'],
			null === $row['recurrence_id'] ? null : (string) $row['recurrence_id'],
			(string) $row['effective_start_gmt']
		);
	}

	/**
	 * Project a series' rule onto occurrence rows.
	 *
	 * Idempotent: upserts on the composite primary key without touching the
	 * `status` of an existing row, and deletes rows the rule no longer
	 * produces. A post that no longer has an expandable rule has its existing
	 * rows cleared rather than left orphaned. That covers a post with no rule
	 * at all and a post whose anchor cannot be resolved. `Recurrence\Meta`
	 * clears the rule
	 * mirrors the moment a recurrence is removed or its timezone is rejected,
	 * and this method is the only thing that ever deletes the occurrence rows
	 * mirrors used to imply.
	 *
	 * A direct projection also consumes any reconciliation this request had
	 * queued for the same post, the way `maybe_project()` consumes it on the
	 * save path. The queue exists for writers that never project (see
	 * `$pending_projection`); a caller that projects deliberately, the save path
	 * and `Splitter::write_rule()` alike, has just consumed the blob as it
	 * stands, so a `shutdown` re-projection would re-derive identical rows for
	 * nothing. Without this,
	 * `Recurrence\Meta::resolve_pending_revalidation()` calling `project()` at
	 * `shutdown` priority 15 leaves the priority-20 pass to project the same
	 * post a second time in one request.
	 *
	 * The cleanup signal survives the unqueue because this call passes
	 * `$cleanup_when_not_recurring = true`, the strongest value the queue can
	 * carry: a post queued as `true` gets its cleanup, and one queued as `false`
	 * gets more than it asked for rather than less. The unqueue is therefore
	 * unconditional, matching the save path's long-standing behavior on a failed
	 * write: the projection sweep, not the request's shutdown, is what retries a
	 * write the database refused. A blob write after this point re-queues the
	 * post, exactly as it does after `maybe_project()`.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID.
	 *
	 * @return int|WP_Error Rows written, 0 when the post is not recurring, or
	 *                      `WP_Error` when a database write failed.
	 */
	public function project( int $post_id ): int|WP_Error {
		$written = $this->run_projection( $post_id, true );

		unset( $this->pending_projection[ $post_id ] );

		return $written;
	}

	/**
	 * Shared implementation behind `project()` and the deferred-shutdown path.
	 *
	 * `$cleanup_when_not_recurring` exists only for `resolve_pending_projection()`:
	 * a direct `project()` call always cleans up when it finds no rule, matching
	 * this method's own frozen "deletes rows the rule no longer produces"
	 * contract. The deferred path instead passes whether the post *was*
	 * recurring at the moment it was deferred, so an ordinary, never-recurring
	 * event resolves without ever touching the occurrence table.
	 * `Rule::from_post()` returning null looks identical whether a post never
	 * had a rule or just lost one, so that distinction has to be captured
	 * earlier, in `maybe_project()`, and threaded through.
	 *
	 * @since 0.36.0
	 *
	 * @param int  $post_id                    Series post ID.
	 * @param bool $cleanup_when_not_recurring Whether to delete existing rows when no rule is found.
	 *
	 * @return int|WP_Error Rows written, 0 when the post is not recurring, or
	 *                      `WP_Error` when a database write failed.
	 */
	protected function run_projection( int $post_id, bool $cleanup_when_not_recurring ): int|WP_Error {
		$resolved = $this->resolve_projectable( $post_id, $cleanup_when_not_recurring );

		if ( null === $resolved ) {
			return 0;
		}

		[ $rule, $anchor_start, $anchor_end, $timezone ] = $resolved;

		$occurrences = $this->expand_or_clear( $rule, $anchor_start, $timezone, $post_id );

		if ( null === $occurrences ) {
			return 0;
		}

		$span = $this->resolve_nominal_span( $anchor_start, $anchor_end );
		$rows = array_map(
			fn( DateTimeImmutable $start ) => $this->build_occurrence_row( $start, $span, $timezone ),
			$occurrences
		);
		$rows = $this->discard_sibling_owned( $post_id, $rows );

		return $this->upsert_occurrences( $post_id, $rows );
	}

	/**
	 * Drop candidate rows whose identifier a sibling of the same series owns.
	 *
	 * The projection-time restatement of `Splitter::verify_partition()`'s
	 * invariant: no identifier is owned by two posts of one series. The
	 * reachable violation is a stale rule re-persisted after a forward split.
	 * The still-open origin editor holds the pre-split rule, one touch of any
	 * rule control writes it back, and re-projecting it would re-upsert every
	 * moved identifier under the origin while the forward post still owns
	 * them. The sibling's ownership wins because the sibling's rows are the
	 * ones carrying the RSVP terms and answering the permalinks.
	 *
	 * An unsplit series pays one memoized membership read and nothing else:
	 * `Series::resolve_post_ids()` answers `array( $post_id )` without a
	 * sibling query for every series on a site that has never split anything.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id Series post ID being projected.
	 * @param array $rows    Candidate row values built by `build_occurrence_row()`.
	 *
	 * @return array The rows no sibling already owns.
	 */
	protected function discard_sibling_owned( int $post_id, array $rows ): array {
		if ( array() === $rows ) {
			return $rows;
		}

		$siblings = array_values(
			array_diff( Series::get_instance()->resolve_post_ids( $post_id ), array( $post_id ) )
		);

		if ( array() === $siblings ) {
			return $rows;
		}

		$owned = array_map(
			'strval',
			wp_list_pluck( $this->select_for_series( $siblings ), 'recurrence_id' )
		);

		if ( array() === $owned ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $owned ): bool {
					return ! in_array( $row['recurrence_id'], $owned, true );
				}
			)
		);
	}

	/**
	 * Resolve the anchor's nominal wall-clock span, immune to zone contamination.
	 *
	 * `DateTimeImmutable::diff()` on two *zoned* datetimes returns their
	 * elapsed real time decomposed into calendar units, not their wall-clock
	 * difference. Across a DST transition the two disagree, and the elapsed
	 * form silently inflates a nominal span the same way an absolute-seconds
	 * delta does. Stripping the zone before diffing (both sides reconstructed
	 * from their own `Y-m-d H:i:s` string in UTC) makes the result a pure
	 * wall-clock calendar difference: the anchor's own printed digits, never
	 * reinterpreted through a UTC offset.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor_start Series anchor start, in the series timezone.
	 * @param DateTimeImmutable $anchor_end   Series anchor end, in the series timezone.
	 *
	 * @return DateInterval The nominal wall-clock span between the two.
	 */
	protected function resolve_nominal_span(
		DateTimeImmutable $anchor_start,
		DateTimeImmutable $anchor_end
	): DateInterval {
		$utc = new DateTimeZone( 'UTC' );

		$naive_start = new DateTimeImmutable( $anchor_start->format( Event::DATETIME_FORMAT ), $utc );
		$naive_end   = new DateTimeImmutable( $anchor_end->format( Event::DATETIME_FORMAT ), $utc );

		return $naive_start->diff( $naive_end );
	}

	/**
	 * Resolve a post's rule and anchor together, clearing its occurrence rows
	 * when either is missing and `$cleanup` allows it.
	 *
	 * Split out from `run_projection()` so the "nothing to project" bail is a
	 * single `return` there (`php:S1142`), and so the clear-on-removal
	 * behavior lives in one place for both failure modes: no rule at all, and
	 * a rule whose anchor datetime cannot be resolved.
	 *
	 * @since 0.36.0
	 *
	 * @param int  $post_id Post ID to resolve.
	 * @param bool $cleanup Whether to delete existing rows when no rule is found.
	 *
	 * @return array{0: Rule, 1: DateTimeImmutable, 2: DateTimeImmutable, 3: DateTimeZone}|null
	 *              The rule, anchor start, anchor end, and timezone, or null
	 *              when the post has no expandable rule.
	 */
	protected function resolve_projectable( int $post_id, bool $cleanup ): ?array {
		$rule = Rule::from_post( $post_id );

		if ( ! $rule instanceof Rule ) {
			if ( $cleanup ) {
				$this->delete_for_post( $post_id );
			}

			return null;
		}

		$anchor = $this->resolve_anchor( $post_id );

		if ( null === $anchor ) {
			if ( $cleanup ) {
				$this->delete_for_post( $post_id );
			}

			return null;
		}

		return array_merge( array( $rule ), $anchor );
	}

	/**
	 * Resolve a post's anchor start, anchor end, and series timezone for projection.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID to resolve the anchor for.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: DateTimeZone}|null
	 *              The anchor start, anchor end, and timezone, or null when the
	 *              timezone string cannot construct a `DateTimeZone` at all, or
	 *              the stored datetime cannot be parsed. Whether the timezone is
	 *              a *named* tz-database identifier is not checked here.
	 *              `expand_or_clear()` is the single source of truth for that,
	 *              via `Expander::expand()`'s own `Timezone_Guard::assert_named()`
	 *              call.
	 */
	protected function resolve_anchor( int $post_id ): ?array {
		$event    = new Event( $post_id );
		$datetime = $event->get_datetime();

		$timezone_name = Utility::normalize_timezone_string( (string) $datetime['timezone'] );

		try {
			// The `gatherpress_timezone` filter runs inside get_datetime()
			// after GatherPress's own validation, so a misbehaving filter can
			// still hand back a string DateTimeZone rejects outright. That
			// must not fatal the post save.
			$timezone = new DateTimeZone( $timezone_name );
		} catch ( Exception $e ) {
			return null;
		}

		$anchor_start = DateTimeImmutable::createFromFormat(
			Event::DATETIME_FORMAT,
			(string) $datetime['datetime_start'],
			$timezone
		);
		$anchor_end   = DateTimeImmutable::createFromFormat(
			Event::DATETIME_FORMAT,
			(string) $datetime['datetime_end'],
			$timezone
		);

		// A rule's mirrors can exist on a post whose own datetime meta never
		// landed, so the anchor may be unparsable even though Rule::from_post()
		// returned a valid rule.
		if ( false === $anchor_start || false === $anchor_end ) {
			return null;
		}

		return array( $anchor_start, $anchor_end, $timezone );
	}

	/**
	 * Expand a rule, clearing the series' occurrence rows when the timezone
	 * turns out not to be a named tz-database identifier.
	 *
	 * `Expander::expand()` asserts the timezone is named on its first line and
	 * does not catch what it throws. GatherPress normalizes site/event
	 * timezones through `Utility::maybe_convert_utc_offset()`, and the
	 * `gatherpress_timezone` filter `resolve_anchor()` reads through can also
	 * hand back a fixed offset after GatherPress's own validation has already
	 * passed, so a fixed UTC offset (`+05:30`) reaching here is a live,
	 * reachable configuration, not a hypothetical one. A rule that can no
	 * longer be expanded must not leave stale occurrences behind, matching
	 * `Recurrence\Meta::write_recurrence()`'s own clear-rather-than-fatal
	 * handling of the identical guard.
	 *
	 * @since 0.36.0
	 *
	 * @param Rule              $rule         Rule being expanded.
	 * @param DateTimeImmutable $anchor_start Series anchor start.
	 * @param DateTimeZone      $timezone     Series timezone.
	 * @param int               $post_id      Series post ID, for the clear-on-catch path.
	 *
	 * @return DateTimeImmutable[]|null The expanded occurrences, or null when the timezone was rejected.
	 */
	protected function expand_or_clear(
		Rule $rule,
		DateTimeImmutable $anchor_start,
		DateTimeZone $timezone,
		int $post_id
	): ?array {
		$through = $this->resolve_horizon( $anchor_start, $timezone );

		try {
			return ( new Expander() )->expand( $rule, $anchor_start, $timezone, $through );
		} catch ( InvalidArgumentException $e ) {
			$this->delete_for_post( $post_id );

			return null;
		}
	}

	/**
	 * Resolve the horizon a `never`- or `until`-ending rule is expanded to.
	 *
	 * Measured from `max( $anchor_start, now )` rather than from the anchor
	 * alone, so a long-running series' horizon rolls forward on every
	 * projection instead of staying pinned to its original anchor date. An
	 * anchor-relative horizon would eventually leave a years-old series
	 * projected entirely into the past, with no upcoming occurrences and no
	 * way for a re-save to fix it, since `project()` is a pure function of
	 * rule and anchor. Filterable so a future top-up task is not
	 * boxed in by a literal.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor_start Series anchor start.
	 * @param DateTimeZone      $timezone     Series timezone, used to read "now".
	 *
	 * @return DateTimeImmutable The horizon datetime.
	 */
	protected function resolve_horizon( DateTimeImmutable $anchor_start, DateTimeZone $timezone ): DateTimeImmutable {
		$months = $this->resolve_horizon_months();
		$now    = new DateTimeImmutable( 'now', $timezone );
		$from   = $anchor_start > $now ? $anchor_start : $now;

		return $from->modify( sprintf( '+%d months', $months ) );
	}

	/**
	 * Resolve the filtered projection horizon, clamped to at least one month.
	 *
	 * The single reader of the horizon filter, shared by `resolve_horizon()`
	 * and `resolve_top_up_cutoff()` so the two can never diverge. The clamp
	 * matches the nearby batch-size filters: an unclamped non-positive value
	 * builds a horizon before `max( anchor, now )`, an open-ended rule then
	 * expands to zero rows, and `upsert_occurrences()` deletes every existing
	 * row as stale.
	 *
	 * @since 0.36.0
	 *
	 * @return int Number of months, at least 1.
	 */
	protected function resolve_horizon_months(): int {
		/**
		 * Filters how many months ahead of "now" an open-ended recurrence rule is projected.
		 *
		 * @since 0.36.0
		 *
		 * @param int $months Number of months, default `Occurrences::PROJECTION_HORIZON_MONTHS`.
		 *
		 * @return int Number of months.
		 */
		$months = (int) apply_filters( 'gatherpress_recurrence_horizon_months', self::PROJECTION_HORIZON_MONTHS );

		return max( 1, $months );
	}

	/**
	 * Build one occurrence's row values from its expanded start.
	 *
	 * The end time carries the anchor's *nominal* wall-clock span, produced by
	 * `resolve_nominal_span()` and applied here through `modify()` rather than
	 * `DateTimeImmutable::add()`. A nominal 2-hour span starting just before a
	 * fall-back transition must still read "2 hours later" on the clock, even
	 * though 10,800 real seconds elapse. Note that `$span` is *not*
	 * `$anchor_start->diff( $anchor_end )` on the zoned anchors directly.
	 * `diff()` between two zoned datetimes returns their *elapsed* real time,
	 * not their wall-clock difference, and reintroduces the exact inflation
	 * this method exists to avoid whenever the anchor itself spans a
	 * transition.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $start    Occurrence start in the series timezone.
	 * @param DateInterval      $span     Nominal wall-clock event span, from `resolve_nominal_span()`.
	 * @param DateTimeZone      $timezone Series timezone.
	 *
	 * @return array<string, string> Row values keyed as the occurrence table's columns are.
	 */
	protected function build_occurrence_row(
		DateTimeImmutable $start,
		DateInterval $span,
		DateTimeZone $timezone
	): array {
		$modifier = '%R%y years %R%m months %R%d days %R%h hours %R%i minutes %R%s seconds';
		$end      = $start->modify( $span->format( $modifier ) );
		$utc      = new DateTimeZone( 'UTC' );

		return array(
			'recurrence_id'      => self::recurrence_id( $start ),
			'datetime_start'     => $start->format( Event::DATETIME_FORMAT ),
			'datetime_start_gmt' => $start->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
			'datetime_end'       => $end->format( Event::DATETIME_FORMAT ),
			'datetime_end_gmt'   => $end->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
			'timezone'           => $timezone->getName(),
		);
	}

	/**
	 * Upsert a series' occurrence rows and delete the ones the rule no longer produces.
	 *
	 * The insert is chunked to `UPSERT_CHUNK_SIZE` rows per statement and
	 * every chunk's `$wpdb->query()` result is inspected, so a failed write
	 * surfaces instead of being reported as rows written. The successful
	 * return stays the row count rather than the summed affected-rows
	 * figure, because `ON DUPLICATE KEY UPDATE` reports 0, 1, or 2 per row
	 * depending on whether it inserted, updated, or matched identically:
	 * an idempotent re-projection would otherwise truthfully write every row
	 * and report zero. The per-chunk results gate the count instead.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id Series post ID.
	 * @param array $rows    Row values built by `build_occurrence_row()`.
	 *
	 * @return int|WP_Error Rows written, or `WP_Error` when a statement failed.
	 */
	protected function upsert_occurrences( int $post_id, array $rows ): int|WP_Error {
		global $wpdb;

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		foreach ( array_chunk( $rows, self::UPSERT_CHUNK_SIZE ) as $chunk ) {
			if ( false === $this->insert_or_update_rows( $table, $post_id, $chunk ) ) {
				return $this->write_error( $post_id );
			}
		}

		if ( false === $this->delete_stale_rows( $table, $post_id, wp_list_pluck( $rows, 'recurrence_id' ) ) ) {
			return $this->write_error( $post_id );
		}

		$this->announce_change( $post_id );

		return count( $rows );
	}

	/**
	 * Build the error a failed occurrence write reports upward.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID whose write failed.
	 *
	 * @return WP_Error The error, carrying the post ID and `$wpdb->last_error`.
	 */
	protected function write_error( int $post_id ): WP_Error {
		global $wpdb;

		return new WP_Error(
			'gatherpress_occurrence_write_failed',
			__( 'Failed to write occurrence rows.', 'gatherpress' ),
			array(
				'post_id'    => $post_id,
				'last_error' => $wpdb->last_error,
			)
		);
	}

	/**
	 * Run one prepared occurrence write, self-healing a missing table once.
	 *
	 * When the statement fails and the occurrence table turns out not to
	 * exist, the table is installed and the statement retried once. That is
	 * the "installed without a version bump" state: `check_plugin_version()`
	 * only runs on `admin_init`, so a site tracking the development branch
	 * can execute this code before anything has created the table.
	 *
	 * @since 0.36.0
	 *
	 * @param string $prepared_sql Fully prepared SQL statement.
	 *
	 * @return int|false Rows affected, or false when the statement failed.
	 */
	protected function execute_write( string $prepared_sql ): int|false {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Prepared by the caller.
		$result = $wpdb->query( $prepared_sql );

		if ( false === $result && $this->maybe_install_missing_table() ) {
			$result = $wpdb->query( $prepared_sql );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return false === $result ? false : (int) $result;
	}

	/**
	 * Install the occurrence table when a failed write reveals it is missing.
	 *
	 * Runs at most one install per request: a failure after a successful
	 * install is a genuine failure that must propagate, not retry DDL in a
	 * loop. The presence check runs only on the failure path, so a healthy
	 * site never pays it.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the table was just installed and a retry is worthwhile.
	 */
	protected function maybe_install_missing_table(): bool {
		global $wpdb;

		if ( $this->table_heal_attempted ) {
			return false;
		}

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $table === $exists ) {
			return false;
		}

		$this->table_heal_attempted = true;

		Core_Setup::get_instance()->install_tables();

		return true;
	}

	/**
	 * Upsert occurrence rows on the composite primary key, never touching `status`.
	 *
	 * `ON DUPLICATE KEY UPDATE` deliberately omits `status`: this is the entire
	 * reason cancellation is a column rather than an `EXDATE` in the rule, and
	 * an existing row's status must survive a rule regeneration untouched.
	 *
	 * @since 0.36.0
	 *
	 * @param string $table   Occurrence table name.
	 * @param int    $post_id Series post ID.
	 * @param array  $rows    Row values built by `build_occurrence_row()`.
	 *
	 * @return int|false Rows affected, or false when the statement failed.
	 */
	protected function insert_or_update_rows( string $table, int $post_id, array $rows ): int|false {
		global $wpdb;

		$placeholders = array();
		$values       = array( $table );

		foreach ( $rows as $row ) {
			$placeholders[] = '(%d, %s, %s, %s, %s, %s, %s)';
			$values[]       = $post_id;
			$values[]       = $row['recurrence_id'];
			$values[]       = $row['datetime_start'];
			$values[]       = $row['datetime_start_gmt'];
			$values[]       = $row['datetime_end'];
			$values[]       = $row['datetime_end_gmt'];
			$values[]       = $row['timezone'];
		}

		// VALUES() in ON DUPLICATE KEY UPDATE is deprecated in MySQL 8.0.20
		// (a warning, still functional) but its replacement, the
		// `AS new ... new.col` row alias, does not exist on MariaDB, which
		// WordPress fully supports. VALUES() is the only spelling that runs
		// on both engines today; revisit if MySQL ever removes it.
		$sql = 'INSERT INTO %i'
			. ' (series_post_id, recurrence_id, datetime_start, datetime_start_gmt,'
			. ' datetime_end, datetime_end_gmt, timezone)'
			. ' VALUES ' . implode( ', ', $placeholders )
			. ' ON DUPLICATE KEY UPDATE'
			. ' datetime_start = VALUES(datetime_start),'
			. ' datetime_start_gmt = VALUES(datetime_start_gmt),'
			. ' datetime_end = VALUES(datetime_end),'
			. ' datetime_end_gmt = VALUES(datetime_end_gmt),'
			. ' timezone = VALUES(timezone)';

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $sql is built from %i/%s/%d placeholders only.
		return $this->execute_write( $wpdb->prepare( $sql, $values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Delete a series' occurrence rows that the current rule no longer produces.
	 *
	 * Deliberately one statement, where the insert beside it chunks to
	 * `UPSERT_CHUNK_SIZE`. Two reasons, and the first is correctness rather
	 * than preference:
	 *
	 * `NOT IN` is a conjunction over the whole produced set, so it does not
	 * decompose the way the insert's `VALUES` list does. Splitting the set in
	 * half and running two deletes would delete every row named in the other
	 * half, which is the live-data loss this method exists to avoid. Bounding
	 * it means selecting the stale identifiers first and deleting them by `IN`,
	 * which trades one statement for two round trips plus a result set.
	 *
	 * Nor is it the binding constraint. Each identifier contributes
	 * `'Ymd\THis', ` to the statement, 19 bytes, against roughly 400 bytes for
	 * the same row in the insert. A 1,000-identifier delete is about 18 KB
	 * where a 1,000-row insert chunk is about 0.4 MB, so the delete stays
	 * inside the same `max_allowed_packet` floor the chunk size was picked
	 * against with more than an order of magnitude to spare.
	 *
	 * @since 0.36.0
	 *
	 * @param string   $table          Occurrence table name.
	 * @param int      $post_id        Series post ID.
	 * @param string[] $recurrence_ids Recurrence identifiers the rule currently produces.
	 *
	 * @return int|false Rows deleted, or false when the statement failed.
	 */
	protected function delete_stale_rows( string $table, int $post_id, array $recurrence_ids ): int|false {
		global $wpdb;

		if ( array() === $recurrence_ids ) {
			return $this->execute_write(
				$wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table, $post_id )
			);
		}

		$placeholders = implode( ', ', array_fill( 0, count( $recurrence_ids ), '%s' ) );
		$sql          = "DELETE FROM %i WHERE series_post_id = %d AND recurrence_id NOT IN ( {$placeholders} )";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		return $this->execute_write(
			$wpdb->prepare( $sql, array_merge( array( $table, $post_id ), $recurrence_ids ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Report whether the occurrence table exists on the current blog.
	 *
	 * Table creation is lazy per blog: `Setup::create_tables()` runs on
	 * network activation and on `wp_initialize_site`, and otherwise only via
	 * `check_plugin_version()` on `admin_init` for the current site. An
	 * existing network upgrading in place therefore has subsites whose
	 * occurrence table does not exist until someone visits their wp-admin.
	 *
	 * This has to be asked before the SQL is built rather than handled after
	 * it runs, because there is nothing to handle: `$wpdb` swallows a
	 * missing-table error rather than throwing, and `get_results()` returns
	 * `array()` rather than `null`, so a failed statement is indistinguishable
	 * from an empty result at every call site.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the current blog has the occurrence table.
	 */
	public function table_exists(): bool {
		global $wpdb;

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		if ( ! isset( $this->table_exists[ $table ] ) ) {
			// A schema probe, not a read-path data query; there is no object
			// cache entry that could answer it and no row for one to hold.
			//
			// `esc_like()` and the strict comparison are both load-bearing, and
			// this table name is the worst case for leaving them out: every
			// `_` in `{prefix}gatherpress_event_occurrences` is a
			// single-character `LIKE` wildcard, so an unescaped pattern is
			// satisfied by any lookalike table. The probe would memoize `true`,
			// the occurrence join would run against a table that does not
			// exist, and ordinary published events would disappear. That is the
			// exact degradation this method exists to prevent. Escaping alone is not
			// enough on principle: `SHOW TABLES` returns the matched *name*, so
			// the answer is only trustworthy once that name is compared with
			// the one asked about.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_var(
				$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$this->table_exists[ $table ] = ( $table === $found );
		}

		return $this->table_exists[ $table ];
	}

	/**
	 * Discard the memoized table-existence answers.
	 *
	 * Called by `Setup::create_tables()`, which is the one path that can turn
	 * a `false` into a `true` inside a single request.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function forget_table_exists(): void {
		$this->table_exists = array();
	}

	/**
	 * Read one occurrence row by its composite key.
	 *
	 * A blog whose table has not been created yet answers "no such row", which
	 * is the same answer the statement would produce and the one every caller
	 * already handles: `Rewrite::parse_request()` 404s on it, and
	 * `Context::set()` leaves the request without occurrence context. Both are
	 * gated on the site-wide recurring-events flag, which says nothing about
	 * whether this blog's table exists, so the probe is what keeps a
	 * lazily-created table from writing a database error into the log, and into
	 * the page wherever `WP_DEBUG_DISPLAY` is on, once per request carrying an
	 * occurrence segment. The probe is the same memoized one
	 * `Query::expand_event_clauses()` guards the list path with, so a request
	 * that renders both pays for it once.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return array|null The row, or null when the composite key matches nothing.
	 */
	public function get( int $post_id, string $recurrence_id ): ?array {
		global $wpdb;

		if ( ! $this->table_exists() ) {
			return null;
		}

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$post_id,
				$recurrence_id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row;
	}

	/**
	 * Read one occurrence of a series by its identifier.
	 *
	 * Takes an array of post IDs, never a single ID, so the query emits
	 * `series_post_id IN (…)` and the forward split stays reachable. This is the lookup
	 * every request-supplied `recurrence_id` is validated against: `get()`
	 * pins one post, which would refuse an occurrence that a forward split had
	 * moved to a sibling post of the same series.
	 *
	 * `LIMIT 1` needs an `ORDER BY` to mean anything. Once a forward split makes a series
	 * span several posts, an identifier can name a row under more than one of
	 * them. Projection itself no longer produces that state, because
	 * `discard_sibling_owned()` skips identifiers a sibling already owns, but
	 * rows written before that guard existed, or by anything else with table
	 * access, can still carry the duplicate. Without an ordering the row MySQL
	 * happens to return is whatever the query plan produces. That choice is not cosmetic:
	 * the returned `series_post_id` is what every downstream consumer keys the
	 * RSVP's occurrence term off, so an unstable pick would move a responder's
	 * RSVP between sibling posts from one request to the next. Lowest post ID
	 * wins, which is stable and is the earliest post of the series.
	 *
	 * @since 0.36.0
	 *
	 * @param int[]  $post_ids      Post IDs from `Series::resolve_post_ids()`.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 *
	 * @return array|null The row, or null when nothing in the series carries that identifier.
	 */
	public function find_in_series( array $post_ids, string $recurrence_id ): ?array {
		global $wpdb;

		// The schema probe belongs here for the same reason it does on `get()`
		// and `select_for_series()`: a blog whose recurring-events flag is on
		// but whose table was never installed would otherwise take a database
		// error. This read in particular resolves occurrence context on a
		// front-end request, so the error would surface to a visitor.
		if ( array() === $post_ids || '' === $recurrence_id || ! $this->table_exists() ) {
			return null;
		}

		$table        = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql          = "SELECT * FROM %i WHERE series_post_id IN ( {$placeholders} )"
			. ' AND recurrence_id = %s ORDER BY series_post_id ASC LIMIT 1';
		$values       = array_merge( array( $table ), $post_ids, array( $recurrence_id ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $values ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row;
	}

	/**
	 * Read the occurrences of a series.
	 *
	 * Takes an array of post IDs, never a single ID, so the query emits
	 * `series_post_id IN (…)` and a future forward split stays reachable.
	 *
	 * The defaults read every row of the series: no time bound and no
	 * limit. Callers with a forward-looking or paginated purpose pass
	 * `after` and `limit`, so the bounding happens in SQL rather than by
	 * hydrating the whole series into PHP and slicing there.
	 *
	 * @since 0.36.0
	 *
	 * @param int[] $post_ids Post IDs from `Series::resolve_post_ids()`.
	 * @param array $args     {
	 *     Optional query arguments. Default empty array.
	 *
	 *     @type string $status Return only rows holding exactly this status.
	 *                          Default unset, all statuses.
	 *     @type string $after  GMT datetime, `Y-m-d H:i:s`. Return only rows
	 *                          whose `datetime_end_gmt` is on or after it.
	 *                          End-inclusive, matching `select_upcoming()`:
	 *                          a running occurrence still returns. Default
	 *                          unset, no time bound.
	 *     @type int    $limit  Maximum rows to return, clamped to at least
	 *                          zero. Default unset, all rows.
	 * }
	 *
	 * @return array The matching occurrence rows.
	 */
	public function select_for_series( array $post_ids, array $args = array() ): array {
		global $wpdb;

		// The empty result a missing table already degrades to, reached without
		// the statement that writes a database error on the way there. See
		// `get()` for why the flag the request paths gate on is not an answer to
		// this question.
		if ( array() === $post_ids || ! $this->table_exists() ) {
			return array();
		}

		$table        = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql          = "SELECT * FROM %i WHERE series_post_id IN ( {$placeholders} )";
		$values       = array_merge( array( $table ), $post_ids );

		if ( isset( $args['status'] ) ) {
			$sql     .= ' AND status = %s';
			$values[] = $args['status'];
		}

		if ( isset( $args['after'] ) ) {
			$sql     .= ' AND datetime_end_gmt >= %s';
			$values[] = (string) $args['after'];
		}

		// The start alone is not a total order, and recurrence_id cannot
		// complete it: the identifier is derived from the local start, so
		// two sibling posts of one series sharing a start share it too.
		// series_post_id is the tie-breaker that makes a limited read
		// deterministic; recurrence_id then orders within one post.
		$sql .= ' ORDER BY datetime_start_gmt ASC, series_post_id ASC, recurrence_id ASC';

		if ( isset( $args['limit'] ) ) {
			// Same clamp as select_by_horizon(): MySQL rejects a negative
			// LIMIT as a syntax error, which $wpdb swallows into an empty
			// result plus a poisoned last_error. Zero legitimately selects
			// nothing.
			$sql     .= ' LIMIT %d';
			$values[] = max( 0, (int) $args['limit'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// See `select_by_horizon()`: `get_results()` with `ARRAY_A` never
		// returns `null` for a non-empty query, so there is no failure value
		// to branch on here either.
		return $rows;
	}

	/**
	 * Read the one occurrence a per-series list row should represent.
	 *
	 * The admin events list renders one row per post, so it has to pick a
	 * single occurrence out of a series that may hold fifty of them. The rule
	 * is "the next one that has not finished yet, and failing that the most
	 * recent one that has", which is what a reader of a list of events expects
	 * a date column to mean.
	 *
	 * Takes an array of post IDs from `Series::resolve_post_ids()` rather than
	 * one ID, so a series a forward split has spread across several posts still
	 * resolves to the earliest upcoming occurrence anywhere in it.
	 *
	 * The elapsed fallback is deliberate rather than a null return. A series
	 * whose occurrences have all happened is still a real event that the list
	 * has to date, and dating it from the series anchor would show the *first*
	 * occurrence, which for a long-running weekly series is years off.
	 *
	 * @since 0.36.0
	 *
	 * @param int[] $post_ids Post IDs from `Series::resolve_post_ids()`.
	 *
	 * @return array|null The chosen occurrence row, or null when the series has no scheduled rows.
	 */
	public function select_display_for_series( array $post_ids ): ?array {
		if ( array() === $post_ids || ! Query::site_has_recurring_events() || ! $this->table_exists() ) {
			return null;
		}

		$upcoming = $this->select_bounded_occurrence( $post_ids, true );

		return ( null !== $upcoming ) ? $upcoming : $this->select_bounded_occurrence( $post_ids, false );
	}

	/**
	 * Read the first scheduled occurrence of a series on one side of "now".
	 *
	 * Bounds on `datetime_end_gmt` rather than on the start, matching
	 * `Event\Query::get_datetime_comparison_column()`'s inclusive-upcoming
	 * semantics: an occurrence that has started but not finished is still the
	 * one a list should be showing.
	 *
	 * The tie this ordering has to break is between *sibling posts* of a split
	 * series contributing rows that share a start, and `recurrence_id` cannot
	 * break it: `recurrence_id` is the occurrence's own local start rendered
	 * `Ymd\THis`, so two rows sharing a start share it too. `series_post_id` is
	 * the column that makes the order total, and lowest post ID wins, matching
	 * `find_in_series()`. `recurrence_id` stays as the last resort for the one
	 * case it can still separate: a DST fold, where two distinct local times
	 * under one post map to a single GMT start.
	 *
	 * Public because it is the shared bounded read behind every "the one
	 * occurrence that answers for this series" question: the admin list's
	 * display row here, and `Rewrite`'s bare-URL redirect target, which
	 * would otherwise hydrate a whole series' row set to emit one
	 * `Location` header.
	 *
	 * @since 0.36.0
	 *
	 * @param int[] $post_ids Post IDs from `Series::resolve_post_ids()`.
	 * @param bool  $upcoming True for the earliest unfinished occurrence, false for the latest finished one.
	 *
	 * @return array|null The matching occurrence row, or null when the bound matches nothing.
	 */
	public function select_bounded_occurrence( array $post_ids, bool $upcoming ): ?array {
		global $wpdb;

		// Same probe the other single-row reads carry. A blog whose flag is on
		// but whose table was never installed would otherwise take a database
		// error here, and this read is reachable from a front-end URL rather
		// than only from an admin screen.
		if ( array() === $post_ids || ! $this->table_exists() ) {
			return null;
		}

		$table        = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
		$comparison   = $upcoming ? '>=' : '<';
		$order        = $upcoming ? 'ASC' : 'DESC';

		$sql = "SELECT * FROM %i WHERE series_post_id IN ( {$placeholders} )"
			. ' AND status = %s'
			. " AND datetime_end_gmt {$comparison} %s"
			. " ORDER BY datetime_start_gmt {$order}, series_post_id ASC, recurrence_id {$order}"
			. ' LIMIT 1';

		$values = array_merge(
			array( $table ),
			$post_ids,
			array( self::STATUS_SCHEDULED, current_time( 'mysql', true ) )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $values ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// `get_row()` answers null both for no match and for a failed query,
		// and this method has nothing different to do in the two cases.
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Build the derived one-row-per-post relation of each post's display occurrence.
	 *
	 * The SQL twin of `select_display_for_series()`'s choice: for every post
	 * that owns scheduled occurrence rows, the earliest occurrence that has
	 * not finished, falling back to the latest one that has, carrying that
	 * occurrence's own paired `datetime_start_gmt` and `datetime_end_gmt`
	 * rather than two independent aggregates. The two SQL consumers are
	 * `Recurrence\Query::adjust_admin_occurrence_sorting()`, which joins it to
	 * supply both the admin list's date ordering and its Upcoming/Past bucket
	 * predicate, and `Admin_List::get_event_counts()`, which joins it so the
	 * view-link counts describe those same rows. The third decision, the date
	 * the column prints, is the PHP twin `select_display_for_series()` making
	 * the identical choice for one post. No row can therefore display a date
	 * that contradicts the view that selected it or the position it was given.
	 *
	 * The paired end is what the bucket predicate needs and what two
	 * independent aggregates cannot supply: bucketing compares the chosen
	 * occurrence's *end* against now while the ordering compares that same
	 * occurrence's *start*, and a `MAX( datetime_end_gmt )` taken across the
	 * whole post would answer for a different occurrence than the one shown.
	 *
	 * The relation is deliberately per-post, not per-series: it groups on
	 * `series_post_id`, the post owning each row, and never consults
	 * `Series::resolve_post_ids()`. The admin list's unit is the post (its
	 * row actions, bulk actions and statuses are all per post), a per-series
	 * date would render every sibling row of a split series identical and
	 * indistinguishable, and the sibling set is defined by a PHP filter that
	 * SQL cannot consult. Series-wide reads remain the job of the selector
	 * methods that take a resolved post ID list.
	 *
	 * The inner pick chooses each post's occurrence start; the outer join
	 * retrieves that occurrence's own row so its start and end stay paired,
	 * and the `MAX()` on the end collapses the theoretical duplicate of two
	 * local starts sharing one GMT instant across a DST fold, keeping the
	 * relation at one row per post.
	 *
	 * One engine caveat, because it does not read off the SQL. Measured on
	 * MariaDB 12 and MySQL 8 over a 10,000-row occurrence table across 200
	 * series, the optimizer splits this derived table and drives it per post
	 * through the primary key, so an `edit.php` load does not aggregate every
	 * occurrence row; forcing `condition_pushdown_for_derived=off` did not
	 * change that plan. MySQL 5.7 has neither lateral derived tables nor
	 * derived condition pushdown and is expected to materialize the whole
	 * grouped aggregate instead. That half is reasoned rather than measured,
	 * and WordPress still lists 5.7 as supported.
	 *
	 * @since 0.36.0
	 *
	 * @param string $now_gmt GMT `Y-m-d H:i:s` instant separating unfinished from finished.
	 *
	 * @return string A parenthesized, fully prepared derived-table expression.
	 */
	public function display_occurrence_relation_sql( string $now_gmt ): string {
		global $wpdb;

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- built from %i/%s placeholders only.
		return (string) $wpdb->prepare(
			'( SELECT chosen.series_post_id, chosen.datetime_start_gmt,'
			. ' MAX( chosen.datetime_end_gmt ) AS datetime_end_gmt'
			. ' FROM %i AS chosen'
			. ' INNER JOIN ( SELECT series_post_id,'
			. ' MIN( CASE WHEN datetime_end_gmt >= %s THEN datetime_start_gmt END ) AS next_start_gmt,'
			. ' MAX( CASE WHEN datetime_end_gmt < %s THEN datetime_start_gmt END ) AS last_start_gmt'
			. ' FROM %i WHERE status = %s GROUP BY series_post_id ) AS pick'
			. ' ON pick.series_post_id = chosen.series_post_id'
			. ' AND chosen.datetime_start_gmt = COALESCE( pick.next_start_gmt, pick.last_start_gmt )'
			. ' WHERE chosen.status = %s'
			. ' GROUP BY chosen.series_post_id, chosen.datetime_start_gmt )',
			$table,
			$now_gmt,
			$now_gmt,
			$table,
			self::STATUS_SCHEDULED,
			self::STATUS_SCHEDULED
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Set the status of one occurrence.
	 *
	 * Scopes its update by both `series_post_id` and `recurrence_id`. Keying on
	 * `recurrence_id` alone is an authorization hole. Only the two `STATUS_*`
	 * constants are accepted: `select_by_horizon()` matches
	 * `status = 'scheduled'` exactly, so a row holding any other string would
	 * vanish from every listing without having been canceled.
	 *
	 * The update runs first and the affected-row count decides, rather than a
	 * check-then-update. A concurrent rule save reprojecting the series can
	 * delete the row between any two statements here, and a preliminary
	 * existence check would report that vanished row as updated. Zero
	 * affected rows is still ambiguous in MySQL, since a same-value write
	 * also reports zero, so only that case pays a follow-up existence read:
	 * row present means a no-op success, row gone means gone.
	 *
	 * The announcement is gated on the row having changed, matching
	 * `move_to_post()`: writing the status a row already has is a write on
	 * paper only, and announcing it advances the series' `SEQUENCE` and
	 * invalidates every calendar response cache for a change no client can
	 * observe.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Series post ID.
	 * @param string $recurrence_id Occurrence identifier in `Ymd\THis` form.
	 * @param string $status        One of the `STATUS_*` constants.
	 *
	 * @return bool True when the composite key matched a row, whether or not the
	 *              value changed; false when it matched nothing or the status is
	 *              not one of the constants.
	 */
	public function set_status( int $post_id, string $recurrence_id, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, array( self::STATUS_SCHEDULED, self::STATUS_CANCELED ), true ) ) {
			return false;
		}

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// `execute_write()` rather than a bare query: it reports what the
		// database did instead of that a statement was issued, and it carries
		// the missing-table self-heal the other writes in this class have.
		$updated = $this->execute_write(
			$wpdb->prepare(
				'UPDATE %i SET status = %s WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$status,
				$post_id,
				$recurrence_id
			)
		);

		// A refused statement is not a vanished row, and the probe below cannot
		// tell them apart: a deadlock or a read-only replica leaves the row
		// sitting there, so probing would answer `true` for a write that never
		// landed and show an occurrence canceled that is still scheduled.
		if ( false === $updated ) {
			return false;
		}

		if ( $updated > 0 ) {
			$this->announce_change( $post_id );
			return true;
		}

		// Zero affected rows is the ambiguous case: the row may already carry
		// this status, or it may be gone. Only a probe separates them, and the
		// caller needs them separated, since one is a success and the other is
		// an occurrence that no longer exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 1 FROM %i WHERE series_post_id = %d AND recurrence_id = %s',
				$table,
				$post_id,
				$recurrence_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $exists;
	}

	/**
	 * Move occurrence rows from one post of a series to another.
	 *
	 * Recycles occurrence records across a split. The row is **moved**, never
	 * deleted and regenerated: it keeps its `recurrence_id`, its datetimes and
	 * its `status`, so a canceled occurrence stays canceled across the split,
	 * and its permalink segment still resolves under the new owner. Identity
	 * is `(series_post_id, recurrence_id)` and the first half **changes**:
	 * a consumer keyed by the full tuple no longer points at the moved row,
	 * and must be migrated by the split coordinator.
	 *
	 * Scopes by both `series_post_id` and `recurrence_id`, never by
	 * `recurrence_id` alone.
	 *
	 * A move is a write on **two** series, so both are announced: the source
	 * loses rows from its feed and its aggregate bucket, the destination gains
	 * them, and a subscriber revalidating either one against an unmoved
	 * `Last-Modified` is told `304` for a body that no longer describes it. The
	 * resolved-context memo is dropped for the same reason. It maps an
	 * occurrence to the post that owned it, and that is precisely what changed.
	 *
	 * The RSVP comments carried by a moved row survive the row itself, but their
	 * taxonomy term slug embeds the owning post ID, so a caller moving rows must
	 * rename the terms in the same operation. That coordination belongs to the
	 * split, not here; this method moves rows and announces the consequences.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $from_post_id   Post the rows currently belong to.
	 * @param int      $to_post_id     Post they move to.
	 * @param string[] $recurrence_ids Occurrence identifiers to move.
	 *
	 * @return int Rows moved.
	 */
	public function move_to_post( int $from_post_id, int $to_post_id, array $recurrence_ids ): int {
		global $wpdb;

		if ( array() === $recurrence_ids || $from_post_id === $to_post_id ) {
			return 0;
		}

		$table        = sprintf( self::TABLE_FORMAT, $wpdb->prefix );
		$placeholders = implode( ', ', array_fill( 0, count( $recurrence_ids ), '%s' ) );
		$sql          = 'UPDATE %i SET series_post_id = %d WHERE series_post_id = %d'
			. " AND recurrence_id IN ( {$placeholders} )";
		$values       = array_merge(
			array( $table, $to_post_id, $from_post_id ),
			array_values( $recurrence_ids )
		);

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$moved = (int) $wpdb->query( $wpdb->prepare( $sql, $values ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 < $moved ) {
			Context::flush_resolved();
			$this->announce_change( $from_post_id );
			$this->announce_change( $to_post_id );
		}

		return $moved;
	}

	/**
	 * Report the occurrence identifiers a candidate rule would produce for a post.
	 *
	 * Read-only, and deliberately so: the organizer must be **shown** how many
	 * RSVPs a rule change would strand before the change is applied, which
	 * means answering "what would this rule produce?" without writing anything.
	 * Everything `project()` does after the expansion is absent here, both the
	 * upsert and the stale-row delete.
	 *
	 * @since 0.36.0
	 *
	 * @param int  $post_id Series post ID whose anchor and timezone the rule is expanded against.
	 * @param Rule $rule    Candidate rule.
	 *
	 * @return string[] The identifiers the rule would produce, ordered ascending.
	 */
	public function preview_recurrence_ids( int $post_id, Rule $rule ): array {
		$anchor = $this->resolve_anchor( $post_id );

		if ( null === $anchor ) {
			return array();
		}

		[ $anchor_start, , $timezone ] = $anchor;

		try {
			$occurrences = ( new Expander() )->expand(
				$rule,
				$anchor_start,
				$timezone,
				$this->resolve_horizon( $anchor_start, $timezone )
			);
		} catch ( InvalidArgumentException ) {
			return array();
		}

		return array_map(
			static fn( DateTimeImmutable $start ) => self::recurrence_id( $start ),
			$occurrences
		);
	}

	/**
	 * Announce that a series' occurrence rows changed.
	 *
	 * Occurrence rows are read while an `.ics` response is built. The aggregate
	 * feeds select their bucket from them, and a canceled row becomes an
	 * `EXDATE`. Yet they are written by bare `$wpdb` statements that touch
	 * no post row, no meta row and no term relationship. None of the hooks
	 * `Calendar\Cache` watches fires for them, so without this the response
	 * cache keeps serving bodies that predate the change, and `Last-Modified`
	 * keeps reporting a moment that has not moved. That second half is the one
	 * that bites: a subscriber revalidating with `If-Modified-Since` and no
	 * stored entity tag is told `304` for as long as the stamp stays put, so a
	 * canceled date may never reach it at all.
	 *
	 * Fired from every write that can alter emitted output rather than from the
	 * one that prompted it: the projection upsert, a status change, and a
	 * per-post delete.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Series post ID whose occurrence rows changed.
	 *
	 * @return void
	 */
	private function announce_change( int $post_id ): void {
		/**
		 * Fires after a series' occurrence rows are written, updated or removed.
		 *
		 * Occurrence writes bypass the post, meta and term hooks entirely, so
		 * anything caching a rendering that reads occurrence rows needs this to
		 * know it went stale, calendar feeds above all.
		 *
		 * @since 0.36.0
		 *
		 * @param int $post_id Series post ID whose occurrence rows changed.
		 */
		do_action( 'gatherpress_occurrences_changed', $post_id );
	}

	/**
	 * Delete every occurrence row belonging to one post.
	 *
	 * Deliberately per-post, not per-series. One rule per event post
	 * means every call site (`delete_post`, an expand-failure
	 * clear) only ever needs to clear the post it was handed. A genuine
	 * series-wide delete is a different, not-yet-needed method
	 * (`delete_for_series( array $post_ids )`), added when the forward
	 * split actually requires one.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post ID whose occurrence rows should be removed.
	 *
	 * @return int|false Rows deleted, or false when the statement failed.
	 */
	public function delete_for_post( int $post_id ): int|false {
		global $wpdb;

		$table = sprintf( self::TABLE_FORMAT, $wpdb->prefix );

		// `int|false`, not `int`: `(int) false` is `0`, which is
		// indistinguishable from a successful delete of a post that had no
		// rows. The two answers mean opposite things to a caller deciding
		// whether the table still holds state for this post, and only one of
		// them should announce a change.
		$deleted = $this->execute_write(
			$wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d', $table, $post_id )
		);

		if ( false !== $deleted && 0 < $deleted ) {
			$this->announce_change( $post_id );
		}

		return $deleted;
	}
}
