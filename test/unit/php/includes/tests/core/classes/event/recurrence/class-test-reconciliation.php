<?php
/**
 * Class handles integration tests for recurrence-rule reconciliation across
 * GatherPress\Core\Event\Recurrence\Meta, Occurrences, and Query.
 *
 * A recurrence rule can be replaced or removed by writers that never fire
 * `wp_after_insert_post` after the blob lands: WP-CLI's `wp post meta update`,
 * an importer updating an existing post, a duplication plugin, or any direct
 * `update_post_meta()` call. These tests pin the invariant that the canonical
 * blob, all ten derived mirrors, the occurrence rows, and the site-wide flag
 * agree once the request completes, whichever writer performed the write.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Tests\Base;
use WP_REST_Request;

/**
 * Class Test_Reconciliation.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Meta
 */
class Test_Reconciliation extends Base {

	use Occurrence_Fixtures;

	/**
	 * Rule A: the reference bi-weekly Tuesday/Thursday rule, five occurrences.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const RULE_A = array(
		'frequency' => 'weekly',
		'interval'  => 2,
		'weekdays'  => array( 2, 4 ),
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Rule B: a daily rule with three occurrences, disjoint from rule A's set
	 * except for the shared anchor occurrence.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const RULE_B = array(
		'frequency' => 'daily',
		'interval'  => 1,
		'end_type'  => 'count',
		'count'     => 3,
	);

	/**
	 * The ten mirror values rule A must derive, keyed by meta key.
	 *
	 * @since 0.36.0
	 * @var array<string, string>
	 */
	const MIRRORS_A = array(
		'gatherpress_recurrence_frequency'       => 'weekly',
		'gatherpress_recurrence_interval'        => '2',
		'gatherpress_recurrence_byday'           => 'TU,TH',
		'gatherpress_recurrence_monthly_mode'    => '',
		'gatherpress_recurrence_monthly_day'     => '0',
		'gatherpress_recurrence_monthly_ordinal' => '0',
		'gatherpress_recurrence_monthly_weekday' => '0',
		'gatherpress_recurrence_end_type'        => 'count',
		'gatherpress_recurrence_until'           => '',
		'gatherpress_recurrence_count'           => '5',
	);

	/**
	 * The ten mirror values rule B must derive, keyed by meta key.
	 *
	 * @since 0.36.0
	 * @var array<string, string>
	 */
	const MIRRORS_B = array(
		'gatherpress_recurrence_frequency'       => 'daily',
		'gatherpress_recurrence_interval'        => '1',
		'gatherpress_recurrence_byday'           => '',
		'gatherpress_recurrence_monthly_mode'    => '',
		'gatherpress_recurrence_monthly_day'     => '0',
		'gatherpress_recurrence_monthly_ordinal' => '0',
		'gatherpress_recurrence_monthly_weekday' => '0',
		'gatherpress_recurrence_end_type'        => 'count',
		'gatherpress_recurrence_until'           => '',
		'gatherpress_recurrence_count'           => '3',
	);

	/**
	 * The occurrence identifiers rule B projects from the reference anchor.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const RULE_B_RECURRENCE_IDS = array(
		'20260903T180000',
		'20260904T180000',
		'20260905T180000',
	);

	/**
	 * Start every test from an empty occurrence table, independent of
	 * execution order relative to Test_Schema, and re-register the recurrence
	 * meta for REST.
	 *
	 * The re-registration is a test-environment need only: the WP test
	 * framework clears `$wp_meta_keys` between tests, while production
	 * registers once per request on `registered_post_type`. Without it, the
	 * REST meta writer silently skips the unregistered key from the second
	 * test in a process onward and every REST-driven assertion tests nothing.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Meta::get_instance()->register( Event::POST_TYPE );
	}

	/**
	 * Create a recurring event whose rule A state has fully settled through
	 * the production save path, shutdown pass included.
	 *
	 * @since 0.36.0
	 *
	 * @return int The post ID.
	 */
	protected function create_settled_series(): int {
		$post_id = $this->create_recurring_event( self::RULE_A );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_settled_state(
			$post_id,
			wp_json_encode( self::RULE_A ),
			self::MIRRORS_A,
			wp_list_pluck( $this->expected_weekly_set(), 'recurrence_id' ),
			'fixture setup'
		);

		return $post_id;
	}

