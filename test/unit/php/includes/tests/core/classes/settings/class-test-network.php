<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Network.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Network;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility as PMC_Utility;
use ReflectionClass;

/**
 * Class Test_Network.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Network
 */
class Test_Network extends Base {
	/**
	 * Reset the Network site option after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_site_option( Network::OPTION_NAME );
		parent::tearDown();
	}

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Network::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'network_admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'register_page' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'network_admin_edit_' . Network::EDIT_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'handle_save' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'network_admin_edit_' . Network::VALUES_EDIT_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'handle_values_save' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_notices',
				'priority' => 10,
				'callback' => array( $instance, 'subsite_inheritance_notice' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_head',
				'priority' => 10,
				'callback' => array( $instance, 'print_inherited_styles' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_default_config.
	 *
	 * @covers ::get_default_config
	 *
	 * @return void
	 */
	public function test_get_default_config(): void {
		$this->assertSame(
			array(
				'enabled'   => false,
				'inherited' => array(),
			),
			Network::get_default_config()
		);
	}

	/**
	 * Coverage for get_config when the site option is empty — returns defaults.
	 *
	 * @covers ::get_config
	 *
	 * @return void
	 */
	public function test_get_config_returns_defaults_when_unset(): void {
		$this->assertSame( Network::get_default_config(), Network::get_config() );
	}

	/**
	 * Coverage for get_config when stored value is not an array — returns defaults.
	 *
	 * @covers ::get_config
	 *
	 * @return void
	 */
	public function test_get_config_returns_defaults_when_stored_is_not_array(): void {
		update_site_option( Network::OPTION_NAME, 'not-an-array' );

		$this->assertSame( Network::get_default_config(), Network::get_config() );
	}

	/**
	 * Coverage for get_config when stored config merges over defaults.
	 *
	 * @covers ::get_config
	 *
	 * @return void
	 */
	public function test_get_config_merges_stored_over_defaults(): void {
		update_site_option(
			Network::OPTION_NAME,
			array(
				'enabled'   => true,
				'inherited' => array( 'date_format' ),
			)
		);

		$this->assertSame(
			array(
				'enabled'   => true,
				'inherited' => array( 'date_format' ),
			),
			Network::get_config()
		);
	}

	/**
	 * Coverage for sanitize with a fully populated valid input.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_valid_input(): void {
		$instance = Network::get_instance();

		$result = $instance->sanitize(
			array(
				'enabled'   => '1',
				'inherited' => array( 'date_format', 'time_format', 'date_format' ),
			)
		);

		$this->assertTrue( $result['enabled'] );
		$this->assertSame( array( 'date_format', 'time_format' ), $result['inherited'] );
	}

	/**
	 * Coverage for sanitize with a non-array input (robustness check).
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_non_array_input_defaults(): void {
		$instance = Network::get_instance();

		$this->assertSame(
			array(
				'enabled'   => false,
				'inherited' => array(),
			),
			$instance->sanitize( 'garbage' )
		);
	}

	/**
	 * Coverage for sanitize with empty strings filtered out of the inherited list.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_filters_empty_keys(): void {
		$instance = Network::get_instance();

		$result = $instance->sanitize(
			array(
				'enabled'   => true,
				'inherited' => array( 'date_format', '', '   ', 'time_format' ),
			)
		);

		$this->assertSame( array( 'date_format', 'time_format' ), $result['inherited'] );
	}

	/**
	 * Coverage for build_field_type_map — uses reflection to invoke the protected
	 * method.
	 *
	 * @covers ::build_field_type_map
	 *
	 * @return void
	 */
	public function test_build_field_type_map(): void {
		$instance   = Network::get_instance();
		$reflection = new ReflectionClass( $instance );
		$method     = $reflection->getMethod( 'build_field_type_map' );
		$method->setAccessible( true );

		$sub_pages = array(
			'events'       => array(
				'name'     => 'Events',
				'sections' => array(
					'date_time'       => array(
						'options' => array(
							'date_format'   => array( 'field' => array( 'type' => 'text' ) ),
							'show_timezone' => array( 'field' => array( 'type' => 'checkbox' ) ),
						),
					),
					'no_options_here' => array(),
				),
			),
			'no_sections'  => array( 'name' => 'Empty' ),
			'skip_section' => array(
				'name'     => 'Skip',
				'sections' => array(
					'empty_section' => array(),
				),
			),
		);

		$this->assertSame(
			array(
				'date_format'   => 'text',
				'show_timezone' => 'checkbox',
			),
			$method->invoke( $instance, $sub_pages )
		);
	}

	/**
	 * Coverage for route_read_to_site_option — always returns the site option
	 * regardless of the pre value.
	 *
	 * @covers ::route_read_to_site_option
	 *
	 * @return void
	 */
	public function test_route_read_to_site_option(): void {
		update_site_option( Settings::OPTION_NAME, array( 'date_format' => 'Y-m-d' ) );

		$instance = Network::get_instance();

		$this->assertSame(
			array( 'date_format' => 'Y-m-d' ),
			$instance->route_read_to_site_option( 'ignored' )
		);

		delete_site_option( Settings::OPTION_NAME );
	}

