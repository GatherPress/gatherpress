<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Projection_Cron.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Projection_Cron;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Projection_Cron.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Projection_Cron
 */
class Test_Projection_Cron extends Base {

	use Occurrence_Fixtures;

	/**
	 * Start every test from an empty occurrence table and with the sweep
	 * unscheduled, independent of execution order.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		wp_clear_scheduled_hook( Projection_Cron::SWEEP_ACTION );
	}

	/**
	 * Leave no scheduled sweep behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_clear_scheduled_hook( Projection_Cron::SWEEP_ACTION );

		parent::tearDown();
	}

	/**
	 * Create and project a recurring event anchored relative to "now".
	 *
	 * Duplicated from `Test_Occurrences` rather than added to
	 * `Occurrence_Fixtures`, because the shared trait deliberately carries
	 * only the pinned-date reference fixture. A relative-to-now helper stays
	 * local to the suites that need one.
	 *
	 * @since 0.36.0
	 *
	 * @param array             $rule     Recurrence rule values.
	 * @param DateTimeImmutable $start    Anchor start, in `$timezone`.
	 * @param DateTimeImmutable $end      Anchor end, in `$timezone`.
	 * @param string            $timezone Named tz-database identifier for the series.
	 *
	 * @return int The projected post ID.
	 */
	protected function create_relative_recurring_event(
		array $rule,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		string $timezone = 'America/New_York'
	): int {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => $timezone,
				)
			)
		);
		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $rule ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return $post_id;
	}

	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Projection_Cron::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_schedule_sweep' ),
			),
			array(
				'type'     => 'action',
				'name'     => Projection_Cron::SWEEP_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'run_sweep' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for `maybe_schedule_sweep()`'s `site_has_recurring_events()`
	 * guard: the "a site with no recurring events pays nothing"
	 * guarantee covers the scheduler itself, not only the sweep callback.
	 * Run on `init`, an unguarded scheduler would cost every GatherPress
	 * install a permanent hourly cron event and the `wp_options` write
	 * `wp_schedule_event()` performs. The overwhelming majority of those
	 * installs never publish a recurring event. Measured via a
	 * `$wpdb->queries` capture, not the schedule alone, since a version
	 * that scheduled without writing an option would still pass a
	 * schedule-only assertion.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_does_nothing_on_a_site_with_no_recurring_events(): void {
		global $wpdb;

		update_option( Query::HAS_RECURRING_OPTION, '0' );
		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site has no recurring events.'
		);

		$query_count_before = count( $wpdb->queries );

		Projection_Cron::get_instance()->maybe_schedule_sweep();

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that maybe_schedule_sweep does not schedule on a site with no recurring events.'
		);

		$queries_since = array_slice( $wpdb->queries, $query_count_before );
		$option_writes = array_values(
			array_filter(
				$queries_since,
				static function ( $query ) {
					return (bool) preg_match( '/^\s*(INSERT|UPDATE|REPLACE)\s+.*`?options`?/i', $query[0] );
				}
			)
		);

		$this->assertSame(
			array(),
			$option_writes,
			'Failed to assert that maybe_schedule_sweep performs no options-table write on a site'
				. ' with no recurring events.'
		);
	}

	/**
	 * Coverage for `maybe_schedule_sweep()` scheduling the recurring sweep
	 * on a site that does have recurring events, when nothing is scheduled
	 * yet. This is the companion to the no-recurring-events guard above.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_schedules_when_site_has_recurring_events(): void {
		update_option( Query::HAS_RECURRING_OPTION, '1' );

		Projection_Cron::get_instance()->maybe_schedule_sweep();

		$this->assertNotFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that maybe_schedule_sweep schedules the recurring sweep on a site with recurring events.'
		);
	}

	/**
	 * Coverage for `maybe_schedule_sweep()`'s dedup branch: an already
	 * scheduled sweep is never duplicated.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_does_not_duplicate_an_existing_schedule(): void {
		update_option( Query::HAS_RECURRING_OPTION, '1' );

		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );
		$existing = wp_next_scheduled( Projection_Cron::SWEEP_ACTION );

		Projection_Cron::get_instance()->maybe_schedule_sweep();

		$this->assertSame(
			$existing,
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that maybe_schedule_sweep left an existing schedule untouched.'
		);
	}

	/**
	 * Coverage for `maybe_schedule_sweep()`'s filter short-circuit. That is the
	 * seam a companion plugin uses to route the sweep through Action
	 * Scheduler instead of WP-Cron, mirroring
	 * `gatherpress_async_geocode_pre_enqueue_job`.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_is_short_circuited_by_the_pre_schedule_filter(): void {
		update_option( Query::HAS_RECURRING_OPTION, '1' );

		$filter = static fn() => 'action-scheduler-job-id';
		add_filter( 'gatherpress_recurrence_top_up_pre_schedule_sweep', $filter );

		Projection_Cron::get_instance()->maybe_schedule_sweep();

		remove_filter( 'gatherpress_recurrence_top_up_pre_schedule_sweep', $filter );

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that a non-null filter return suppresses the default WP-Cron scheduling.'
		);
	}

	// Coverage for run_sweep()'s early-return branch lives in
	// Test_Occurrences::test_scheduled_job_performs_no_writes_on_a_site_with_no_recurring_events(),
	// which fires this same real cron hook and asserts against a $wpdb->queries
	// capture. A prior version of this test asserted only that
	// site_has_recurring_events() was still false after the sweep. That is a
	// tautology, since nothing in run_sweep() can write that option regardless
	// of whether the guard runs, and removing the guard left it green.

	/**
	 * Coverage for `run_sweep()`'s top-up branch, driven through the real
	 * cron hook.
	 *
	 * @covers ::run_sweep
	 *
	 * @return void
	 */
	public function test_run_sweep_tops_up_when_site_has_recurring_events(): void {
		$timezone = new DateTimeZone( 'America/New_York' );
		$anchor   = ( new DateTimeImmutable( 'now', $timezone ) )->modify( '-1 week' );

		$short_horizon = static fn() => 1;
		add_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		$post_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( (int) $anchor->format( 'w' ) ),
				'end_type'  => 'never',
			),
			$anchor,
			$anchor->modify( '+2 hours' ),
			'America/New_York'
		);

		remove_filter( 'gatherpress_recurrence_horizon_months', $short_horizon );

		Query::refresh_has_recurring_events();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- SWEEP_ACTION is a gatherpress_-prefixed class constant.
		do_action( Projection_Cron::SWEEP_ACTION );

		$far_future = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )
			->modify( '+6 months' )
			->format( 'Y-m-d H:i:s' );
		$rows       = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertNotEmpty(
			array_filter( $rows, static fn( $row ) => $row['datetime_start_gmt'] > $far_future ),
			'Failed to assert that run_sweep topped up a stale series via the real cron hook.'
		);
	}

	/**
	 * Coverage for the horizon top-up: deactivation unschedules the sweep so a cron event
	 * never survives plugin deactivation.
	 *
	 * @covers ::deactivate
	 *
	 * @return void
	 */
	public function test_deactivation_unschedules_the_sweep(): void {
		$instance = Projection_Cron::get_instance();

		// Scheduled directly rather than through maybe_schedule_sweep().
		// This test is about deactivate(), not about the scheduling guard,
		// which has its own coverage above.
		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );

		$this->assertNotFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that the sweep was scheduled before deactivation.'
		);

		$instance->deactivate();

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that deactivate() unschedules the sweep.'
		);
	}

	/**
	 * Coverage for `deactivate()`'s no-op branch: deactivating twice, or
	 * deactivating when nothing was ever scheduled, does not error.
	 *
	 * @covers ::deactivate
	 *
	 * @return void
	 */
	public function test_deactivation_is_a_no_op_when_nothing_is_scheduled(): void {
		$instance = Projection_Cron::get_instance();

		$instance->deactivate();

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that deactivate() is a harmless no-op when nothing was scheduled.'
		);
	}

	/**
	 * Coverage for `deactivate()` clearing every scheduled sweep timestamp,
	 * not only the earliest one `wp_next_scheduled()` can see.
	 *
	 * @covers ::deactivate
	 *
	 * @return void
	 */
	public function test_deactivation_clears_every_sweep_timestamp(): void {
		$instance = Projection_Cron::get_instance();

		// Two entries an hour apart, past core's ten-minute duplicate window,
		// the shape a drifted schedule or a misbehaving integration leaves
		// behind. Unscheduling only wp_next_scheduled()'s earliest timestamp
		// would leave the second entry dispatching after deactivation.
		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );
		wp_schedule_event(
			time() + ( 2 * HOUR_IN_SECONDS ),
			Projection_Cron::SWEEP_RECURRENCE,
			Projection_Cron::SWEEP_ACTION
		);

		$this->assertSame(
			2,
			$this->count_scheduled_sweeps(),
			'Failed to assert the fixture scheduled two distinct sweep timestamps.'
		);

		$instance->deactivate( false );

		$this->assertSame(
			0,
			$this->count_scheduled_sweeps(),
			'Failed to assert that deactivation cleared every scheduled sweep timestamp.'
		);
	}

	/**
	 * Coverage for network-wide deactivation clearing the sweep on every
	 * site, not only the one the network admin request runs on.
	 *
	 * Each subsite stores its own cron option, so a deactivation that never
	 * iterates sites orphans every child site's hourly sweep for as long as
	 * the plugin stays inactive. Unrelated cron hooks must survive.
	 *
	 * @group multisite
	 * @covers ::deactivate
	 *
	 * @return void
	 */
	public function test_network_deactivation_clears_the_sweep_on_every_site(): void {
		$instance  = Projection_Cron::get_instance();
		$site_id_2 = $this->factory()->blog->create();

		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gatherpress_unrelated_probe' );

		switch_to_blog( $site_id_2 );
		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );
		restore_current_blog();

		$instance->deactivate( true );

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that network deactivation cleared the sweep on the main site.'
		);
		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_unrelated_probe' ),
			'Failed to assert that an unrelated cron hook survived network deactivation.'
		);

		switch_to_blog( $site_id_2 );
		$sweep_on_child = wp_next_scheduled( Projection_Cron::SWEEP_ACTION );
		wp_clear_scheduled_hook( Projection_Cron::SWEEP_ACTION );
		restore_current_blog();

		wp_clear_scheduled_hook( 'gatherpress_unrelated_probe' );

		$this->assertFalse(
			$sweep_on_child,
			'Failed to assert that network deactivation cleared the sweep on the child site.'
		);
	}

	/**
	 * Coverage for `setup_hooks()`'s `register_deactivation_hook()` call.
	 * Both deactivation tests above call `deactivate()` directly, so neither
	 * would fail if the `register_deactivation_hook()` line were removed.
	 * This asserts the registration itself, the same shape as the
	 * cron-wiring gap that would leave `run_sweep()` unregistered.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_deactivation_hook_is_registered(): void {
		$instance = Projection_Cron::get_instance();

		$this->assertSame(
			10,
			has_action(
				'deactivate_' . plugin_basename( GATHERPRESS_CORE_FILE ),
				array( $instance, 'deactivate' )
			),
			'Failed to assert that deactivate() is registered on the real WordPress deactivation hook.'
		);
	}

	/**
	 * Coverage for the horizon top-up: a site that removes its last recurrence must lose
	 * the hourly sweep, not keep dispatching an early-returning callback for
	 * the life of the plugin. The `1 -> 0` transition is the whole point.
	 * A never-recurring site has nothing to unschedule, so the existing
	 * "does nothing on a site with no recurring events" test stays green
	 * whether or not the unscheduling branch exists at all.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_unschedules_when_the_last_recurrence_is_removed(): void {
		$instance = Projection_Cron::get_instance();

		update_option( Query::HAS_RECURRING_OPTION, '1' );
		$instance->maybe_schedule_sweep();

		$this->assertNotFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that the sweep was scheduled while the site had a recurrence.'
		);

		update_option( Query::HAS_RECURRING_OPTION, '0' );
		$instance->maybe_schedule_sweep();

		$this->assertFalse(
			wp_next_scheduled( Projection_Cron::SWEEP_ACTION ),
			'Failed to assert that removing the last recurrence unschedules the hourly sweep.'
		);
	}

	/**
	 * Coverage for the horizon top-up: adding a recurrence schedules the sweep exactly
	 * once, however many times `init` runs. `wp_next_scheduled()` returns
	 * only the soonest timestamp, so it cannot see a second, later
	 * registration of the same hook, so this counts the real cron array
	 * entries instead.
	 *
	 * The second half seeds an existing schedule an hour out before calling
	 * again, and that is the half that measures the dedup guard: WordPress
	 * core refuses a recurring duplicate only when the two timestamps are
	 * within ten minutes of each other, so back-to-back calls in the same
	 * second stay at one entry whether or not this class checks
	 * `wp_next_scheduled()` itself. An `init` an hour after the first one is
	 * the real shape of the second run, and core does not deduplicate it.
	 *
	 * @covers ::maybe_schedule_sweep
	 *
	 * @return void
	 */
	public function test_maybe_schedule_sweep_schedules_exactly_one_event(): void {
		$instance = Projection_Cron::get_instance();

		update_option( Query::HAS_RECURRING_OPTION, '1' );

		$instance->maybe_schedule_sweep();
		$instance->maybe_schedule_sweep();

		$this->assertSame(
			1,
			$this->count_scheduled_sweeps(),
			'Failed to assert that adding a recurrence schedules the sweep exactly once.'
		);

		wp_clear_scheduled_hook( Projection_Cron::SWEEP_ACTION );
		wp_schedule_event( time() + HOUR_IN_SECONDS, Projection_Cron::SWEEP_RECURRENCE, Projection_Cron::SWEEP_ACTION );

		$instance->maybe_schedule_sweep();

		$this->assertSame(
			1,
			$this->count_scheduled_sweeps(),
			'Failed to assert that a later init run does not add a second sweep entry.'
		);
	}

	/**
	 * Count the sweep entries present in the real WordPress cron array.
	 *
	 * @since 0.36.0
	 *
	 * @return int Number of scheduled sweep entries across all timestamps.
	 */
	protected function count_scheduled_sweeps(): int {
		$count = 0;

		foreach ( (array) _get_cron_array() as $hooks ) {
			$count += count( (array) ( $hooks[ Projection_Cron::SWEEP_ACTION ] ?? array() ) );
		}

		return $count;
	}
}
