<?php
/**
 * Class handles unit tests for the recurrence occurrence table schema and the
 * `gatherpress_has_recurring_events` lifecycle flag on
 * GatherPress\Core\Event\Recurrence\Query.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Schema.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Query
 */
class Test_Schema extends Base {

	use Occurrence_Fixtures;

	/**
	 * Meta key holding the read-only frequency mirror the flag recompute reads.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const FREQUENCY_META_KEY = 'gatherpress_recurrence_frequency';

	/**
	 * Meta key holding the writable recurrence rule blob.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RULE_META_KEY = 'gatherpress_recurrence';

	/**
	 * Drop the occurrence table so a test can prove the create path from a
	 * table-less site.
	 *
	 * Deliberately not called from `setUp()`. DDL implicitly commits in MySQL
	 * and MariaDB, which ends the transaction `WP_UnitTestCase` opened for the
	 * test and leaks everything written before it into the rest of the run.
	 * Called as the first statement of a test, there is nothing to leak.
	 *
	 * @since 0.36.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function drop_occurrence_table(): void {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table creation.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Coverage for create_tables creating the occurrence table.
	 *
	 * @covers \GatherPress\Core\Setup::create_tables
	 *
	 * @return void
	 */
	public function test_create_tables_creates_occurrence_table(): void {
		global $wpdb;

		$this->drop_occurrence_table();

		Utility::invoke_hidden_method( Setup::get_instance(), 'create_tables' );

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table creation.
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );

		$this->assertSame(
			$table,
			$table_exists,
			'Failed to assert that the occurrence table was created.'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table structure.
		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table}" );

		$primary_columns = array();
		$key_names       = array();

		foreach ( $indexes as $index ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL's SHOW INDEX column names.
			$key_names[] = $index->Key_name;

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL's SHOW INDEX column names.
			if ( 'PRIMARY' === $index->Key_name ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MySQL's SHOW INDEX column names.
				$primary_columns[ (int) $index->Seq_in_index ] = $index->Column_name;
			}
		}

		ksort( $primary_columns );

		$this->assertSame(
			array( 'series_post_id', 'recurrence_id' ),
			array_values( $primary_columns ),
			'Failed to assert that the PRIMARY KEY covers (series_post_id, recurrence_id) in that order.'
		);

		$this->assertContains(
			'start_gmt',
			$key_names,
			'Failed to assert that the start_gmt key exists.'
		);

