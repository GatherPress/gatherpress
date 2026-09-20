<?php
/**
 * Unit tests for the abstract uninstall task base.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Base as Uninstall_Base;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Base.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Base
 */
class Test_Base extends Base {

	/**
	 * Arms and resets the uninstall opt-ins.
	 */
	use Preferences_Fixture;

	/**
	 * A task is assumed to work through the APIs core already invalidates.
	 *
	 * @covers ::invalidates_cache
	 *
	 * @return void
	 */
	public function test_invalidates_cache_defaults_to_false(): void {
		$this->assertFalse(
			$this->make_task()->invalidates_cache(),
			'Flushing clears the whole site, so a task has to ask for it.'
		);
	}

	/**
	 * Builds a task double that counts its cleanup calls.
	 *
	 * @since 0.36.0
	 *
	 * @param bool $applies What the double's applies() reports.
	 *
	 * @return Uninstall_Base The task double, carrying public $site_runs and $network_runs.
	 */
	private function make_task( bool $applies = true ): Uninstall_Base {
		return new class( $applies ) extends Uninstall_Base {

			/**
			 * How many times uninstall_site() ran.
			 *
			 * @var int
			 */
			public int $site_runs = 0;

			/**
			 * How many times uninstall_network() ran.
			 *
			 * @var int
			 */
			public int $network_runs = 0;

			/**
			 * What applies() reports.
			 *
			 * @var bool
			 */
			private bool $applies;

			/**
			 * Class constructor.
			 *
			 * @param bool $applies What applies() reports.
			 */
			public function __construct( bool $applies ) {
				$this->applies = $applies;
			}

			/**
			 * Reports the configured applicability.
			 *
			 * @return bool The configured value.
			 */
			public function applies(): bool {
				return $this->applies;
			}

			/**
			 * Counts the per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
				++$this->site_runs;
			}

			/**
			 * Counts the network pass.
			 *
			 * @return void
			 */
			protected function uninstall_network(): void {
				++$this->network_runs;
			}
		};
	}

	/**
	 * Applies defaults to false so a bare task removes nothing.
	 *
	 * Uninstall tasks are destructive; a task must opt in before it runs.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_defaults_to_false(): void {
		$task = new class() extends Uninstall_Base {

			/**
			 * No-op per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
			}
		};

		$this->assertFalse(
			$task->applies(),
			'A task that does not opt in must not run.'
		);
	}

	/**
	 * The default network pass is a no-op.
	 *
	 * @covers ::uninstall_network
	 *
	 * @return void
	 */
	public function test_uninstall_network_defaults_to_noop(): void {
		$task = new class() extends Uninstall_Base {

			/**
			 * No-op per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
			}
		};

		$this->assertNull(
			Utility::invoke_hidden_method( $task, 'uninstall_network' ),
			'The base network pass should do nothing.'
		);
	}

	/**
	 * Run performs the per-site pass once and the network pass once.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_performs_site_then_network_cleanup(): void {
		$task = $this->make_task();

		$task->run();

		$this->assertSame(
			1,
			$task->site_runs,
			'Single-site: the per-site pass should run exactly once.'
		);
		$this->assertSame(
			1,
			$task->network_runs,
			'The network pass should run exactly once.'
		);
	}

	/**
	 * Run does nothing when the task does not apply.
	 *
	 * @covers ::run
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_run_skips_task_that_does_not_apply(): void {
		$task = $this->make_task( false );

		$task->run();

		$this->assertSame(
			0,
			$task->site_runs,
			'A non-applying task must not run its per-site pass.'
		);
		$this->assertSame(
			0,
			$task->network_runs,
			'A non-applying task must not run its network pass.'
		);
	}

	/**
	 * On multisite, run visits every subsite and the network pass runs once.
	 *
	 * @covers ::run
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_run_visits_every_subsite_on_multisite(): void {
		$site_id_b = $this->factory()->blog->create();
		$task      = $this->make_task();
		$origin_id = get_current_blog_id();

		$task->run();

		$site_count = count(
			get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_site()->id,
				)
			)
		);

		$this->assertGreaterThanOrEqual(
			2,
			$site_count,
			'Pre-condition: the network holds the primary site and the created subsite.'
		);
		$this->assertSame(
			$site_count,
			$task->site_runs,
			'Multisite: the per-site pass should run once per subsite.'
		);
		$this->assertSame(
			1,
			$task->network_runs,
			'Multisite: the network pass should still run exactly once.'
		);
		$this->assertSame(
			$origin_id,
			get_current_blog_id(),
			'The loop should restore the original site.'
		);

		wp_delete_site( $site_id_b );
	}

	/**
	 * A task with no preference answers the network question the same way.
	 *
	 * @covers ::applies_to_network
	 *
	 * @return void
	 */
	public function test_applies_to_network_follows_applies_without_a_preference(): void {
		$task = new class() extends Uninstall_Base {

			/**
			 * Always runs, the way the bookkeeping tasks do.
			 *
			 * @return bool Always true.
			 */
			public function applies(): bool {
				return true;
			}

			/**
			 * No-op per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
			}
		};

		$this->assertTrue(
			$task->applies_to_network(),
			'A task that names no preference has one answer, not two.'
		);
	}

	/**
	 * A task with a preference asks the network for the network pass.
	 *
	 * @covers ::applies
	 * @covers ::applies_to_network
	 * @group multisite
	 *
	 * @return void
	 */
	public function test_applies_to_network_asks_the_network(): void {
		$this->reset_uninstall_preferences();

		$task = new class() extends Uninstall_Base {

			/**
			 * The preference that gates this double.
			 *
			 * @return string The task key.
			 */
			protected function preference(): string {
				return Preferences::TASK_OPTIONS;
			}

			/**
			 * No-op per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
			}
		};

		$this->arm_uninstall_for_network( Preferences::TASK_OPTIONS );

		$this->assertTrue(
			$task->applies_to_network(),
			'The network settings belong to no site, so the network answers for them.'
		);

		$this->reset_uninstall_preferences();

		$this->assertFalse(
			$task->applies_to_network(),
			'A network that never opted in keeps what it owns.'
		);
	}

	/**
	 * A task names no preference unless it says so.
	 *
	 * Invoked directly as well as through `applies()`, because xdebug does
	 * not trace a protected method called from the parent class.
	 *
	 * @covers ::preference
	 *
	 * @return void
	 */
	public function test_preference_defaults_to_none(): void {
		$this->assertNull(
			Utility::invoke_hidden_method( $this->make_task(), 'preference' ),
			'A task that names no preference is gated by nothing, so it removes nothing.'
		);
	}
}
