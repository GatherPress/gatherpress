<?php
/**
 * Tools settings page for GatherPress.
 *
 * This class handles the "Tools" settings page in GatherPress, providing
 * import and export functionality for plugin settings.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Tools.
 *
 * Handles the "Tools" settings page for GatherPress.
 *
 * @since 0.34.0
 */
final class Tools extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Get the slug for the tools settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The slug for the tools settings page.
	 */
	protected function get_slug(): string {
		return 'tools_settings';
	}

	/**
	 * Get the name for the tools settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return string The localized name for the tools settings page.
	 */
	protected function get_name(): string {
		return __( 'Tools', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the tools settings page.
	 *
	 * @since 0.34.0
	 *
	 * @return int The priority for displaying the tools settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 2;
	}

	/**
	 * Render the custom tools section instead of the default settings form.
	 *
	 * @since 0.34.0
	 *
	 * @param string $page The current settings page slug.
	 *
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
				array( 'scope' => is_network_admin() ? 'network' : 'blog' ),
				true
			);
		}
	}

	/**
	 * Register the REST routes the Tools tab imports and exports through.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		$scope      = array(
			'type'    => 'string',
			'enum'    => array( 'blog', 'network' ),
			'default' => 'blog',
		);
		$permission = function ( WP_REST_Request $request ): bool {
			return current_user_can( $this->capability_for_scope( (string) $request->get_param( 'scope' ) ) );
		};

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/settings/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => $permission,
				'args'                => array( 'scope' => $scope ),
			)
		);

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/settings/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => $permission,
				'args'                => array(
					'scope'  => $scope,
					'mode'   => array(
						'type'    => 'string',
						'enum'    => array( 'merge', 'replace' ),
						'default' => 'merge',
					),
					'import' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);
	}

	/**
	 * Minimum capability required to act in the given scope.
	 *
	 * @since 0.34.0
	 *
	 * @param string $scope 'network' or 'blog'.
	 *
	 * @return string
	 */
	protected function capability_for_scope( string $scope ): string {
		return 'network' === $scope ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Export the settings from a REST request.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response The export, ready to save as a JSON file.
	 */
	public function export( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			Settings::get_instance()->export_settings( (string) $request->get_param( 'scope' ) )
		);
	}

	/**
	 * Import settings from a REST request.
	 *
	 * Values are sanitized as they are imported, so the file only has to
	 * decode to an object.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error What was imported, or why nothing was.
	 */
	public function import( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = Settings::get_instance()->import_settings(
			(array) $request->get_param( 'import' ),
			(string) $request->get_param( 'mode' ),
			(string) $request->get_param( 'scope' )
		);

		if ( ! $result['success'] ) {
			return new WP_Error(
				'gatherpress_settings_import_failed',
				implode( ' ', $result['warnings'] ),
				array( 'status' => 400 )
			);
		}

		return new WP_REST_Response( $result );
	}
}
