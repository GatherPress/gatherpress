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
 * block in the bootstrap file. The settings-gated tasks (events, venues,
 * RSVPs, topics, venues, files, cron, users, options) are registered the same way,
 * and run only where an administrator opted in on the Uninstall screen.
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
		// The always-on bookkeeping runs first, then the opt-in tasks in
		// dependency order: the posts and the RSVPs recorded against them
		// before the taxonomies that classify what is left, and options last
		// so the opt-in map is the final thing to go.
		$this->add( new Notices() );
		$this->add( new Transients() );
		$this->add( new Events() );
		$this->add( new Venues() );
		$this->add( new Rsvps() );
		$this->add( new Topics() );
		$this->add( new Files() );
		$this->add( new Cron() );
		$this->add( new Users() );
		$this->add( new Options() );
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
		$flush = false;

		foreach ( $this->tasks as $task ) {
			$flush = $flush || ( $task->applies() && $task->invalidates_cache() );

			$task->run();
		}

		// The tasks that delete rows with SQL never told the object cache what
		// they removed, and a persistent backend (Redis, Memcached) outlives
		// the plugin being deleted: it would keep serving objects for rows that
		// are no longer there. Flushing clears the whole site's cache rather
		// than this plugin's share of it, which is why it waits until a task
		// that works that way has actually run.
		if ( $flush ) {
			wp_cache_flush();
		}
	}
}
