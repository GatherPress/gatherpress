<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Tools.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Tools;
use PMC\Unit_Test\Base_Ajax;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Tools.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Tools
 */
class Test_Tools extends Base_Ajax {
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
				'name'     => 'wp_ajax_gatherpress_export_settings',
				'priority' => 10,
				'callback' => array( $instance, 'ajax_export' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_ajax_gatherpress_import_settings',
				'priority' => 10,
				'callback' => array( $instance, 'ajax_import' ),
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

		$this->assertSame( 'tools', $slug, 'Failed to assert slug is tools.' );
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

		$this->assertEquals( PHP_INT_MAX - 1, $priority, 'Failed to assert correct priority.' );
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
			array( 'gatherpress_tools' )
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
	 * Coverage for ajax_export with admin permissions.
	 *
	 * @covers ::ajax_export
	 *
	 * @return void
	 */
	public function test_ajax_export(): void {
		// Set up as admin user.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Set a value so export has data.
		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$_POST['nonce'] = wp_create_nonce( 'gatherpress_tools_nonce' );

		$response = $this->do_ajax( 'gatherpress_export_settings' );

		$this->assertTrue( $response->success, 'Failed to assert export was successful.' );
		$this->assertSame( 'google', $response->data->settings->map_platform, 'Failed to assert exported value.' );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for ajax_export permission denied for non-admin.
	 *
	 * @covers ::ajax_export
	 *
	 * @return void
	 */
	public function test_ajax_export_permission_denied(): void {
		// Set up as subscriber (no manage_options).
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$response = $this->do_ajax( 'gatherpress_export_settings' );

		$this->assertFalse( $response->success, 'Failed to assert export was denied.' );
	}

	/**
	 * Coverage for ajax_import with valid data and admin permissions.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']         = wp_create_nonce( 'gatherpress_tools_nonce' );
		$_POST['settings_json'] = wp_json_encode(
			array(
				'version'  => GATHERPRESS_VERSION,
				'settings' => array( 'map_platform' => 'google' ),
			)
		);

		// Mock the HTTP input for import_mode.
		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ): ?string {
				if ( INPUT_POST === $type && 'import_mode' === $var_name ) {
					return 'merge';
				}
				return null;
			},
			10,
			3
		);

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertTrue( $response->success, 'Failed to assert import was successful.' );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for ajax_import permission denied for non-admin.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import_permission_denied(): void {
		// Set up as subscriber (no manage_options).
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertFalse( $response->success, 'Failed to assert import was denied.' );
	}

	/**
	 * Coverage for ajax_import with empty JSON data.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import_empty_json(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce'] = wp_create_nonce( 'gatherpress_tools_nonce' );
		// Deliberately omit settings_json to test the empty check.

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertFalse( $response->success, 'Failed to assert empty JSON was rejected.' );
	}

	/**
	 * Coverage for ajax_import with invalid JSON data.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import_invalid_json(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']         = wp_create_nonce( 'gatherpress_tools_nonce' );
		$_POST['settings_json'] = 'not valid json{';

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertFalse( $response->success, 'Failed to assert invalid JSON was rejected.' );
	}

	/**
	 * Coverage for ajax_import with data that fails import validation.
	 *
	 * Sends valid JSON with no 'settings' key, which passes json_decode
	 * but fails import_settings validation.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import_failure(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST['nonce']         = wp_create_nonce( 'gatherpress_tools_nonce' );
		$_POST['settings_json'] = wp_json_encode( array( 'version' => '1.0.0' ) );
		// No 'settings' key means validation failure.

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ): ?string {
				if ( INPUT_POST === $type && 'import_mode' === $var_name ) {
					return 'merge';
				}
				return null;
			},
			10,
			3
		);

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertFalse( $response->success, 'Failed to assert import failure for missing settings key.' );

		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for ajax_import with invalid import mode defaulting to merge.
	 *
	 * @covers ::ajax_import
	 *
	 * @return void
	 */
	public function test_ajax_import_invalid_mode(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		try {
			// Mock the HTTP input for import_mode with invalid value.
			add_filter(
				'gatherpress_pre_get_http_input',
				static function ( $pre_value, $type, $var_name ): ?string {
					if ( INPUT_POST === $type && 'import_mode' === $var_name ) {
						return 'invalid';
					}
					return null;
				},
				10,
				3
			);

			$_POST['nonce']         = wp_create_nonce( 'gatherpress_tools_nonce' );
			$_POST['settings_json'] = wp_json_encode(
				array(
					'version'  => GATHERPRESS_VERSION,
					'settings' => array( 'map_platform' => 'google' ),
				)
			);

			// Should default to merge and succeed.
			$response = $this->do_ajax( 'gatherpress_import_settings' );

			$this->assertTrue(
				$response->success,
				'Failed to assert import succeeded with invalid mode defaulting to merge. Response: '
				. wp_json_encode( $response )
			);
		} finally {
			remove_all_filters( 'gatherpress_pre_get_http_input' );
			delete_option( 'gatherpress_settings' );
			unset( $_POST['nonce'], $_POST['settings_json'] );
		}
	}

	/**
	 * Coverage for ajax_export in the network scope — reads the network
	 * site option rather than the blog option.
	 *
	 * @covers ::ajax_export
	 * @covers ::resolve_scope
	 * @covers ::capability_for_scope
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_ajax_export_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		update_site_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$_POST['nonce'] = wp_create_nonce( 'gatherpress_tools_nonce' );
		$_POST['scope'] = 'network';

		$response = $this->do_ajax( 'gatherpress_export_settings' );

		$this->assertTrue( $response->success, 'Network-scope export should succeed.' );
		$this->assertSame( 'network', $response->data->scope, 'Response should reflect network scope.' );
		$this->assertSame(
			'google',
			$response->data->settings->map_platform,
			'Exported value should come from the site option.'
		);

		delete_site_option( 'gatherpress_settings' );
		revoke_super_admin( $user_id );
		wp_delete_user( $user_id );
		unset( $_POST['nonce'], $_POST['scope'] );
	}

	/**
	 * Coverage for ajax_import in the network scope — writes to the site
	 * option, not the blog option, and flushes the Network config cache.
	 *
	 * @covers ::ajax_import
	 * @covers ::resolve_scope
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_ajax_import_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $user_id );
		wp_set_current_user( $user_id );

		$payload = array(
			'version'     => '1.0.0',
			'exported_at' => '2026-01-01T00:00:00Z',
			'scope'       => 'network',
			'settings'    => array( 'map_platform' => 'google' ),
		);

		$_POST['nonce']         = wp_create_nonce( 'gatherpress_tools_nonce' );
		$_POST['scope']         = 'network';
		$_POST['settings_json'] = wp_json_encode( $payload );
		$_POST['import_mode']   = 'merge';

		$response = $this->do_ajax( 'gatherpress_import_settings' );

		$this->assertTrue( $response->success, 'Network-scope import should succeed.' );

		$stored = get_site_option( 'gatherpress_settings' );
		$this->assertSame(
			'google',
			$stored['map_platform'] ?? null,
			'Imported value should be written to the site option.'
		);

		delete_site_option( 'gatherpress_settings' );
		revoke_super_admin( $user_id );
		wp_delete_user( $user_id );
		unset( $_POST['nonce'], $_POST['scope'], $_POST['settings_json'], $_POST['import_mode'] );
	}
}
