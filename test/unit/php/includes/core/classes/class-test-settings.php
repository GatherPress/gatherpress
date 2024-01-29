<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Settings;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Settings.
 *
 * @coversDefaultClass \GatherPress\Core\Settings
 */
class Test_Settings extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Settings::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_menu',
				'priority' => 10,
				'callback' => array( $instance, 'options_page' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_head',
				'priority' => 10,
				'callback' => array( $instance, 'remove_sub_options' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_init',
				'priority' => 10,
				'callback' => array( $instance, 'register_settings' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_settings_section',
				'priority' => 10,
				'callback' => array( $instance, 'render_settings_form' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'submenu_file',
				'priority' => 10,
				'callback' => array( $instance, 'select_menu' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for set_main_sub_page method.
	 *
	 * @covers ::set_main_sub_page
	 *
	 * @return void
	 */
	public function test_set_main_sub_page(): void {
		$instance = Settings::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'main_sub_page', '' );
		Utility::invoke_hidden_method( $instance, 'set_main_sub_page' );

		$this->assertSame(
			'general',
			Utility::get_hidden_property( $instance, 'main_sub_page' ),
			'Failed to assert main sub page is set to general'
		);
	}

	/**
	 * Coverage for set_current_page method.
	 *
	 * @covers ::set_current_page
	 *
	 * @return void
	 */
	public function test_set_current_page(): void {
		$instance = Settings::get_instance();

		$this->assertEmpty(
			Utility::get_hidden_property( $instance, 'current_page' ),
			'Failed to assert current_page is empty.'
		);

		$this->mock->input(
			array(
				'GET' => array( 'page' => 'unit-test' ),
			)
		);

		Utility::invoke_hidden_method( $instance, 'set_current_page' );

		$this->assertSame(
			'unit-test',
			Utility::get_hidden_property( $instance, 'current_page' ),
			'Failed to assert current_page is set to unit-test.'
		);
	}

	/**
	 * Coverage for render_settings_form method.
	 *
	 * @covers ::render_settings_form
	 *
	 * @return void
	 */
	public function test_render_settings_form(): void {
		$instance = Settings::get_instance();

		$response = Utility::buffer_and_return( array( $instance, 'render_settings_form' ), array( 'gp_general' ) );
		$this->assertStringContainsString( 'value=\'gp_general\'', $response, 'Failed to assert general form rendered.' );
	}

	/**
	 * Coverage for text method.
	 *
	 * @covers ::text
	 *
	 * @return void
	 */
	public function test_text(): void {
		$instance = Settings::get_instance();
		$text     = Utility::buffer_and_return(
			array( $instance, 'text' ),
			array(
				'sub_page',
				'section',
				'option',
				array(
					'field'       => array(
						'label' => 'Unit test',
					),
					'description' => 'unit test description',
				),
			)
		);

		$this->assertStringContainsString(
			'<label for="gp_option">Unit test</label>',
			$text,
			'Failed to assert that label matches.'
		);
		$this->assertStringContainsString(
			'<input id="gp_option" type="text" name="sub_page[section][option]" class="regular-text" value="" />',
			$text,
			'Failed to assert that input matches.'
		);
		$this->assertStringContainsString(
			'<p class="description">unit test description</p>',
			$text,
			'Failed to assert that description matches.'
		);
	}

	/**
	 * Coverage for checkbox method.
	 *
	 * @covers ::checkbox
	 *
	 * @return void
	 */
	public function test_checkbox(): void {
		$instance = Settings::get_instance();
		$checkbox = Utility::buffer_and_return(
			array( $instance, 'checkbox' ),
			array(
				'sub_page',
				'section',
				'option',
				array(
					'field'       => array(
						'label' => 'Unit test',
					),
					'description' => 'unit test description',
				),
			)
		);

		$this->assertStringContainsString(
			'<label for="gp_option">Unit test</label>',
			$checkbox,
			'Failed to assert that label matches.'
		);
		$this->assertStringContainsString(
			'<input id="gp_option" type="checkbox" name="sub_page[section][option]" value="1"  />',
			$checkbox,
			'Failed to assert that input matches.'
		);
		$this->assertStringContainsString(
			'<input type="hidden" name="sub_page[section][option]" value="0" />',
			$checkbox,
			'Failed to assert that hidden input matches.'
		);
		$this->assertStringContainsString(
			'<p class="description">unit test description</p>',
			$checkbox,
			'Failed to assert that description matches.'
		);
	}

	/**
	 * Coverage for autocomplete method.
	 *
	 * @covers ::autocomplete
	 *
	 * @return void
	 */
	public function test_autocomplete(): void {
		$instance     = Settings::get_instance();
		$autocomplete = Utility::buffer_and_return(
			array( $instance, 'autocomplete' ),
			array(
				'sub_page',
				'section',
				'option',
				array(
					'type'  => 'page',
					'label' => 'Select unit test page',
					'limit' => 2,
					'field' => array(
						'options' => array(
							'unit' => 'test',
						),
					),
				),
			)
		);

		$this->assertStringContainsString(
			'<div class="regular-text" data-gp_component_name="autocomplete" data-gp_component_attrs="{&quot;name&quot;:&quot;sub_page[section][option]&quot;,&quot;option&quot;:&quot;gp_option&quot;,&quot;value&quot;:&quot;[]&quot;,&quot;fieldOptions&quot;:{&quot;unit&quot;:&quot;test&quot;}}"></div>',
			$autocomplete,
			'Failed to assert that markup matches.'
		);
	}

	/**
	 * Coverage for get_name_field method.
	 *
	 * @covers ::get_name_field
	 *
	 * @return void
	 */
	public function test_get_name_field(): void {
		$instance = Settings::get_instance();
		$expects  = 'sub_page[section][option]';

		$this->assertSame( $expects, $instance->get_name_field( 'sub_page', 'section', 'option' ) );
	}

	/**
	 * Coverage for get_sub_pages method.
	 *
	 * @covers ::get_sub_pages
	 *
	 * @return void
	 */
	public function test_get_sub_pages(): void {
		$instance  = Settings::get_instance();
		$sub_pages = $instance->get_sub_pages();

		$this->assertIsArray( $sub_pages['general'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['leadership'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['credits'], 'Failed to assert sub page is an array.' );
		$this->assertSame(
			'general',
			array_key_first( $sub_pages ),
			'Failed to assert that general is first key.'
		);
		$this->assertSame(
			'credits',
			array_key_last( $sub_pages ),
			'Failed to assert that credits is last key.'
		);
	}
}