		$this->assertContains(
			'series_status_start',
			$key_names,
			'Failed to assert that the series_status_start key exists.'
		);
	}

	/**
	 * Coverage for a second dbDelta run being a genuine no-op.
	 *
	 * @covers \GatherPress\Core\Setup::create_tables
	 *
	 * @return void
	 */
	public function test_dbdelta_rerun_is_a_no_op(): void {
		global $wpdb;

		$this->drop_occurrence_table();

		$instance = Setup::get_instance();
		$table    = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		Utility::invoke_hidden_method( $instance, 'create_tables' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table structure.
		$create_sql_before = $wpdb->get_row( "SHOW CREATE TABLE {$table}", ARRAY_N )[1];

		$query_count_before = count( $wpdb->queries );

		Utility::invoke_hidden_method( $instance, 'create_tables' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table structure.
		$create_sql_after = $wpdb->get_row( "SHOW CREATE TABLE {$table}", ARRAY_N )[1];

		$this->assertSame(
			$create_sql_before,
			$create_sql_after,
			'Failed to assert that SHOW CREATE TABLE is byte-identical after a second dbDelta run.'
		);

		$queries_since = array_slice( $wpdb->queries, $query_count_before );
		$altered       = array_filter(
			$queries_since,
			static function ( $query ) {
				return str_contains( strtoupper( $query[0] ), 'ALTER TABLE' )
					&& str_contains( $query[0], 'gatherpress_event_occurrences' );
			}
		);

		$this->assertEmpty(
			$altered,
			'Failed to assert that the second dbDelta run issued no ALTER statement against the occurrence table.'
		);
	}

	/**
	 * Coverage for the shared fixture's postconditions.
	 *
	 * Every downstream recurrence task depends on `create_recurring_event()`;
	 * asserting its postconditions here means a broken fixture is caught directly
	 * rather than at the integration checkpoint.
	 *
	 * @return void
	 */
	public function test_fixture_creates_recurring_event_with_expected_postconditions(): void {
		global $wpdb;

		Utility::invoke_hidden_method( Setup::get_instance(), 'create_tables' );

		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 2,
				'weekdays'  => array( 2, 4 ),
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		$this->assertInstanceOf(
			\WP_Post::class,
			get_post( $post_id ),
			'Failed to assert that the fixture post exists.'
		);

		$this->assertSame(
			'2026-09-03 22:00:00',
			get_post_meta( $post_id, 'gatherpress_datetime_start_gmt', true ),
			'Failed to assert that the gatherpress_datetime_start_gmt mirror matches the anchor.'
		);

		$events_table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Required for testing table contents.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$events_table} WHERE post_id = %d", $post_id ) );

		$this->assertNotNull(
			$row,
			'Failed to assert that a wp_gatherpress_events row exists for the fixture post.'
		);
	}

	/**
	 * Coverage for site_has_recurring_events returning false when the option is unset.
	 *
	 * @covers ::site_has_recurring_events
	 *
	 * @return void
	 */
	public function test_site_has_recurring_events_defaults_false(): void {
		delete_option( Query::HAS_RECURRING_OPTION );

		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert that site_has_recurring_events defaults false with no option set.'
		);
	}

	/**
	 * Coverage for site_has_recurring_events returning true when the option is '1'.
	 *
	 * @covers ::site_has_recurring_events
	 *
	 * @return void
	 */
	public function test_site_has_recurring_events_reads_true_option(): void {
		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert that site_has_recurring_events reads a true option value.'
		);
	}

	/**
	 * Coverage for refresh_has_recurring_events setting the flag true when a
	 * post carries the frequency mirror meta.
	 *
	 * @covers ::refresh_has_recurring_events
	 *
	 * @return void
	 */
	public function test_refresh_has_recurring_events_sets_flag_true(): void {
		$post_id = $this->factory->post->create();

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );

		Query::refresh_has_recurring_events();

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that refresh_has_recurring_events set the flag to 1.'
		);
	}

	/**
	 * Coverage for refresh_has_recurring_events setting the flag false when no
	 * post carries the frequency mirror meta.
	 *
	 * @covers ::refresh_has_recurring_events
	 *
	 * @return void
	 */
	public function test_refresh_has_recurring_events_sets_flag_false(): void {
		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		Query::refresh_has_recurring_events();

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that refresh_has_recurring_events set the flag to 0 with no recurring meta present.'
		);
	}

	/**
	 * Coverage for maybe_refresh_has_recurring_events_for_meta ignoring unrelated meta keys.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_meta
	 *
	 * @return void
	 */
	public function test_unrelated_meta_key_does_not_refresh_flag(): void {
		$post_id = $this->factory->post->create();

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		add_post_meta( $post_id, 'gatherpress_unrelated_meta_key', 'value' );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that an unrelated meta key write does not trigger a refresh.'
		);
	}

	/**
	 * Coverage for added_post_meta refreshing the flag in production write order.
	 *
	 * Production writes the canonical rule blob first, then derives its mirrors
	 * (including the frequency mirror the recompute reads) synchronously
	 * afterward, in the same save. Each write fires its own `added_post_meta`
	 * hook. If only the canonical-key write triggered a recompute, it would
	 * observe zero mirrors at that instant and write a stale `'0'`. The flag only
	 * ends up correct because the mirror write's own hook fires a second,
	 * corrective recompute.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_meta
	 *
	 * @return void
	 */
	public function test_added_post_meta_in_production_order_refreshes_flag(): void {
		$post_id = $this->factory->post->create();
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		add_post_meta( $post_id, self::RULE_META_KEY, wp_json_encode( array( 'frequency' => 'weekly' ) ) );
		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that adding the recurrence meta in production order refreshes the flag.'
		);
	}

	/**
	 * Coverage for added_post_meta on the canonical key alone refreshing the
	 * flag when frequency mirror meta already exists elsewhere on the site.
	 *
	 * The recompute query is site-wide, not scoped to one post, so a
	 * canonical-key write on a post with no mirror of its own still corrects
	 * the flag as long as some post on the site already carries the mirror.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_meta
	 *
	 * @return void
	 */
	public function test_added_post_meta_on_canonical_key_alone_refreshes_flag_when_mirror_exists_elsewhere(): void {
		$existing_recurring_post_id = $this->factory->post->create();
		add_post_meta( $existing_recurring_post_id, self::FREQUENCY_META_KEY, 'weekly' );

		$post_id = $this->factory->post->create();
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		add_post_meta( $post_id, self::RULE_META_KEY, wp_json_encode( array( 'frequency' => 'weekly' ) ) );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that a canonical-key-only write refreshes the flag when frequency meta exists elsewhere.'
		);
	}

	/**
	 * Coverage for updated_post_meta refreshing the flag in production write order.
	 *
	 * Same race as `test_added_post_meta_in_production_order_refreshes_flag()`,
	 * on the update path instead of the add path.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_meta
	 *
	 * @return void
	 */
	public function test_updated_post_meta_in_production_order_refreshes_flag(): void {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, self::RULE_META_KEY, wp_json_encode( array( 'frequency' => 'weekly' ) ) );
		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		update_post_meta( $post_id, self::RULE_META_KEY, wp_json_encode( array( 'frequency' => 'daily' ) ) );
		update_post_meta( $post_id, self::FREQUENCY_META_KEY, 'daily' );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that updating the recurrence meta in production order refreshes the flag.'
		);
	}

	/**
	 * Coverage for a direct delete_post_meta() call on the canonical key, and
	 * for the all-clear case where removing the last recurring event's meta
	 * brings the flag back to '0'.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_meta
	 * @covers ::refresh_has_recurring_events
	 *
	 * @return void
	 */
	public function test_direct_delete_post_meta_is_the_all_clear_case(): void {
		$post_id = $this->factory->post->create();

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		add_post_meta( $post_id, self::RULE_META_KEY, wp_json_encode( array( 'frequency' => 'weekly' ) ) );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert the flag is true once the recurring event exists.'
		);

		// Remove the frequency mirror first, so the canonical-key delete below
		// is genuinely the last recurring event being cleared.
		delete_post_meta( $post_id, self::FREQUENCY_META_KEY );
		delete_post_meta( $post_id, self::RULE_META_KEY );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that deleting the last recurring event\'s meta is the all-clear case.'
		);
	}

	/**
	 * Coverage for trashing a supported post refreshing the flag via transition_post_status.
	 *
	 * WordPress retains post meta on trash, so the frequency mirror is still in
	 * `wp_postmeta` when the recompute runs. The recompute has to look at the
	 * post's own status to see that the last recurring event is gone. The
	 * fixture deliberately leaves every meta row in place and drives nothing
	 * but the real `wp_trash_post()` transition, because deleting the mirror
	 * by hand first would let a meta-only recompute pass this test without
	 * ever exercising the lifecycle its name claims.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_transition
	 * @covers ::refresh_has_recurring_events
	 *
	 * @return void
	 */
	public function test_trash_refreshes_flag(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		Query::refresh_has_recurring_events();

		$this->assertSame( '1', get_option( Query::HAS_RECURRING_OPTION ) );

		wp_trash_post( $post_id );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that trashing the last recurring post refreshed the flag to 0.'
		);
	}

	/**
	 * Coverage for untrashing a supported post refreshing the flag via transition_post_status.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_transition
	 *
	 * @return void
	 */
	public function test_untrash_refreshes_flag(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );

		wp_trash_post( $post_id );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		wp_untrash_post( $post_id );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that untrashing a recurring post refreshed the flag to 1.'
		);
	}

	/**
	 * Coverage for transition_post_status being scoped to gatherpress-event-date
	 * post types: an unsupported post type's status change must not query.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_transition
	 *
	 * @return void
	 */
	public function test_trash_of_unsupported_post_type_does_not_refresh_flag(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		wp_trash_post( $post_id );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that trashing an unsupported post type does not refresh the flag.'
		);
	}

	/**
	 * Coverage for a hard delete of a supported post refreshing the flag via deleted_post.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_deleted_post
	 *
	 * @return void
	 */
	public function test_hard_delete_refreshes_flag(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		Query::refresh_has_recurring_events();

		$this->assertSame( '1', get_option( Query::HAS_RECURRING_OPTION ) );

		wp_delete_post( $post_id, true );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that hard-deleting the last recurring post refreshed the flag to 0.'
		);
	}

	/**
	 * Coverage for deleted_post being scoped to gatherpress-event-date post
	 * types: hard-deleting an unsupported post type must not query.
	 *
	 * @covers ::maybe_refresh_has_recurring_events_for_deleted_post
	 *
	 * @return void
	 */
	public function test_hard_delete_of_unsupported_post_type_does_not_refresh_flag(): void {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		wp_delete_post( $post_id, true );

		$this->assertSame(
			'0',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that hard-deleting an unsupported post type does not refresh the flag.'
		);
	}

	/**
	 * Coverage for import_end refreshing the flag.
	 *
	 * @covers ::refresh_has_recurring_events
	 *
	 * @return void
	 */
	public function test_import_end_refreshes_flag(): void {
		$post_id = $this->factory->post->create();

		add_post_meta( $post_id, self::FREQUENCY_META_KEY, 'weekly' );
		update_option( Query::HAS_RECURRING_OPTION, '0', true );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'import_end' );

		$this->assertSame(
			'1',
			get_option( Query::HAS_RECURRING_OPTION ),
			'Failed to assert that import_end refreshed the flag to 1.'
		);
	}

	/**
	 * Coverage for setup_hooks registering the lifecycle hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Query::get_instance();

		$hooks = array(
			array(
				'type'     => 'action',
				'name'     => 'transition_post_status',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_has_recurring_events_for_transition' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_has_recurring_events_for_deleted_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_has_recurring_events_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_has_recurring_events_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_refresh_has_recurring_events_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'import_end',
				'priority' => 10,
				'callback' => array( $instance, 'refresh_has_recurring_events' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_occurrences_changed',
				'priority' => 10,
				'callback' => array( $instance, 'invalidate_post_query_cache' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 11,
				'callback' => array( $instance, 'expand_event_clauses' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 11,
				'callback' => array( $instance, 'adjust_admin_occurrence_sorting' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_posts',
				'priority' => 10,
				'callback' => array( $instance, 'attach_occurrences' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}
}
