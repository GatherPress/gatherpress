<?php
/**
 * Tools settings page for GatherPress.
 *
 * This class handles the "Tools" settings page in GatherPress, providing
 * import and export functionality for plugin settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Tools.
 *
 * Handles the "Tools" settings page for GatherPress.
 *
 * @since 1.0.0
 */
class Tools extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'wp_ajax_gatherpress_export_settings', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_gatherpress_import_settings', array( $this, 'ajax_import' ) );
	}

	/**
	 * Get the slug for the tools settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the tools settings page.
	 */
	protected function get_slug(): string {
		return 'tools';
	}

	/**
	 * Get the name for the tools settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the tools settings page.
	 */
	protected function get_name(): string {
		return __( 'Tools', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the tools settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying the tools settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 1;
	}

	/**
	 * Render the custom tools section instead of the default settings form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page The current settings page slug.
	 * @return void
	 */
	public function settings_section( string $page ): void {
		if ( Utility::unprefix_key( $page ) === $this->slug ) {
			remove_action(
				'gatherpress_settings_section',
				array( Settings::get_instance(), 'render_settings_form' )
			);

			Utility::render_template(
				sprintf( '%s/includes/templates/admin/settings/tools.php', GATHERPRESS_CORE_PATH ),
				array(),
				true
			);
		}
	}

	/**
	 * AJAX handler for exporting settings.
	 *
	 * Verifies permissions and nonce, then sends the settings export as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'gatherpress' ) )
			);
		}

		check_ajax_referer( 'gatherpress_tools_nonce', 'nonce' );

		$settings = Settings::get_instance();

		wp_send_json_success( $settings->export_settings() );
	}

	/**
	 * AJAX handler for importing settings.
	 *
	 * Verifies permissions and nonce, parses the uploaded JSON,
	 * validates and imports the settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'gatherpress' ) )
			);
		}

		check_ajax_referer( 'gatherpress_tools_nonce', 'nonce' );

		// Raw JSON string — validated by json_decode below, individual values sanitized during import.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$json = isset( $_POST['settings_json'] ) ? wp_unslash( $_POST['settings_json'] ) : '';
		$mode = Utility::get_http_input( INPUT_POST, 'import_mode' );

		if ( empty( $json ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No settings data provided.', 'gatherpress' ) )
			);
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid JSON data.', 'gatherpress' ) )
			);
		}

		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			$mode = 'merge';
		}

		$settings = Settings::get_instance();
		$result   = $settings->import_settings( $data, $mode );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
}
