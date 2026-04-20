<?php
/**
 * Base class for vendored third-party PHP libraries.
 *
 * Each Composer-installed library that ships under
 * `includes/libraries/` (via the `installer-paths` stanza in
 * `composer.json`) gets a thin wrapper subclass here. The base handles
 * the uniform parts — requiring the entry file during bootstrap and
 * showing a non-fatal admin notice when the library is missing — so
 * individual wrappers only declare what's specific to them (entry file
 * path, runtime-availability check, human-readable name).
 *
 * Follows the same pattern the settings pages use: abstract parent in
 * `includes/core/classes/libraries/class-base.php`, concrete subclasses
 * next to it, each one a singleton, all instantiated from
 * `Setup::instantiate_classes()`.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Libraries;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Base.
 *
 * Shared bootstrap for the vendored-library wrapper classes.
 *
 * @since 1.0.0
 */
abstract class Base {
	/**
	 * Load the library and hook the admin notice.
	 *
	 * Subclasses don't need to override — they declare the
	 * library-specific details via the abstract methods below and the
	 * base wires the rest up.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->load_library();
		$this->setup_hooks();
	}

	/**
	 * Path to the library's entry file, relative to `includes/libraries/`.
	 *
	 * E.g. `action-scheduler/action-scheduler.php` for the Action Scheduler
	 * subclass. Matches the `installer-paths` destination in
	 * `composer.json` minus the shared `includes/libraries/` prefix the
	 * Base class takes care of.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract protected function get_library_entry(): string;

	/**
	 * Human-readable library name used in the missing-library admin notice.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	abstract protected function get_library_name(): string;

	/**
	 * Whether the library is loaded and ready to use.
	 *
	 * Call sites that rely on the library gate on this so a rare botched
	 * deploy can't fatal the plugin. Typically implemented via
	 * `function_exists()` or `class_exists()` against one of the library's
	 * public symbols.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	abstract public static function is_available(): bool;

	/**
	 * Require the library entry file when it's on disk.
	 *
	 * Guarded with `file_exists()` so a fresh clone that hasn't run
	 * `composer install` yet keeps the rest of the plugin functional —
	 * call sites always check {@see self::is_available()} before
	 * reaching for a library symbol.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function load_library(): void {
		$entry = trailingslashit( GATHERPRESS_CORE_PATH )
			. 'includes/libraries/'
			. $this->get_library_entry();

		if ( file_exists( $entry ) ) {
			require_once $entry;
		}
	}

	/**
	 * Set up hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render_missing_notice' ) );
	}

	/**
	 * Render an informational admin notice when the library is missing.
	 *
	 * Intentionally non-fatal — the plugin keeps working (individual
	 * subsystems fall back to WP-Cron, inline execution, or simply skip
	 * the affected feature). The notice just points site admins at the
	 * recovery step (`composer install`) so library-dependent behavior
	 * doesn't degrade silently.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_render_missing_notice(): void {
		if ( static::is_available() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_admin_notice(
			sprintf(
				/* translators: 1: library name, 2: composer command, 3: plugin directory. */
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'GatherPress could not load the %1$s library. Run %2$s in the %3$s plugin directory to install the vendored PHP libraries.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				esc_html( $this->get_library_name() ),
				'<code>composer install</code>',
				'<code>gatherpress</code>'
			),
			array(
				'type'           => 'warning',
				'paragraph_wrap' => true,
			)
		);
	}
}
