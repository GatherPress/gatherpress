<?php
/**
 * Unit tests for the uninstall task registry.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Uninstall\Base;
use GatherPress\Core\Uninstall\Cron;
use GatherPress\Core\Uninstall\Events;
use GatherPress\Core\Uninstall\Files;
use GatherPress\Core\Uninstall\Notices;
use GatherPress\Core\Uninstall\Options;
use GatherPress\Core\Uninstall\Rsvps;
use GatherPress\Core\Uninstall\Setup;
use GatherPress\Core\Uninstall\Topics;
use GatherPress\Core\Uninstall\Transients;
use GatherPress\Core\Uninstall\Users;
use GatherPress\Core\Uninstall\Venues;
use GatherPress\Tests\Base as Test_Base;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Setup
 */
class Test_Setup extends Test_Base {

	/**
	 * The registry ships with every task, in the order they have to run.
	 *
	 * @covers ::__construct
	 * @covers ::register_default_tasks
	 * @covers ::get_tasks
	 *
	 * @return void
	 */
	public function test_registers_default_tasks(): void {
		$expected = array(
			Notices::class,
			Transients::class,
			Events::class,
			Venues::class,
			Rsvps::class,
			Topics::class,
			Files::class,
			Cron::class,
			Users::class,
			Options::class,
		);

		$classes = array_values(
			array_intersect(
				array_map( 'get_class', Setup::get_instance()->get_tasks() ),
				$expected
			)
		);

		$this->assertSame(
			$expected,
			$classes,
			'Order is part of the contract, and the opt-in map has to go last.'
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

	/**
	 * The object cache is left alone when nothing wrote past it.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_leaves_the_cache_alone_without_a_sql_task(): void {
		wp_cache_set( 'probe', 'value', 'gatherpress_test' );

		Setup::get_instance()->run();

		$this->assertSame(
			'value',
			wp_cache_get( 'probe', 'gatherpress_test' ),
			'Flushing clears the whole site, so an uninstall that removed nothing must not do it.'
		);
	}

	/**
	 * The object cache is flushed once a task that writes past it has run.
	 *
	 * @covers ::run
	 *
	 * @return void
	 */
	public function test_run_flushes_the_cache_for_a_sql_task(): void {
		$task = new class() extends Base {

			/**
			 * Opts in, so the registry sees a task that will do work.
			 *
			 * @return bool Always true.
			 */
			public function applies(): bool {
				return true;
			}

			/**
			 * Reports that it deletes rows with SQL.
			 *
			 * @return bool Always true.
			 */
			public function invalidates_cache(): bool {
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

		$instance = Setup::get_instance();
		$instance->add( $task );

		wp_cache_set( 'probe', 'value', 'gatherpress_test' );

		$instance->run();

		$this->assertFalse(
			wp_cache_get( 'probe', 'gatherpress_test' ),
			'A persistent cache outlives the plugin, so deleted rows must not stay readable through it.'
		);
	}
}