	/**
	 * Assert the canonical blob, all ten mirrors, the occurrence rows, and the
	 * site flag describe one consistent state.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $post_id        Series post ID.
	 * @param string   $expected_blob  Expected canonical blob value.
	 * @param array    $mirrors        Expected value per mirror meta key.
	 * @param string[] $recurrence_ids Expected recurrence identifiers, in order.
	 * @param string   $context        Assertion message prefix.
	 *
	 * @return void
	 */
	protected function assert_settled_state(
		int $post_id,
		string $expected_blob,
		array $mirrors,
		array $recurrence_ids,
		string $context
	): void {
		$this->assertSame(
			$expected_blob,
			(string) get_post_meta( $post_id, Meta::META_KEY, true ),
			"{$context}: failed to assert the canonical blob value."
		);

		foreach ( $mirrors as $meta_key => $expected ) {
			$this->assertSame(
				$expected,
				(string) get_post_meta( $post_id, $meta_key, true ),
				"{$context}: failed to assert the {$meta_key} mirror agrees with the blob."
			);
		}

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertSame(
			$recurrence_ids,
			array_values( wp_list_pluck( $rows, 'recurrence_id' ) ),
			"{$context}: failed to assert the occurrence rows agree with the blob."
		);

		$this->assertSame(
			array() !== $recurrence_ids,
			Query::site_has_recurring_events(),
			"{$context}: failed to assert the site flag agrees with the stored state."
		);
	}

	/**
	 * Assert every mirror is cleared, no occurrence rows remain, and the site
	 * flag reports no recurring events.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id Series post ID.
	 * @param string $context Assertion message prefix.
	 *
	 * @return void
	 */
	protected function assert_cleared_state( int $post_id, string $context ): void {
		foreach ( Meta::DERIVED_META_KEYS as $meta_key ) {
			$this->assertSame(
				'',
				(string) get_post_meta( $post_id, $meta_key, true ),
				"{$context}: failed to assert the {$meta_key} mirror was cleared."
			);
		}

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			"{$context}: failed to assert the occurrence rows were removed."
		);

