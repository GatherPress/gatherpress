<?php
/**
 * Duplicate GatherPress Plugin Check.
 *
 * Detects whether another copy of GatherPress is already running and exposes
 * the public helper companion plugins call from inside the
 * `gatherpress_register_coexistence_guards` action.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Coexistence_Guard;

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

if ( ! function_exists( 'gatherpress_register_coexistence_guard' ) ) {
	/**
	 * Registers a coexistence activation guard for a GatherPress companion plugin.
	 *
	 * Companion plugins should hook into the `gatherpress_register_coexistence_guards`
	 * action and call this helper from inside the callback, passing their plugin
	 * slug, display name, and `__FILE__`:
	 *
	 * ```
	 * add_action( 'gatherpress_register_coexistence_guards', static function (): void {
	 *     if ( function_exists( 'gatherpress_register_coexistence_guard' ) ) {
	 *         gatherpress_register_coexistence_guard( 'my-plugin-slug', 'My Plugin', __FILE__ );
	 *     }
	 * } );
	 * ```
	 *
	 * The `function_exists()` guard turns the call into a graceful no-op if the
	 * helper is removed in a future version of GatherPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug      Plugin folder slug, e.g. `gatherpress-alpha`.
	 * @param string $name      Plugin display name, e.g. `GatherPress Alpha`.
	 * @param string $main_file Absolute path to the companion's main plugin file.
	 * @return void
	 */
	function gatherpress_register_coexistence_guard( string $slug, string $name, string $main_file ): void {
		Coexistence_Guard::get_instance()->register( $slug, $name, $main_file );
	}
}

return $gatherpress_activated;
