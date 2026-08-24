<?php
/**
 * Manages GatherPress uninstall tasks.
 *
 * This file contains the Setup class, the registry that holds and runs
 * the uninstall tasks.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * A registry of uninstall tasks, run by `uninstall.php` when the user
 * deletes the plugin. Each cleanup concern is one small Base subclass, so
 * adding one is a single registration here rather than another procedural
 * block in the bootstrap file. The #681 follow-up registers its
 * settings-gated tasks (options, tables, posts, terms, comments, cron)
 * the same way.
 *
 * @since 0.36.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Registered uninstall tasks.
	 *
	 * @since 0.36.0
	 * @var Base[]
	 */
	protected array $tasks = array();

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->register_default_tasks();
	}

	/**
	 * Register the tasks the plugin ships with.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function register_default_tasks(): void {
		$this->add( new Transients() );
	}

	/**
	 * Add a task to the registry.
	 *
	 * @since 0.36.0
	 *
	 * @param Base $task The uninstall task.
	 *
	 * @return void
	 */
	public function add( Base $task ): void {
		$this->tasks[] = $task;
	}

	/**
	 * The registered tasks.
	 *
	 * @since 0.36.0
	 *
	 * @return Base[] The tasks, in registration order.
	 */
	public function get_tasks(): array {
		return $this->tasks;
	}

	/**
	 * Run every registered task.
	 *
	 * Each task decides for itself whether it applies and how it spans a
	 * network; the registry just runs them in registration order.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->tasks as $task ) {
			$task->run();
		}
	}
}
