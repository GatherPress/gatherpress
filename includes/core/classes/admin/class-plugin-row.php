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

		// The network Plugins screen is where a plugin is deleted on
		// multisite, so the warning there has to answer for the network
		// rather than for whichever site the screen happens to run under.
		$network = is_network_admin();
		$armed   = Uninstall_Settings::armed_labels( $network );

		if ( empty( $armed ) ) {
			return;
		}

		// Same shape core gives an unmet dependency in this cell: a wrapper
		// for the margin, and an inline alt notice inside it. Warning rather
		// than error, because nothing is broken.
		echo '<div class="gatherpress-plugin-row-warning">';

		wp_admin_notice(
			$this->get_message( $armed, $network ),
			array(
				'type'               => 'warning',
				'additional_classes' => array( 'inline', 'notice-alt' ),
			)
		);

		echo '</div>';
	}

	/**
	 * The warning itself.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $armed   Labels of the data selected for removal.
	 * @param bool     $network Whether the warning is for the network screen.
	 *
	 * @return string The escaped message.
	 */
	protected function get_message( array $armed, bool $network = false ): string {
		$link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( Uninstall_Settings::get_page_url( $network ) ),
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