		$this->assertFalse(
			Query::site_has_recurring_events(),
			"{$context}: failed to assert the site flag was recomputed to off."
		);
	}

	/**
	 * Dispatch a block-editor-shaped REST save writing the recurrence blob.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id Post to update.
	 * @param string $blob    Raw blob value to store, '' to remove the rule.
	 *
	 * @return void
	 */
	protected function rest_save_blob( int $post_id, string $blob ): void {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'PUT', sprintf( '/wp/v2/gatherpress_events/%d', $post_id ) );
		$request->add_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'meta' => array( Meta::META_KEY => $blob ),
				)
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status(), 'Failed to assert the REST save succeeded.' );
	}

	/**
	 * Replacing rule A with rule B through a real REST save, the way the block
	 * editor writes meta, leaves blob, mirrors, rows, and flag agreeing on
	 * rule B once the request and its shutdown pass complete.
	 *
	 * @covers ::set_recurrence
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_project
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_rest_save_replacing_a_rule_reconciles_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		$this->rest_save_blob( $post_id, wp_json_encode( self::RULE_B ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_settled_state(
			$post_id,
			wp_json_encode( self::RULE_B ),
			self::MIRRORS_B,
			self::RULE_B_RECURRENCE_IDS,
			'REST replace'
		);
	}

	/**
	 * Removing a rule through a real REST save clears the mirrors, deletes the
	 * occurrence rows, and turns the site flag off.
	 *
	 * @covers ::set_recurrence
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_project
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_rest_save_removing_a_rule_clears_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		$this->rest_save_blob( $post_id, '' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_cleared_state( $post_id, 'REST removal' );
	}

	/**
	 * Replacing a rule with an invalid one through a real REST save clears the
	 * mirrors and rows rather than leaving the previous rule's derived state.
	 *
	 * @covers ::set_recurrence
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_project
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_rest_save_with_an_invalid_replacement_clears_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		// Structurally invalid: weekly with no weekday selection, the shape an
		// editor round trip that clears the weekday list would produce.
		$this->rest_save_blob( $post_id, wp_json_encode( array( 'frequency' => 'weekly' ) ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_cleared_state( $post_id, 'REST invalid replacement' );
	}

	/**
	 * A rule replaced by a bare `update_post_meta()` call, with no post save in
	 * the request at all (WP-CLI's `wp post meta update`, an importer touching
	 * an existing post), is reconciled by the shutdown pass.
	 *
	 * @covers ::maybe_queue_reconciliation
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_queue_projection
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_direct_meta_update_replacing_a_rule_reconciles_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		update_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::RULE_B ) );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_settled_state(
			$post_id,
			wp_json_encode( self::RULE_B ),
			self::MIRRORS_B,
			self::RULE_B_RECURRENCE_IDS,
			'direct meta replace'
		);
	}

	/**
	 * A rule removed by a bare `delete_post_meta()` call is reconciled by the
	 * shutdown pass: mirrors cleared, rows deleted, flag off.
	 *
	 * @covers ::maybe_queue_reconciliation
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_queue_projection
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_direct_meta_delete_removing_a_rule_clears_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		delete_post_meta( $post_id, Meta::META_KEY );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_cleared_state( $post_id, 'direct meta removal' );
	}

	/**
	 * A rule overwritten with an invalid blob by a bare `update_post_meta()`
	 * call is reconciled by the shutdown pass to the cleared state.
	 *
	 * @covers ::maybe_queue_reconciliation
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_queue_projection
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_direct_meta_update_with_an_invalid_blob_clears_every_derived_state(): void {
		$post_id = $this->create_settled_series();

		update_post_meta( $post_id, Meta::META_KEY, 'not json at all' );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assert_cleared_state( $post_id, 'direct invalid overwrite' );
	}

	/**
	 * The ordinary save path projects exactly once: the classic-editor shape
	 * (blob written mid-request, then `wp_after_insert_post`) must not project
	 * a second time at shutdown.
	 *
	 * Measured over `$wpdb->queries` rather than asserted structurally, so a
	 * regression that queues a redundant shutdown projection turns this red by
	 * count, not by implementation detail.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_project
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_classic_save_path_projects_exactly_once(): void {
		global $wpdb;

		$post_id = $this->create_recurring_event( self::RULE_A );

		$occurrence_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$writes_baseline  = $this->count_occurrence_writes( $occurrence_table );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );

		$writes_after_save = $this->count_occurrence_writes( $occurrence_table );

		$this->assertSame(
			1,
			$writes_after_save - $writes_baseline,
			'Failed to assert the immediate save pass issued exactly one occurrence insert.'
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$this->assertSame(
			$writes_after_save,
			$this->count_occurrence_writes( $occurrence_table ),
			'Failed to assert the shutdown pass issued no second projection for an already-projected save.'
		);

		$this->assert_settled_state(
			$post_id,
			wp_json_encode( self::RULE_A ),
			self::MIRRORS_A,
			wp_list_pluck( $this->expected_weekly_set(), 'recurrence_id' ),
			'classic save'
		);
	}

	/**
	 * An unrelated meta write on a supported post queues no reconciliation and
	 * the shutdown pass issues no occurrence-table statements for it.
	 *
	 * This is the REQ-16 guard for the reconciliation entry point itself: a
	 * site whose saves never touch the recurrence blob must not gain
	 * occurrence-table work from the meta watchers.
	 *
	 * @covers ::maybe_queue_reconciliation
	 * @covers ::resolve_pending_recurrence
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_queue_projection
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::resolve_pending_projection
	 *
	 * @return void
	 */
	public function test_unrelated_meta_write_queues_no_reconciliation(): void {
		global $wpdb;

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, 'unrelated_meta_key', 'value' );

		$occurrence_table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$queries_before   = count( (array) $wpdb->queries );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'shutdown' );

		$shutdown_queries = array_slice( (array) $wpdb->queries, $queries_before );

		foreach ( $shutdown_queries as $query ) {
			$this->assertStringNotContainsString(
				$occurrence_table,
				(string) $query[0],
				'Failed to assert the shutdown pass never touches the occurrence table for an unrelated meta write.'
			);
		}
	}

	/**
	 * A recurrence-blob write on a post type without `gatherpress-event-date`
	 * support queues nothing for either watcher.
	 *
	 * @covers ::maybe_queue_reconciliation
	 * @covers \GatherPress\Core\Event\Recurrence\Occurrences::maybe_queue_projection
	 *
	 * @return void
	 */
	public function test_blob_write_on_unsupported_post_type_queues_no_reconciliation(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		update_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::RULE_A ) );

		$this->assertArrayNotHasKey(
			$post_id,
			\PMC\Unit_Test\Utility::get_hidden_property( Meta::get_instance(), 'pending_recurrence' ),
			'Failed to assert no recurrence reconciliation was queued for an unsupported post type.'
		);
		$this->assertArrayNotHasKey(
			$post_id,
			\PMC\Unit_Test\Utility::get_hidden_property( Occurrences::get_instance(), 'pending_projection' ),
			'Failed to assert no projection was queued for an unsupported post type.'
		);
	}

	/**
	 * Count occurrence-table insert statements recorded by `SAVEQUERIES`.
	 *
	 * @since 0.36.0
	 *
	 * @param string $occurrence_table Occurrence table name.
	 *
	 * @return int Number of `INSERT` statements against the occurrence table.
	 */
	protected function count_occurrence_writes( string $occurrence_table ): int {
		global $wpdb;

		$count = 0;

		foreach ( (array) $wpdb->queries as $query ) {
			$sql = (string) $query[0];

			if ( str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $occurrence_table ) ) {
				++$count;
			}
		}

		return $count;
	}
}
