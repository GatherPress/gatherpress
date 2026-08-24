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
	 * Whether this task should run at all.
	 *
	 * Today every registered task runs unconditionally. The #681 follow-up
	 * adds destructive tasks (settings, tables, posts) that each hang off
	 * an opt-in setting, and this is where that check will live.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return true;
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
	 * network uninstall, and the network cleanup runs exactly once.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	final public function run(): void {
		if ( ! $this->applies() ) {
			return;
		}

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
				$this->uninstall_site();
				restore_current_blog();
			}
		} else {
			$this->uninstall_site();
		}

		$this->uninstall_network();
	}
}
