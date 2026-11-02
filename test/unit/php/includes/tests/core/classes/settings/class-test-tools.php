<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Tools.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Tools;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_REST_Request;

/**
 * Class Test_Tools.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Tools
 */
class Test_Tools extends Base {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Tools::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'rest_api_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_endpoints' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_settings_section',
				'priority' => 9,
				'callback' => array( $instance, 'settings_section' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Tools::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'tools_settings', $slug, 'Failed to assert slug is tools_settings.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Tools::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Tools', $name, 'Failed to assert name is Tools.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Tools::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( PHP_INT_MAX - 2, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for settings_section rendering on the tools page.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_renders_for_tools_page(): void {
		$instance = Tools::get_instance();
		$response = Utility::buffer_and_return(
			array( $instance, 'settings_section' ),
			array( 'gatherpress_tools_settings' )
		);

		$this->assertFalse(
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) ),
			'Failed to assert gatherpress_settings_section does not have render_settings_form callback.'
		);

		$this->assertNotEmpty( $response, 'Failed to assert markup was returned for tools page.' );
		$this->assertStringContainsString( 'Export Settings', $response, 'Failed to assert export string is present.' );
		$this->assertStringContainsString( 'Import Settings', $response, 'Failed to assert import string is present.' );
	}

	/**
	 * Coverage for settings_section skipping non-tools pages.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_skips_other_pages(): void {
		$instance = Tools::get_instance();
		$response = Utility::buffer_and_return(
			array( $instance, 'settings_section' ),
			array( 'gatherpress_events' )
		);

		$this->assertEmpty( $response, 'Failed to assert no markup was returned for non-tools page.' );
	}

	/**
	 * Coverage for register_endpoints.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_register_endpoints(): void {
		Tools::get_instance()->register_endpoints();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/gatherpress/v1/settings/export', $routes );
		$this->assertArrayHasKey( '/gatherpress/v1/settings/import', $routes );
	}

	/**
	 * Coverage for export with admin permissions.
	 *
	 * @covers ::export
	 * @covers ::capability_for_scope
	 *
	 * @return void
	 */
	public function test_export(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/gatherpress/v1/settings/export' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'blog', $response->get_data()['scope'] );
		$this->assertSame( 'google', $response->get_data()['settings']['map_platform'] );
	}

	/**
	 * Check that a user who can't manage options can't export.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_export_permission_denied(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/gatherpress/v1/settings/export' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Coverage for import with admin permissions.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request(
			$this->import_request(
				array(
					'version'  => GATHERPRESS_VERSION,
					'settings' => array( 'map_platform' => 'google' ),
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( 'map_platform', $response->get_data()['imported'] );
		$this->assertSame( 'google', get_option( 'gatherpress_settings' )['map_platform'] ?? null );
	}

	/**
	 * Check that a user who can't manage options can't import.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_import_permission_denied(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = rest_do_request( $this->import_request( array( 'settings' => array() ) ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Check that the import needs a file, and an import mode it knows.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_import_rejects_missing_file_and_unknown_mode(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			400,
			rest_do_request( new WP_REST_Request( 'POST', '/gatherpress/v1/settings/import' ) )->get_status(),
			'An import without a file should be rejected.'
		);
		$this->assertSame(
			400,
			rest_do_request( $this->import_request( array( 'settings' => array() ), 'invalid' ) )->get_status(),
			'An unknown import mode should be rejected.'
		);
	}

	/**
	 * Check that a file without settings reports why it wasn't imported.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_failure(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( $this->import_request( array( 'version' => '1.0.0' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'gatherpress_settings_import_failed', $response->get_data()['code'] );
		$this->assertSame( 'Invalid import file: missing settings data.', $response->get_data()['message'] );
	}

	/**
	 * Coverage for export in the network scope, which reads the network
	 * site option rather than the blog option.
	 *
	 * @covers ::export
	 * @covers ::capability_for_scope
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_export_network_scope(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		update_site_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/settings/export' );
		$request->set_param( 'scope', 'network' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'network', $response->get_data()['scope'] );
		$this->assertSame( 'google', $response->get_data()['settings']['map_platform'] );

		revoke_super_admin( $user_id );
	}

	/**
	 * Check that a site administrator can't export the network settings.
	 *
	 * @covers ::capability_for_scope
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_export_network_scope_needs_a_super_admin(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'GET', '/gatherpress/v1/settings/export' );
		$request->set_param( 'scope', 'network' );

		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	/**
	 * Coverage for import in the network scope, which writes to the site
	 * option, not the blog option.
	 *
	 * @covers ::import
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_import_network_scope(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$request = $this->import_request(
			array(
				'version'     => '1.0.0',
				'exported_at' => '2026-01-01T00:00:00Z',
				'scope'       => 'network',
				'settings'    => array( 'map_platform' => 'google' ),
			)
		);
		$request->set_param( 'scope', 'network' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			'google',
			get_site_option( 'gatherpress_settings' )['map_platform'] ?? null,
			'Imported value should be written to the site option.'
		);

		revoke_super_admin( $user_id );
	}

	/**
	 * Build an import request the way the Tools tab sends it.
	 *
	 * @param array  $import The import file's contents.
	 * @param string $mode   The import mode.
	 *
	 * @return WP_REST_Request The request.
	 */
	private function import_request( array $import, string $mode = 'merge' ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/gatherpress/v1/settings/import' );

		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'mode'   => $mode,
					'import' => $import,
				)
			)
		);

		return $request;
	}
}
