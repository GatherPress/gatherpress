<?php
/**
 * Uninstall task that drops the plugin's custom tables.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

/**
 * Class Tables.
 *
 * Drops the custom event table `Setup::create_tables()` builds with
 * `dbDelta()`. The table is per-site: it is named from `$wpdb->prefix`,
 * which changes inside `switch_to_blog()`, so the drop belongs in the
 * per-site pass and never in the network one.
 *
 * @since 0.36.0
 */
final class Tables extends Base {

	/**
	 * Whether the administrator opted in to dropping tables.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_TABLES );
	}

	/**
	 * Drop the current site's event table.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping a plugin-owned table on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}
