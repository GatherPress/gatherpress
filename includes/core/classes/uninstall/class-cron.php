<?php
/**
 * Uninstall task that clears the plugin's scheduled events.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Cron.
 *
 * Clears every scheduled event the plugin owns, so WP-Cron stops firing
 * hooks that no longer have a listener once the plugin is deleted.
 *
 * Sweeps the cron array by hook prefix rather than listing hook names.
 * Every scheduled hook the plugin registers is `gatherpress_`-prefixed —
 * the RSVP cleanup, the email send, the async geocode, and the three
 * static-map jobs — and a prefix sweep keeps a hook added later from being
 * missed because nobody remembered to update this class.
 *
 * @since 0.36.0
 */
final class Cron extends Base {

	/**
	 * Prefix shared by every scheduled hook the plugin registers.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const HOOK_PREFIX = 'gatherpress_';

	/**
	 * Whether the administrator opted in to clearing scheduled events.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_CRON );
	}

	/**
	 * Clear the current site's plugin-owned scheduled events.
	 *
	 * `wp_unschedule_hook()` removes every event for a hook whatever its
	 * arguments, which matters because the geocode and static-map jobs are
	 * scheduled per venue and would otherwise need their exact argument
	 * sets to unschedule.
	 *
	 * The hooks are collected before anything is unscheduled, so the cron
	 * array is not modified while it is being walked.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		$hooks = array();

		foreach ( _get_cron_array() as $events ) {
			foreach ( array_keys( $events ) as $hook ) {
				if ( str_starts_with( (string) $hook, self::HOOK_PREFIX ) ) {
					$hooks[ $hook ] = true;
				}
			}
		}

		foreach ( array_keys( $hooks ) as $hook ) {
			wp_unschedule_hook( (string) $hook );
		}
	}
}