	/**
	 * Coverage for get_current_tab — falls back to first key when no tab is
	 * supplied, returns the tab when present in the sub-pages list.
	 *
	 * @covers ::get_current_tab
	 *
	 * @return void
	 */
	public function test_get_current_tab(): void {
		$instance   = Network::get_instance();
		$reflection = new ReflectionClass( $instance );
		$method     = $reflection->getMethod( 'get_current_tab' );
		$method->setAccessible( true );

		$sub_pages = array(
			'network' => array(),
			'events'  => array(),
		);

		// No tab in $_GET → falls back to first key.
		unset( $_GET['tab'] );
		$this->assertSame( 'network', $method->invoke( $instance, $sub_pages ) );

		// Valid tab in $_GET.
		$_GET['tab'] = 'events';
		$this->assertSame( 'events', $method->invoke( $instance, $sub_pages ) );

		// Unknown tab in $_GET → falls back to first key.
		$_GET['tab'] = 'nonexistent';
		$this->assertSame( 'network', $method->invoke( $instance, $sub_pages ) );

		unset( $_GET['tab'] );
	}

	/**
	 * Coverage for get_network_sub_pages — drops Tools and injects Network tab.
	 *
	 * @covers ::get_network_sub_pages
	 *
	 * @return void
	 */
	public function test_get_network_sub_pages_drops_tools_and_adds_network(): void {
		$instance   = Network::get_instance();
		$reflection = new ReflectionClass( $instance );
		$method     = $reflection->getMethod( 'get_network_sub_pages' );
		$method->setAccessible( true );

		$filter = static function (): array {
			return array(
				'events' => array(
					'name'     => 'Events',
					'priority' => 0,
					'sections' => array(),
				),
				'tools'  => array(
					'name'     => 'Tools',
					'priority' => 10,
					'sections' => array(),
				),
			);
		};

		add_filter( 'gatherpress_sub_pages', $filter, 100 );
		$result = $method->invoke( $instance );
		remove_filter( 'gatherpress_sub_pages', $filter, 100 );

		$this->assertArrayNotHasKey( 'tools', $result );
		$this->assertArrayHasKey( 'network', $result );
	}

	/**
	 * Coverage for subsite_inheritance_notice on the main site (never shows).
	 *
	 * @covers ::subsite_inheritance_notice
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_subsite_inheritance_notice_bails_on_main_site(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$instance = Network::get_instance();
		$output   = PMC_Utility::buffer_and_return( array( $instance, 'subsite_inheritance_notice' ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Coverage for subsite_inheritance_notice when not on a GatherPress page.
	 *
	 * @covers ::subsite_inheritance_notice
	 *
	 * @return void
	 */
	public function test_subsite_inheritance_notice_bails_on_non_gatherpress_page(): void {
		$instance = Network::get_instance();

		$_GET['page'] = 'something-else';

		$output = PMC_Utility::buffer_and_return( array( $instance, 'subsite_inheritance_notice' ) );

		unset( $_GET['page'] );

		$this->assertSame( '', $output );
	}

	/**
	 * Coverage for print_inherited_styles — only emits on GatherPress pages.
	 *
	 * @covers ::print_inherited_styles
	 *
	 * @return void
	 */
	public function test_print_inherited_styles_bails_on_non_gatherpress_page(): void {
		$instance = Network::get_instance();

		unset( $_GET['page'] );

		$output = PMC_Utility::buffer_and_return( array( $instance, 'print_inherited_styles' ) );

		$this->assertSame( '', $output );
	}

	/**
	 * Coverage for print_inherited_styles — emits a style block on GatherPress pages.
	 *
	 * @covers ::print_inherited_styles
	 *
	 * @return void
	 */
	public function test_print_inherited_styles_emits_on_gatherpress_page(): void {
		$instance     = Network::get_instance();
		$_GET['page'] = 'gatherpress_general';

		$output = PMC_Utility::buffer_and_return( array( $instance, 'print_inherited_styles' ) );

		unset( $_GET['page'] );

		$this->assertStringContainsString( '.gatherpress-field-inherited', $output );
	}

	/**
	 * Coverage for scope_read_filter — registers the pre_option filter.
	 *
	 * @covers ::scope_read_filter
	 *
	 * @return void
	 */
	public function test_scope_read_filter_registers_filter(): void {
		$instance = Network::get_instance();
		$instance->scope_read_filter();

		$this->assertNotFalse(
			has_filter(
				'pre_option_' . Settings::OPTION_NAME,
				array( $instance, 'route_read_to_site_option' )
			)
		);

		remove_filter(
			'pre_option_' . Settings::OPTION_NAME,
			array( $instance, 'route_read_to_site_option' )
		);
	}
}
