<?php
/**
 * Coexistence activation guard for GatherPress and its companion plugins.
 *
 * Detects when more than one folder matching a plugin's slug pattern exists on
 * disk (the artifact of WordPress's upload-replace flow when the new build's
 * folder name doesn't match the existing one) and refuses activation while a
 * sibling copy is already running, surfacing a `wp_die()` page that lists the
 * duplicate folders.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Coexistence_Guard.
 *
 * Centralizes the "two folders of the same plugin, only one should run" guard
 * for GatherPress and any companion plugin that registers itself via the
 * `gatherpress_register_coexistence_guards` action.
 *
 * @since 1.0.0
 */
class Coexistence_Guard {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'fire_registration_action' ) );
	}

	/**
	 * Self-registers GatherPress and fires the public registration action so
	 * companion plugins can wire up their own guards.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function fire_registration_action(): void {
		$this->register( 'gatherpress', 'GatherPress', GATHERPRESS_CORE_FILE );

		/**
		 * Fires to allow GatherPress companion plugins to register coexistence
		 * activation guards.
		 *
		 * Companion plugins should hook into this action and call
		 * `gatherpress_register_coexistence_guard()` from inside the callback,
		 * passing their plugin slug, display name, and `__FILE__`. The
		 * `function_exists()` guard recommended in the helper's docblock makes
		 * the registration a graceful no-op if GatherPress is removed in the
		 * future.
		 *
		 * @since 1.0.0
		 */
		do_action( 'gatherpress_register_coexistence_guards' );
	}

	/**
	 * Wire up the activation guard for one plugin.
	 *
	 * Registers the canonical plugin's activation hook AND, when running in
	 * admin context, mirrors the same callback onto every sibling folder
	 * already on disk so an older build that doesn't carry this code still
	 * trips the guard when activated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug      Folder slug, e.g. `gatherpress-alpha`. The companion
	 *                          plugin's main file must be `<slug>.php` inside a
	 *                          folder named `<slug>` (canonical) or `<slug>-*` (siblings).
	 * @param string $name      Display name, e.g. `GatherPress Alpha`.
	 * @param string $main_file Absolute path to the companion's main plugin file.
	 * @return void
	 */
	public function register( string $slug, string $name, string $main_file ): void {
		$callback = function () use ( $slug, $name ): void {
			$this->refuse_on_duplicates( $slug, $name );
		};

		register_activation_hook( $main_file, $callback );

		if ( ! is_admin() ) {
			return;
		}

		foreach ( $this->find_duplicates( $slug ) as $sibling ) {
			add_action( 'activate_' . $sibling, $callback );
		}
	}

	/**
	 * Returns every installed plugin file matching `<slug>*\/<slug>.php`, sorted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin folder slug.
	 * @return string[] Plugin basenames matching the slug pattern.
	 */
	public function find_duplicates( string $slug ): array {
		$plugins       = get_plugins();
		$duplicates    = array();
		$expected_file = $slug . '.php';
		$folder_prefix = $slug . '-';

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			if ( ! is_string( $plugin_file ) ) {
				continue;
			}

			$parts = explode( '/', $plugin_file );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			list( $folder, $file ) = $parts;

			if ( $expected_file !== $file ) {
				continue;
			}

			if ( $slug !== $folder && 0 !== strpos( $folder, $folder_prefix ) ) {
				continue;
			}

			$duplicates[] = $plugin_file;
		}

		sort( $duplicates );

		return $duplicates;
	}

	/**
	 * Refuses activation when more than one matching folder is on disk and a
	 * sibling copy is already active.
	 *
	 * Removes the failure-redirect Location header that `activate_plugin()`
	 * pre-sends, then `wp_die()`s with a list of duplicate folders. The
	 * `update_option( 'active_plugins', ... )` call in WordPress core runs
	 * after this hook, so `wp_die()` alone prevents the plugin from being
	 * persisted as active.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin folder slug.
	 * @param string $name Plugin display name.
	 * @return void
	 */
	public function refuse_on_duplicates( string $slug, string $name ): void {
		$duplicates = $this->find_duplicates( $slug );

		if ( count( $duplicates ) <= 1 ) {
			return;
		}

		$active_duplicates = array_values( array_filter( $duplicates, 'is_plugin_active' ) );

		if ( empty( $active_duplicates ) ) {
			return;
		}

		// `headers_sent()` is unreliable in the PHPUnit CLI test environment —
		// the test runner prints progress output to stdout before this branch
		// runs, so the guarded `header_remove()` call cannot be reached from a
		// test. The branch is exercised in production during the activation
		// request, where the failure-Location header is queued but no body has
		// been emitted yet.
		if ( ! headers_sent() ) {
			header_remove( 'Location' ); // @codeCoverageIgnore
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
				esc_html(
					sprintf(
						/* translators: %s: Plugin name. */
						__( 'Another copy of %s is already active', 'gatherpress' ),
						$name
					)
				),
				esc_html(
					sprintf(
						/* translators: %s: Plugin name. */
						__(
							// phpcs:disable Generic.Files.LineLength.TooLong
							'Only one version of %s can run at a time. WordPress installed a new copy in a separate folder instead of replacing the existing one — both folders are now on disk:',
							// phpcs:enable Generic.Files.LineLength.TooLong
							'gatherpress'
						),
						$name
					)
				),
				implode( '</code></li><li><code>', array_map( 'esc_html', $folders ) ),
				esc_html__(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'Deactivate the currently-active copy or remove the duplicate folder via SFTP, then return to the plugins screen and try again.',
					'gatherpress'
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: Plugin name. */
					__( '%s activation halted', 'gatherpress' ),
					$name
				)
			),
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}
}
