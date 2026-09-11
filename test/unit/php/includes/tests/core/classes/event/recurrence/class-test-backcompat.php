<?php
/**
 * Class handles unit tests proving the back-compat guarantee: a site with
 * no recurring events pays nothing, and converting a plain event to/from a
 * recurring one never disturbs its identity, permalink, or RSVPs.
 *
 * Every entry point the recurrence subsystem adds to WordPress's request
 * lifecycle is covered here against a site whose
 * `gatherpress_has_recurring_events` option is `'0'`: the `posts_clauses` and
 * `the_posts` filters, both branches of the `parse_request` handler, the
 * `init` cron scheduler, and the lazy-repair path a read can trigger.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrence_Ref;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Projection_Cron;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rewrite;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Query;

/**
 * Class Test_Backcompat.
 *
 * @since 0.36.0
 */
class Test_Backcompat extends Base {

	use Rewrite_State;

	/**
	 * The reference daily rule: five consecutive daily occurrences.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const DAILY_RULE = array(
		'frequency' => 'daily',
		'interval'  => 1,
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Start every test in this file from an empty occurrence table with the
	 * flag at '0', independent of execution order.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		$this->snapshot_rewrite_state();
	}

	/**
	 * Put the rewrite state back the way this file found it.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->restore_rewrite_state();

		parent::tearDown();
	}

	/**
	 * Build "now" in UTC.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable Current time in UTC.
	 */
	protected function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The anchor start every fixture in this file is built from.
	 *
	 * Relative to now rather than a literal calendar date, and comfortably
	 * ahead of it: several tests in this file put the fixture into an
	 * `upcoming` bucket and then assert it is what the list shows, which is a
	 * comparison against the clock. A pinned anchor would pass until the date
	 * arrived and then fail for a reader with no context. `Test_Loop_Render`
	 * is the model. `test_the_fixture_series_is_never_in_the_past()` fails by
	 * name if this is ever re-pinned.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The fixture anchor start in UTC.
	 */
	protected function anchor(): DateTimeImmutable {
		return $this->now()->modify( '+2 hours' );
	}

