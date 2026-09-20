<?php
/**
 * Uninstall task that removes event posts and the event date table.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

/**
 * Class Events.
 *
 * Removes the event posts and everything that only exists because they do,
 * then drops the custom table that holds their start and end times.
 *
 * The table goes with the posts rather than on a switch of its own. Every
 * row in it is keyed by `post_id`, so keeping the table after the posts are
 * gone leaves a row per deleted event pointing at nothing, and keeping the
 * posts after the table is gone leaves events with no dates.
 *
 * @since 0.36.0
 */
final class Events extends Post_Type {

	/**
	 * Whether the administrator opted in to removing events.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_EVENTS );
	}

	/**
	 * The post type this task removes.
	 *
	 * @since 0.36.0
	 *
	 * @return string The event post type name.
	 */
	protected function post_type(): string {
		return Event::POST_TYPE;
	}

	/**
	 * Remove the current site's events and drop its event date table.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		$this->remove_posts();
		$this->drop_table();
	}

	/**
	 * Drop the current site's event date table.
	 *
	 * The table is per-site: it is named from `$wpdb->prefix`, which changes
	 * inside `switch_to_blog()`, so the drop belongs in the per-site pass and
	 * never in the network one.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function drop_table(): void {
		global $wpdb;

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping a plugin-owned table on uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}
