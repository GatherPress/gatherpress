<?php
/**
 * Base class for GatherPress uninstall tasks.
 *
 * This file contains the Base class that every GatherPress uninstall task
 * extends.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Base.
 *
 * One uninstall cleanup concern. Subclasses declare what to remove; this
 * class owns whether the task runs and how it fans out across a network,
 * so no subclass ever calls `is_multisite()`.
 *
 * @since 0.36.0
 */
abstract class Base {

	/**
	 * The preference key that gates this task, if one does.
	 *
	 * Null by default: uninstall tasks are destructive, so a task that
	 * never names a preference removes nothing. A task whose cleanup is
	 * always safe (caches, bookkeeping) overrides `applies()` instead.
	 *
	 * @since 0.36.0
	 *
	 * @return string|null A key from `Preferences::task_keys()`, or null.
	 */
	protected function preference(): ?string {
		return null;
	}

	/**
	 * Whether this task should run for the site it is being asked about.
	 *
	 * Asked once per site, inside the fan-out, so each site answers for its
	 * own data. Where the network told its sites what to do, every site
	 * gives the network's answer; where it left the choice to them, they
	 * each give their own.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		$task = $this->preference();

		return null !== $task && Preferences::is_enabled( $task );
	}

	/**
	 * Whether the network-level cleanup should run.
	 *
	 * The network settings and the shared user table belong to no single
	 * site, so the network's own answer governs them rather than whatever
	 * the site the uninstall happens to run on decided.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the network cleanup should run.
	 */
	public function applies_to_network(): bool {
		$task = $this->preference();

		return null === $task
			? $this->applies()
			: Preferences::is_enabled_for_network( $task );
	}

	/**
	 * Whether this task changes rows behind the object cache's back.
	 *
	 * False by default, for the tasks that work through `delete_option()`,
	 * `wp_unschedule_hook()` and the like: core invalidates what those
	 * touch. A task that deletes rows with SQL returns true, and the
	 * registry flushes once at the end if any such task ran.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task writes past the object cache.
	 */
	public function invalidates_cache(): bool {
		return false;
	}

	/**
	 * Per-site cleanup.
	 *
	 * Runs once on a single-site install, and once per subsite (inside
	 * `switch_to_blog()`) on a network uninstall.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	abstract protected function uninstall_site(): void;

	/**
	 * Network-level cleanup.
	 *
	 * Runs once after the per-site pass, on single-site and multisite
	 * alike. Most tasks are purely per-site, so the default is a no-op;
	 * a task that owns network options or network-wide state overrides it.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_network(): void {
	}

	/**
	 * Run the task.
	 *
	 * Final so the multisite contract cannot be overridden away: the
	 * per-site cleanup visits every subsite of the current network on a
	 * network uninstall, asking each one whether it applies there, and the
	 * network cleanup runs at most once.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	final public function run(): void {
		if ( is_multisite() ) {
			// `number => 0` is required so WP doesn't silently cap the
			// loop at 100 sites.
			$site_ids = get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_site()->id,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );

				if ( $this->applies() ) {
					$this->uninstall_site();
				}

				restore_current_blog();
			}
		} elseif ( $this->applies() ) {
			$this->uninstall_site();
		}

		if ( $this->applies_to_network() ) {
			$this->uninstall_network();
		}
	}
}
