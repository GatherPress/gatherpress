<?php
/**
 * Uninstall task that wipes plugin-owned transient rows.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Transients.
 *
 * Removes plugin-owned transient rows so the options table no longer
 * accumulates orphans after the plugin is deleted and reinstalled.
 *
 * @since 0.36.0
 */
final class Transients extends Base {

	/**
	 * Wipe plugin-owned transient rows (data + timeout) from the current site.
	 *
	 * Covers both the data row (`_transient_gatherpress_<key>`) and its paired
	 * timeout row (`_transient_timeout_gatherpress_<key>`). Hits the table
	 * directly because some object-cache backends silently no-op the
	 * `delete_transient()` API against the options row — the goal is to remove
	 * rows the plugin wrote, not to manage cache state.
	 *
	 * After the SQL delete, the persistent object cache is purged per key so a
	 * reinstall within the original TTL window does not see a stale cache hit
	 * pointing at a row that no longer exists.
	 *
	 * @since 0.36.0
	 *
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wpdb;

		// Collect the plugin-owned transient keys before deletion so we can
		// invalidate the persistent object cache. The cache stores values
		// under the unsuffixed key inside the `transient` cache group, so
		// we need the key names, not just the option names.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidation pre-delete; not a read path.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_name FROM %i'
				. ' WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_gatherpress_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_gatherpress_' ) . '%'
			)
		);

		// Pattern A: data rows (`_transient_gatherpress_*`).
		// Pattern B: timeout rows (`_transient_timeout_gatherpress_*`).
		// Patterns do not overlap — after the `_transient_` prefix the next
		// char differs (`g` vs `t`).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk delete on uninstall; not a read path.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
				$wpdb->options,
				$wpdb->esc_like( '_transient_gatherpress_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_gatherpress_' ) . '%'
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		// Strip the `_transient_` prefix to recover the cache key WP uses
		// inside the `transient` cache group; only data rows map to a cache
		// entry, timeout rows are metadata-only.
		foreach ( $rows as $row ) {
			$name = (string) $row->option_name;

			// Evict every collected row from the `options` group; data and
			// timeout rows both live in wp_options, so both must clear or a
			// subsequent get_option() returns the deleted row from a persistent
			// object cache. `alloptions` and `notoptions` are populated lazily
			// on first DB touch; clearing them per row is cheap and avoids
			// tracking which names already primed the cache.
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );

			// Only data rows (`_transient_gatherpress_…`) have a matching
			// `transient` cache entry, since WP strips the `_transient_` prefix
			// before storing. Timeout rows live under `_transient_timeout_gatherpress_…`
			// and have no parallel cache slot.
			if ( str_starts_with( $name, '_transient_gatherpress_' ) ) {
				wp_cache_delete( substr( $name, strlen( '_transient_' ) ), 'transient' );
			}
		}
	}
}
