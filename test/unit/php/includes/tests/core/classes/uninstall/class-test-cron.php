<?php
/**
 * Unit tests for the scheduled-event uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Cron;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;

/**
 * Class Test_Cron.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Cron
 */
class Test_Cron extends Base {

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_unschedule_hook( 'gatherpress_rsvp_cleanup' );
		wp_unschedule_hook( 'gatherpress_async_geocode_venue' );
		wp_unschedule_hook( 'some_other_plugin_job' );

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Cron() )->applies(),
			'Clearing scheduled jobs must wait for an explicit opt-in.'
		);
	}

	/**
	 * The task applies once its preference is on.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_opted_in(): void {
		Preferences::save( array( Preferences::TASK_CRON => true ) );

		$this->assertTrue(
			( new Cron() )->applies(),
			'The opt-in turns the task on.'
		);
	}

	/**
	 * Nothing is unscheduled while the task is off.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_jobs_alone_without_opt_in(): void {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gatherpress_rsvp_cleanup' );

		( new Cron() )->run();

		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_rsvp_cleanup' ),
			'A task that was never opted in to must remove nothing.'
		);
	}

	/**
	 * Plugin-owned jobs go, and other plugins' jobs stay.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_clears_only_plugin_owned_jobs(): void {
		Preferences::save( array( Preferences::TASK_CRON => true ) );

		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gatherpress_rsvp_cleanup' );
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'some_other_plugin_job' );

		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_rsvp_cleanup' ),
			'Pre-condition: the plugin job is scheduled.'
		);

		( new Cron() )->run();

		$this->assertFalse(
			wp_next_scheduled( 'gatherpress_rsvp_cleanup' ),
			'A gatherpress_-prefixed job is cleared.'
		);
		$this->assertNotFalse(
			wp_next_scheduled( 'some_other_plugin_job' ),
			'Another plugin\'s job must survive.'
		);
	}

	/**
	 * A job scheduled with arguments is cleared without knowing them.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_clears_jobs_scheduled_with_arguments(): void {
		Preferences::save( array( Preferences::TASK_CRON => true ) );

		$args = array( 123 );

		wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'gatherpress_async_geocode_venue', $args );

		$this->assertNotFalse(
			wp_next_scheduled( 'gatherpress_async_geocode_venue', $args ),
			'Pre-condition: the per-venue job is scheduled.'
		);

		( new Cron() )->run();

		$this->assertFalse(
			wp_next_scheduled( 'gatherpress_async_geocode_venue', $args ),
			'wp_unschedule_hook() clears every event for the hook, whatever its arguments.'
		);
	}
}