	/**
	 * Create a published, non-recurring, upcoming event with a datetime range.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Extra factory args, e.g. `post_name`.
	 *
	 * @return int The created post ID.
	 */
	protected function create_event( array $args = array() ): int {
		$start = $this->anchor();
		$end   = $start->modify( '+2 hours' );

		$post_id = $this->factory->post->create(
			array_merge(
				array(
					'post_type'   => Event::POST_TYPE,
					'post_status' => 'publish',
				),
				$args
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		return (int) $post_id;
	}

	/**
	 * Turn on pretty permalinks and register the occurrence rewrite rule, so
	 * `parse_request` scenarios exercise the same regex-matched request path
	 * a real front end request takes.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function enable_pretty_permalinks(): void {
		global $wp_rewrite;

		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		$wp_rewrite->set_permalink_structure( '/%postname%/' );

		do_action( 'init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		Rewrite::get_instance()->add_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	/**
	 * Reduce `$wpdb->queries` entries to their bare SQL strings.
	 *
	 * @since 0.36.0
	 *
	 * @param array $queries A slice of `$wpdb->queries`.
	 *
	 * @return string[] The SQL text of each query, in order.
	 */
	protected function query_sql( array $queries ): array {
		return array_column( $queries, 0 );
	}

	/**
	 * Run a callback and return the SQL text of every query it issued.
	 *
	 * @since 0.36.0
	 *
	 * @param callable $callback Callback to run.
	 *
	 * @return string[] The SQL text of every query issued while the callback ran.
	 */
	protected function capture_sql( callable $callback ): array {
		global $wpdb;

		$before = count( $wpdb->queries );

		$callback();

		return $this->query_sql( array_slice( $wpdb->queries, $before ) );
	}

	/**
	 * Filter a list of SQL strings down to the ones mentioning a table.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $queries SQL strings to filter.
	 * @param string   $table   Unprefixed-format table name to search for.
	 *
	 * @return string[] The subset of queries mentioning the table.
	 */
	protected function queries_touching( array $queries, string $table ): array {
		return array_values(
			array_filter(
				$queries,
				static function ( string $sql ) use ( $table ): bool {
					return str_contains( $sql, $table );
				}
			)
		);
	}

	/**
	 * The occurrence table name for the current test database prefix.
	 *
	 * @since 0.36.0
	 *
	 * @return string The occurrence table name.
	 */
	protected function occurrence_table(): string {
		global $wpdb;

		return sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
	}

	/**
	 * Build the arguments for an "upcoming events" list query, the read this
	 * whole file's back-compat guarantee is measured against.
	 *
	 * @since 0.36.0
	 *
	 * @return array The query arguments.
	 */
	protected function upcoming_events_args(): array {
		return array(
			'post_type'                    => Event::POST_TYPE,
			Event_Query::EVENT_QUERY_PARAM => 'upcoming',
			'posts_per_page'               => 20,
			'orderby'                      => 'datetime',
			'order'                        => 'ASC',
		);
	}

	/**
	 * Rendering the upcoming-events list on a site with no recurring events
	 * produces byte-identical SQL and an identical query count to a baseline
	 * with the recurrence hooks removed entirely.
	 *
	 * The baseline is produced by removing `Query::expand_event_clauses()` from
	 * `posts_clauses` and `Query::attach_occurrences()` from `the_posts` for the
	 * duration of one query, then re-adding them. That is the "hooks not
	 * registered at all" side of the comparison, rather than removing every
	 * recurrence hook process-wide, which would also tear down hooks this
	 * file's other tests depend on.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 * @covers \GatherPress\Core\Event\Recurrence\Query::attach_occurrences
	 *
	 * @return void
	 */
	public function test_no_recurring_events_means_zero_additional_queries(): void {
		$this->create_event();

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$args = $this->upcoming_events_args();

		// A persistent-shaped object cache serves the second identical
		// WP_Query from its split-query cache with no SQL at all, which
		// would make the comparison trivially pass by starving it of any
		// query to compare. Flushing before each capture keeps both runs
		// cold, so the comparison is between two real SQL query lists.
		wp_cache_flush();

		$with_hooks = $this->capture_sql(
			static function () use ( $args ): void {
				new WP_Query( $args );
			}
		);

		remove_filter( 'posts_clauses', array( Query::get_instance(), 'expand_event_clauses' ), 11 );
		remove_filter( 'the_posts', array( Query::get_instance(), 'attach_occurrences' ), 10 );

		wp_cache_flush();

		$without_hooks = $this->capture_sql(
			static function () use ( $args ): void {
				new WP_Query( $args );
			}
		);

		add_filter( 'posts_clauses', array( Query::get_instance(), 'expand_event_clauses' ), 11, 2 );
		add_filter( 'the_posts', array( Query::get_instance(), 'attach_occurrences' ), 10, 2 );

		$this->assertCount(
			count( $without_hooks ),
			$with_hooks,
			'Failed to assert the query count is identical with and without the recurrence hooks registered.'
		);
		$this->assertSame(
			$without_hooks,
			$with_hooks,
			'Failed to assert the SQL text of every query is byte-identical with and without the recurrence hooks.'
		);
		$this->assertSame(
			array(),
			$this->queries_touching( $with_hooks, $this->occurrence_table() ),
			'Failed to assert no query with the hooks registered references the occurrence table.'
		);
	}

	/**
	 * Coverage for the `posts_clauses` and `the_posts` entry points: rendering
	 * the upcoming-events list issues no query mentioning the occurrence table
	 * when the site has no recurring events.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 * @covers \GatherPress\Core\Event\Recurrence\Query::attach_occurrences
	 *
	 * @return void
	 */
	public function test_posts_clauses_and_the_posts_do_not_touch_occurrence_table(): void {
		$this->create_event();

		$args = $this->upcoming_events_args();

		$sql = $this->capture_sql(
			static function () use ( $args ): void {
				new WP_Query( $args );
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert rendering the upcoming-events list issues no query against the occurrence table.'
		);
	}

	/**
	 * Coverage for the `parse_request` handler's bare-URL branch
	 * (`Rewrite::maybe_resolve_bare_series()`): visiting a plain event's own
	 * permalink issues no query against the occurrence table.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::maybe_resolve_bare_series
	 *
	 * @return void
	 */
	public function test_parse_request_bare_series_branch_does_not_touch_occurrence_table(): void {
		$post_id = $this->create_event( array( 'post_name' => 'plain-event' ) );

		$this->enable_pretty_permalinks();

		$url = get_permalink( $post_id );

		$sql = $this->capture_sql(
			function () use ( $url ): void {
				$this->go_to( $url );
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert visiting a plain event permalink issues no query against the occurrence table.'
		);
	}

	/**
	 * Coverage for the `parse_request` handler's occurrence-segment branch: a
	 * request carrying a well-formed occurrence segment for a plain,
	 * non-recurring event.
	 *
	 * The occurrence rewrite rule is registered unconditionally in
	 * `Rewrite::add_rewrite_rules()`, so
	 * `resolve_post_id_from_query_vars()` resolves a post for *any*
	 * `gatherpress-event-date` post type, recurring or not, and the
	 * occurrence-segment branch then calls `Occurrences::get()`, which
	 * queries the occurrence table directly. That branch is deliberately
	 * unguarded by `Query::site_has_recurring_events()`: it only runs for a
	 * request already carrying an occurrence identifier, and its one
	 * primary-key read is what keeps a crafted or stale
	 * `/{event}/{slug}/{Ymd\THis}/` request 404ing instead of silently
	 * rendering the event at its anchor date on a site with zero recurring
	 * events.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 *
	 * @return void
	 */
	public function test_parse_request_occurrence_segment_branch_pays_one_read_and_404s(): void {
		$post_id = $this->create_event( array( 'post_name' => 'plain-event-occurrence-segment' ) );

		$this->enable_pretty_permalinks();

		$url = Rewrite::get_occurrence_url( $post_id, '20260903T180000' );

		$sql = $this->capture_sql(
			function () use ( $url ): void {
				$this->go_to( $url );
			}
		);

		$this->assertCount(
			1,
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert a well-formed occurrence URL for a non-recurring event pays exactly one'
				. ' primary-key occurrence read.'
		);
		$this->assertTrue(
			is_404(),
			'Failed to assert a crafted occurrence URL for a non-recurring event 404s rather than rendering'
				. ' the event at its anchor date.'
		);
	}

	/**
	 * Coverage for the `init` cron scheduler: `maybe_schedule_sweep()` neither
	 * schedules the sweep nor writes the `wp_options` row scheduling it would
	 * perform, when the site has no recurring events.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Projection_Cron::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_init_cron_scheduler_does_not_schedule_when_option_is_zero(): void {
		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert the sweep is not scheduled before the cron hook runs.'
		);

		Projection_Cron::get_instance()->maybe_schedule_sweep();

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert the sweep stays unscheduled on a site with no recurring events.'
		);
	}

	/**
	 * Coverage for the lazy-repair path: `maybe_lazy_repair()` short-circuits
	 * before setting a debounce transient or reading storage, when the site
	 * has no recurring events.
	 *
	 * Invoked directly with a fabricated ref carrying a non-null
	 * `recurrence_id`, since a genuinely non-recurring site's own reads never
	 * produce one. This isolates the guard itself rather than depending on
	 * it being reachable end-to-end, matching how `select_by_horizon()` always
	 * calls this method regardless of what its own read returned.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_lazy_repair
	 *
	 * @return void
	 */
	public function test_lazy_repair_short_circuits_when_option_is_zero(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$transient_key = sprintf( Occurrences::LAZY_REPAIR_TRANSIENT_FORMAT, $post_id );

		$this->assertFalse(
			get_transient( $transient_key ),
			'Failed to assert no debounce transient exists before the read runs.'
		);

		$ref = new Occurrence_Ref( $post_id, '20260903T180000', '2026-09-03 18:00:00' );

		$sql = $this->capture_sql(
			function () use ( $ref ): void {
				Utility::invoke_hidden_method(
					Occurrences::get_instance(),
					'maybe_lazy_repair',
					array( array( $ref ) )
				);
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert the lazy-repair guard issues no query against the occurrence table.'
		);
		$this->assertFalse(
			get_transient( $transient_key ),
			'Failed to assert the lazy-repair guard sets no debounce transient on a site with no recurring events.'
		);
	}

	/**
	 * Coverage for the options surface: rendering the upcoming-events list,
	 * visiting a plain event's permalink, and running the cron scheduler
	 * together write no option at all when the site has no recurring events.
	 *
	 * Diffs `wp_load_alloptions()` before and after, rather than asserting
	 * against one option in isolation.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Query::expand_event_clauses
	 * @covers \GatherPress\Core\Event\Recurrence\Rewrite::parse_request
	 * @covers \GatherPress\Core\Event\Recurrence\Projection_Cron::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_no_options_are_written_across_read_entry_points_when_option_is_zero(): void {
		$post_id = $this->create_event( array( 'post_name' => 'plain-event-options' ) );

		$this->enable_pretty_permalinks();

		wp_cache_delete( 'alloptions', 'options' );
		$before = wp_load_alloptions();

		new WP_Query( $this->upcoming_events_args() );
		$this->go_to( get_permalink( $post_id ) );
		Projection_Cron::get_instance()->maybe_schedule_sweep();

		wp_cache_delete( 'alloptions', 'options' );
		$after = wp_load_alloptions();

		$this->assertSame(
			array_keys( $before ),
			array_keys( $after ),
			'Failed to assert no option was added or removed by the read entry points.'
		);
		$this->assertSame(
			'0',
			$after[ Query::HAS_RECURRING_OPTION ] ?? '0',
			'Failed to assert the has-recurring-events flag is still 0 after the read entry points ran.'
		);
		$this->assertSame(
			$before,
			$after,
			'Failed to assert no option value changed either, not merely that the key set is unchanged'
				. ' (the "cron" option, for one, already exists on every site, so a spurious scheduled sweep'
				. ' would only ever change its value, never add a new key).'
		);
		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert the sweep is not scheduled by any of the read entry points.'
		);
	}

	/**
	 * Coverage for an upgrade on a site with zero recurring events: the
	 * `check_plugin_version()` self-heal recreates the occurrence table but
	 * writes zero rows to it and adds no unexpected option.
	 *
	 * Simulates the upgrade by dropping the occurrence table and storing a
	 * stale `gatherpress_version`, then calling the same public method
	 * `admin_init` calls in production.
	 *
	 * @covers \GatherPress\Core\Setup::check_plugin_version
	 *
	 * @return void
	 */
	public function test_upgrade_writes_no_data_and_creates_no_occurrence_rows(): void {
		global $wpdb;

		$this->create_event();

		$table = $this->occurrence_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- simulating a pre-upgrade site.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

		update_option( 'gatherpress_version', '0.0.1' );

		Setup::get_instance()->check_plugin_version();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- asserting the self-heal recreated the table.
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		$this->assertSame(
			$table,
			$table_exists,
			'Failed to assert the upgrade self-heal recreated the occurrence table.'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- asserting the recreated table is empty.
		$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$this->assertSame(
			'0',
			$row_count,
			'Failed to assert the recreated occurrence table holds zero rows.'
		);
		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION, '0' ),
			'Failed to assert the has-recurring-events flag is still 0 after the upgrade.'
		);
		$this->assertSame(
			defined( 'GATHERPRESS_VERSION' ) ? GATHERPRESS_VERSION : '0.0.1',
			get_option( 'gatherpress_version' ),
			'Failed to assert the stored plugin version was updated by the upgrade.'
		);
	}

	/**
	 * Coverage for the identity guarantee going forward: converting a
	 * plain event to a recurring one preserves its post ID, permalink, and
	 * RSVPs.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::project
	 *
	 * @return void
	 */
	public function test_enabling_recurrence_preserves_post_id_permalink_and_rsvps(): void {
		$post_id = $this->create_event( array( 'post_name' => 'convert-to-recurring' ) );

		$this->enable_pretty_permalinks();

		$permalink_before = get_permalink( $post_id );

		$user_id = $this->factory->user->create();
		$rsvp    = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );

		$rsvp_before = $rsvp->get( $user_id );

		$this->assertSame(
			'attending',
			$rsvp_before['status'],
			'Failed to assert the RSVP fixture saved as attending.'
		);

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::DAILY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert enabling recurrence actually projected occurrence rows.'
		);
		$this->assertSame(
			$post_id,
			(int) get_post( $post_id )->ID,
			'Failed to assert the post ID is unchanged after enabling recurrence.'
		);
		$this->assertSame(
			$permalink_before,
			get_permalink( $post_id ),
			'Failed to assert the permalink is unchanged after enabling recurrence.'
		);
		$this->assertSame(
			$rsvp_before,
			$rsvp->get( $user_id ),
			'Failed to assert the RSVP data is unchanged after enabling recurrence.'
		);
	}

	/**
	 * Coverage for the inverse of the test above: disabling recurrence on a
	 * series removes its occurrence rows and restores plain-event behavior.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::project
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_projectable
	 *
	 * @return void
	 */
	public function test_disabling_recurrence_removes_occurrences_and_restores_plain_behavior(): void {
		$post_id = $this->create_event();

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::DAILY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert the fixture series projected occurrence rows.'
		);
		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site reports recurring events.'
		);

		Utility::invoke_hidden_method( Meta::get_instance(), 'clear_mirrors', array( $post_id ) );
		Occurrences::get_instance()->project( $post_id );

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Failed to assert disabling recurrence removed every occurrence row.'
		);
		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the site no longer reports recurring events once the only series is disabled.'
		);

		$query = new WP_Query( $this->upcoming_events_args() );
		$ids   = wp_list_pluck( $query->posts, 'ID' );

		$this->assertSame(
			array( $post_id ),
			$ids,
			'Failed to assert the disabled series appears exactly once, like a plain event.'
		);
		$this->assertFalse(
			property_exists( $query->posts[0], 'gatherpress_recurrence_id' ),
			'Failed to assert the disabled series carries no occurrence identity, matching plain-event behavior.'
		);
	}

	/**
	 * Coverage for the no-recurring-events guard on `Occurrences::select_upcoming()`.
	 *
	 * `select_by_horizon()` is the shared body behind both `select_upcoming()`
	 * and `select_past()`, and this class's own docblocks name it as *the*
	 * occurrence-aware read API for GatherPress's own lists, so it is what a
	 * third-party consumer calls, and it is a public entry point. On a site whose
	 * `gatherpress_has_recurring_events` option is `'0'` it must not name the
	 * occurrence table in any SQL it issues, and it must still return the
	 * site's ordinary events.
	 *
	 * Measured with a `$wpdb->queries` capture across the real entry point
	 * rather than against the guard in isolation, because a "performs no
	 * writes" test that drives the body of the work and never the entry point
	 * passes while the guard is missing at the entry point.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_upcoming
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_upcoming_does_not_touch_occurrence_table_when_option_is_zero(): void {
		$post_id = $this->create_event();

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$refs = array();
		$sql  = $this->capture_sql(
			static function () use ( &$refs ): void {
				$refs = Occurrences::get_instance()->select_upcoming( 50 );
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert select_upcoming() issues no query naming the occurrence table on a site with no'
				. ' recurring events, and this method is the read API a third party calls.'
		);
		$this->assertSame(
			array( $post_id ),
			wp_list_pluck( $refs, 'post_id' ),
			'Failed to assert the anchor-only fallback still returns the site\'s ordinary events.'
		);
		$this->assertNull(
			$refs[0]->recurrence_id,
			'Failed to assert a non-recurring entry carries no occurrence identity.'
		);
	}

	/**
	 * Coverage for the same guard on `select_past()`, the descending arm.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_past
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_past_does_not_touch_occurrence_table_when_option_is_zero(): void {
		$this->create_event();

		$sql = $this->capture_sql(
			static function (): void {
				Occurrences::get_instance()->select_past( 50 );
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert select_past() issues no query naming the occurrence table on a site with no'
				. ' recurring events.'
		);
	}

	/**
	 * A running event is upcoming, never past, on the anchor-only fallback.
	 *
	 * The repository defines upcoming inclusively, matching
	 * `Event\Query::get_datetime_comparison_column()`: the buckets split at
	 * `datetime_end_gmt`, so an event that has started but not finished is
	 * still what an upcoming list should be showing, and it appears in
	 * exactly one bucket. The fallback arm must apply the same boundary as
	 * the joined arm, or the same running event flips buckets depending on
	 * whether some other event on the site happens to recur. The SQL capture
	 * pins the arm under test: no query may name the occurrence table, so
	 * this cannot be satisfied by the joined arm.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_upcoming
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_past
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_by_horizon
	 *
	 * @return void
	 */
	public function test_a_running_event_is_upcoming_not_past_when_option_is_zero(): void {
		$start   = $this->now()->modify( '-1 hour' );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$upcoming = array();
		$past     = array();
		$sql      = $this->capture_sql(
			static function () use ( &$upcoming, &$past ): void {
				$upcoming = Occurrences::get_instance()->select_upcoming( 50 );
				$past     = Occurrences::get_instance()->select_past( 50 );
			}
		);

		$this->assertSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to arrange the anchor-only fallback as the arm under test; the read named the occurrence table.'
		);
		$this->assertContains(
			$post_id,
			wp_list_pluck( $upcoming, 'post_id' ),
			'Failed to assert a running event still counts as upcoming on the anchor-only fallback.'
		);
		$this->assertNotContains(
			$post_id,
			wp_list_pluck( $past, 'post_id' ),
			'Failed to assert a running event is not classified as past on the anchor-only fallback.'
		);
	}

	/**
	 * Coverage for the guard's other side: once the site does have recurring
	 * events, `select_upcoming()` expands the series.
	 *
	 * Without this, the guard above could be satisfied by a method that
	 * never reads the occurrence table at all.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_upcoming
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::select_by_horizon
	 *
	 * @return void
	 */
	public function test_select_upcoming_expands_a_series_once_the_site_has_recurring_events(): void {
		$post_id = $this->create_event();

		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::DAILY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site reports recurring events.'
		);

		$refs = array();
		$sql  = $this->capture_sql(
			static function () use ( &$refs ): void {
				$refs = Occurrences::get_instance()->select_upcoming( 50 );
			}
		);

		$this->assertNotSame(
			array(),
			$this->queries_touching( $sql, $this->occurrence_table() ),
			'Failed to assert select_upcoming() does read the occurrence table once the site has recurring events.'
		);
		$this->assertCount(
			5,
			$refs,
			'Failed to assert select_upcoming() returns one entry per projected occurrence.'
		);
		$this->assertCount(
			5,
			array_unique( wp_list_pluck( $refs, 'recurrence_id' ) ),
			'Failed to assert every entry carries its own occurrence identity.'
		);
	}

	/**
	 * The date-bomb guard for this file, and it fails by name.
	 *
	 * Several tests here put the fixture into an `upcoming` bucket and then
	 * assert it is what the list shows. A fixture pinned to a literal calendar
	 * date passes until that date arrives and then fails with a message about
	 * query expansion, debugged by someone with no context. This asserts the
	 * one property that makes those tests honest: the shared anchor is always
	 * ahead of the clock.
	 *
	 * If you are reading this because it failed, someone re-pinned
	 * `anchor()` to a literal date. Make it relative to `now()` again.
	 *
	 * @return void
	 */
	public function test_the_fixture_series_is_never_in_the_past(): void {
		$anchor = $this->anchor();
		$now    = $this->now();

		$this->assertGreaterThan(
			$now->getTimestamp(),
			$anchor->getTimestamp(),
			'Failed to assert this file\'s shared fixture anchor is still ahead of the clock. Someone re-pinned'
				. ' anchor() to a literal date; make it relative to now() again.'
		);

		$post_id = $this->create_event();
		$event   = new Event( $post_id );

		$this->assertFalse(
			$event->has_event_past(),
			'Failed to assert an event built from the shared anchor is upcoming rather than past.'
		);
	}
}
