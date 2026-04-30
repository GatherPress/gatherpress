<?php
/**
 * Duplicate GatherPress Plugin Check.
 *
 * This file checks to determine if another version of GatherPress is already running.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_activated = false;

if ( defined( 'GATHERPRESS_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			wp_admin_notice(
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'You have more than one version of GatherPress installed and activated. Please activate only one version of GatherPress at a time.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				array(
					'type' => 'error',
				)
			);
		}
	);

	$gatherpress_activated = true;
}

if ( ! function_exists( 'gatherpress_find_duplicate_folders' ) ) {
	/**
	 * Returns the installed plugin folders that look like duplicate copies of GatherPress.
	 *
	 * WordPress's upload-replace flow keys off the plugin folder slug, so a fresh
	 * upload of a GatherPress build whose folder name doesn't match an existing
	 * copy lands in a sibling folder (`gatherpress-1`, `gatherpress-build`, etc.)
	 * and leaves the older copy in place. This helper scans `get_plugins()` for
	 * any `gatherpress*\/gatherpress.php` entries so callers can refuse activation
	 * when more than one is on disk.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Plugin basenames of every GatherPress-shaped folder found, sorted.
	 */
	function gatherpress_find_duplicate_folders(): array {
		$plugins    = get_plugins();
		$duplicates = array();

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}

			$parts = explode( '/', $plugin_file );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			list( $folder, $file ) = $parts;

			if ( 'gatherpress.php' !== $file ) {
				continue;
			}

			if ( 'gatherpress' !== $folder && 0 !== strpos( $folder, 'gatherpress-' ) ) {
				continue;
			}

			$duplicates[] = $plugin_file;
		}

		sort( $duplicates );

		return $duplicates;
	}
}

if ( ! function_exists( 'gatherpress_refuse_activation_on_duplicates' ) ) {
	/**
	 * Refuses activation when more than one GatherPress folder is on disk.
	 *
	 * Belt-and-suspenders against WordPress's upload-replace miss: if the user has
	 * uploaded a newer build into a sibling folder rather than replacing the
	 * existing one, both copies show up in `get_plugins()`. Activating either
	 * while the other still exists creates two GatherPress plugin rows. This
	 * deactivates the plugin and halts with `wp_die()` so the user has to clean
	 * up the duplicate folders before activation can succeed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function gatherpress_refuse_activation_on_duplicates(): void {
		$duplicates = gatherpress_find_duplicate_folders();

		if ( count( $duplicates ) <= 1 ) {
			return;
		}

		// Only refuse when another copy of GatherPress is already active. With
		// two folders on disk and neither active, the user is allowed to activate
		// one — the guard kicks in only on the second activation attempt while a
		// sibling is already running.
		$active_duplicates = array_values( array_filter( $duplicates, 'is_plugin_active' ) );

		if ( empty( $active_duplicates ) ) {
			return;
		}

		// `activate_plugin()` pre-sends a `Location:` redirect header to a failure
		// URL before running the activation hook, so any output produced by
		// `wp_die()` here would be discarded by the browser as it follows the
		// redirect. Remove the pre-set redirect so the user actually sees this.
		// `update_option( 'active_plugins', ... )` runs *after* this hook in
		// `activate_plugin()`, so `wp_die()` alone is enough to prevent the plugin
		// from being persisted as active — no manual deactivation needed.
		if ( ! headers_sent() ) {
			header_remove( 'Location' );
		}

		$folders = array_map(
			static function ( string $plugin_file ): string {
				return dirname( $plugin_file );
			},
			$duplicates
		);

		wp_die(
			sprintf(
				'<h1>%s</h1><p>%s</p><ul><li><code>%s</code></li></ul><p>%s</p>',
				esc_html__( 'Another copy of GatherPress is already active', 'gatherpress' ),
				esc_html__(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'Only one version of GatherPress can run at a time. WordPress installed a new copy in a separate folder instead of replacing the existing one — both folders are now on disk:',
					'gatherpress'
				),
				implode( '</code></li><li><code>', array_map( 'esc_html', $folders ) ),
				esc_html__(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'Deactivate the currently-active copy or remove the duplicate folder via SFTP, then return to the plugins screen and try again.',
					'gatherpress'
				)
			),
			esc_html__( 'GatherPress activation halted', 'gatherpress' ),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}
}

register_activation_hook(
	dirname( __DIR__, 2 ) . '/gatherpress.php',
	'gatherpress_refuse_activation_on_duplicates'
);

// Also wire the activation guard to every other GatherPress-shaped folder so
// activating an older copy that lacks this guard still triggers the check from
// the active install. `get_plugins()` is only available in admin context.
if ( is_admin() ) {
	foreach ( gatherpress_find_duplicate_folders() as $gatherpress_sibling_plugin_file ) {
		add_action( 'activate_' . $gatherpress_sibling_plugin_file, 'gatherpress_refuse_activation_on_duplicates' );
	}
}

return $gatherpress_activated;
