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
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

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
}
