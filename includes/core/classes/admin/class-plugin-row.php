<?php
/**
 * Warning attached to the plugin's own row on the Plugins screen.
 *
 * @package GatherPress\Core\Admin
 * @since 0.36.0
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings\Uninstall as Uninstall_Settings;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Plugin_Row.
 *
 * The Uninstall screen is where somebody opts in to having their data
 * removed, and the Plugins screen is where they act on it, possibly months
 * later and possibly not the same person. This puts the consequence in the
 * plugin's own description, beside the Delete link that triggers it.
 *
 * Rendered where core puts what it has to say about a plugin: the same
 * place as "Required by" and the note about a plugin that cannot be
 * deactivated yet, and in the same shape, so it reads as part of the row
 * rather than as a banner dropped underneath it.
 *
 * Only rendered when something is actually selected. A site that opted into
 * nothing loses nothing, and has no reason to be warned.
 *
 * @since 0.36.0
 */
class Plugin_Row {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'after_plugin_row_meta', array( $this, 'render_uninstall_warning' ) );
	}

	/**
	 * Warn that deleting the plugin will take data with it.
	 *
	 * @since 0.36.0
	 *
	 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
	 *
	 * @return void
	 */
	public function render_uninstall_warning( string $plugin_file ): void {
		if ( plugin_basename( GATHERPRESS_CORE_FILE ) !== $plugin_file ) {
			return;
		}

		$armed = Uninstall_Settings::armed_labels();

		if ( empty( $armed ) ) {
			return;
		}

		printf(
			'<p><span class="dashicons dashicons-warning"></span> %s</p>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_message().
			$this->get_message( $armed )
		);
	}

	/**
	 * The warning itself.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $armed Labels of the data selected for removal.
	 *
	 * @return string The escaped message.
	 */
	protected function get_message( array $armed ): string {
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( Uninstall_Settings::get_page_url() ),
			esc_html__( 'GatherPress Uninstall screen', 'gatherpress' )
		);

		return sprintf(
			'<strong>%1$s</strong> %2$s',
			esc_html__( 'Deleting GatherPress will also delete your data.', 'gatherpress' ),
			sprintf(
				/* translators: 1: List of data selected for removal, 2: Link to the Uninstall screen. */
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong -- One translator string, kept whole.
					'%1$s will be removed and cannot be recovered. Change what goes on the %2$s.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				wp_sprintf_l( '%l', array_map( 'esc_html', $armed ) ),
				$link
			)
		);
	}
}
