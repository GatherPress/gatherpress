<?php
/**
 * Unit tests for the uninstall task registry.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Base;
use GatherPress\Core\Uninstall\Setup;
use GatherPress\Core\Uninstall\Notices;
use GatherPress\Core\Uninstall\Transients;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Setup
 */
class Test_Setup extends Test_Base {

	/**
	 * The registry ships with the always-safe cleanups registered.
	 *
	 * @covers ::__construct
	 * @covers ::register_default_tasks
	 * @covers ::get_tasks
	 *
	 * @return void
	 */
	public function test_registers_default_tasks(): void {
		$tasks   = Setup::get_instance()->get_tasks();
		$classes = array_map( 'get_class', $tasks );

		$this->assertNotEmpty( $tasks, 'The registry should ship with tasks.' );
		$this->assertContains(
			Transients::class,
			$classes,
			'The transient wipe should be registered.'
		);
		$this->assertContains(
			Notices::class,
			$classes,
			'The notice cleanup should be registered.'
		);
	}

	/**
	 * Added tasks land in the registry in registration order.
	 *
	 * @covers ::add
	 * @covers ::get_tasks
	 *
	 * @return void
	 */
	public function test_add_appends_to_the_registry(): void {
		$instance = Setup::get_instance();
		$before   = count( $instance->get_tasks() );
		$task     = new class() extends Base {

			/**
			 * No-op per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
			}
		};

		$instance->add( $task );
		$tasks = $instance->get_tasks();

		$this->assertCount(
			$before + 1,
			$tasks,
			'Adding a task should grow the registry by one.'
		);
		$this->assertSame(
			$task,
			end( $tasks ),
			'The added task should sit at the end of the registry.'
		);
	}

	/**
	 * Run executes every registered task.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_executes_each_task(): void {
		$task = new class() extends Base {

			/**
			 * Whether the per-site pass ran.
			 *
			 * @var bool
			 */
			public bool $ran = false;

			/**
			 * Opts in; applies() defaults to false so a bare double never runs.
			 *
			 * @return bool Always true.
			 */
			public function applies(): bool {
				return true;
			}

			/**
			 * Records the per-site pass.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
				$this->ran = true;
			}
		};

		$instance = Setup::get_instance();
		$instance->add( $task );
		$instance->run();

		$this->assertTrue(
			$task->ran,
			'Running the registry should run each registered task.'
		);
	}
}
