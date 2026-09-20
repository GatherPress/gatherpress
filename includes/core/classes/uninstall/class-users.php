<?php
/**
 * Uninstall task that removes per-user preferences.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;

/**
 * Class Users.
 *
 * Removes what the plugin stored against individual users: the time format
 * and time zone they chose on their profile, whether they opted in to event
 * updates, and the screen options the RSVP list table keeps for them.
 *
 * All of it is network-wide. `usermeta` is a single shared table on
 * multisite and the plugin writes these keys unprefixed, so this runs once
 * in the network pass rather than once per site.
 *
 * @since 0.36.0
 */
final class Users extends Base {

	/**
	 * The preference that gates this task.
	 *
	 * @since 0.36.0
	 *
	 * @return string The task key.
	 */
	protected function preference(): string {
		return Preferences::TASK_USERS;
	}

	/**
	 * This task deletes rows with SQL.
	 *
	 * @since 0.36.0
	 *
	 * @return bool Always true.
	 */
	public function invalidates_cache(): bool {
		return true;
	}

	/**
	 * The meta keys the plugin writes against a user.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] The meta keys.
	 */
	public static function meta_keys(): array {
		return array(
			'gatherpress_timezone',
			'gatherpress_time_format',
			'gatherpress_event_updates_opt_in',
			sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ),
		);
	}

	/**
	 * Nothing to do per site.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
	}

	/**
	 * Remove the plugin's user meta.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_network(): void {
		global $wpdb;

		foreach ( self::meta_keys() as $meta_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE meta_key = %s',
					$wpdb->usermeta,
					$meta_key
				)
			);
		}

		// Core stores each user's hidden columns for a screen as
		// `manage{$screen_id}columnshidden`, and the RSVP screen's ID is not
		// knowable here because the screen is never registered during an
		// uninstall. Matching the shape is what finds them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE meta_key LIKE %s',
				$wpdb->usermeta,
				'manage%' . $wpdb->esc_like( 'gatherpress' ) . '%columnshidden'
			)
		);
	}
}
