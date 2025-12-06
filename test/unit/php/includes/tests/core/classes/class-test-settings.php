<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
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
	 * @covers ::instantiate_classes
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Settings::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'set_main_sub_page' ),
			),
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
				'type'     => 'action',
				'name'     => 'gatherpress_text_after',
				'priority' => 10,
				'callback' => array( $instance, 'datetime_preview' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_text_after',
				'priority' => 10,
				'callback' => array( $instance, 'url_rewrite_preview' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'update_option_gatherpress_general',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_flush_rewrite_rules' ),
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

		$response = Utility::buffer_and_return( array( $instance, 'render_settings_form' ), array( 'gatherpress_general' ) );
		$this->assertStringContainsString( 'value=\'gatherpress_general\'', $response, 'Failed to assert general form rendered.' );
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
			'<label for="gatherpress_option">Unit test</label>',
			$text,
			'Failed to assert that label matches.'
		);
		$this->assertStringContainsString(
			'<input id="gatherpress_option" type="text" name="sub_page[section][option]" class="regular-text" value="" />',
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
	 * Data provider for testing the sanitize_autocomplete method.
	 *
	 * Provides test cases with various input JSON strings and their expected
	 * sanitized outputs for the sanitize_autocomplete method.
	 *
	 * @return array Array of test cases with input and expected output pairs.
	 *               Each case contains:
	 *               - string $input   JSON string to be sanitized
	 *               - string $expects Expected output after sanitization
	 */
	public function data_sanitize_autocomplete(): array {
		return array(
			array(
				'foobar',
				'[]',
			),
			array(
				'[{"id":"3"}]',
				'[{"id":3}]',
			),
			array(
				'[{"id":3,"slug":"unittest","value":"unittest"}]',
				'[{"id":3,"slug":"unittest","value":"unittest"}]',
			),
			array(
				'[{"id":3,"slug":"unittest","value":"unittest","bad":"data"}]',
				'[{"id":3,"slug":"unittest","value":"unittest"}]',
			),
		);
	}

	/**
	 * Tests the sanitize_autocomplete method.
	 *
	 * @covers ::sanitize_autocomplete
	 * @dataProvider data_sanitize_autocomplete
	 *
	 * @param string $input   The JSON string input to sanitize.
	 * @param string $expects The expected sanitized output.
	 * @return void
	 */
	public function test_sanitize_autocomplete( $input, $expects ): void {
		$instance = Settings::get_instance();

		$this->assertSame(
			$expects,
			$instance->sanitize_autocomplete( $input ),
			'Failed to assert that the input is the same as expects.'
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
			'<label for="gatherpress_option">Unit test</label>',
			$checkbox,
			'Failed to assert that label matches.'
		);
		$this->assertStringContainsString(
			'<input id="gatherpress_option" type="checkbox" name="sub_page[section][option]" value="1"  />',
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
	 * Coverage for number method.
	 *
	 * @covers ::number
	 *
	 * @return void
	 */
	public function test_number(): void {
		$instance = Settings::get_instance();
		$text     = Utility::buffer_and_return(
			array( $instance, 'number' ),
			array(
				'sub_page',
				'section',
				'option',
				array(
					'field' => array(
						'label'   => 'Unit test',
						'options' => array(
							'default' => '2',
							'min'     => '1',
							'max'     => '5',
						),
					),
				),
			)
		);

		$this->assertStringContainsString(
			'<label for="gatherpress_option">Unit test</label>',
			$text,
			'Failed to assert that label matches.'
		);
		$this->assertStringContainsString(
			'<input id="gatherpress_option" type="number" name="sub_page[section][option]" class="regular-text" value="" min="1" max="5" />',
			$text,
			'Failed to assert that input matches.'
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
			'<div class="regular-text" data-gatherpress_component_name="autocomplete" data-gatherpress_component_attrs="{&quot;name&quot;:&quot;sub_page[section][option]&quot;,&quot;option&quot;:&quot;gatherpress_option&quot;,&quot;value&quot;:&quot;[]&quot;,&quot;fieldOptions&quot;:{&quot;unit&quot;:&quot;test&quot;}}"></div>',
			$autocomplete,
			'Failed to assert that markup matches.'
		);
	}

	/**
	 * Test getting value with all parameters set.
	 *
	 * @since  1.0.0
	 * @covers ::get_value
	 *
	 * @return void
	 */
	public function test_get_value_with_all_parameters(): void {
		$instance = Settings::get_instance();
		$expected = 'test_value';

		add_option(
			'gatherpress_test_page',
			array(
				'test_section' => array(
					'test_option' => $expected,
				),
			)
		);

		$value = $instance->get_value( 'test_page', 'test_section', 'test_option' );

		$this->assertEquals(
			$expected,
			$value,
			'Should return the correct value when all parameters are set'
		);

		delete_option( 'gatherpress_test_page' );
	}

	/**
	 * Test getting default value when option is empty.
	 *
	 * @since  1.0.0
	 * @covers ::get_value
	 *
	 * @return void
	 */
	public function test_get_default_value_when_empty(): void {
		$instance = Settings::get_instance();

		add_option(
			'gatherpress_test_page',
			array(
				'test_section' => array(
					'test_option' => '',
				),
			)
		);

		$value = $instance->get_value( 'test_page', 'test_section', 'test_option' );

		// The actual default value will come from get_default_value.
		$default_value = $instance->get_default_value( 'test_page', 'test_section', 'test_option' );

		$this->assertEquals(
			$default_value,
			$value,
			'Should return default value when option is empty'
		);

		delete_option( 'gatherpress_test_page' );
	}

	/**
	 * Test getting value with empty section and option.
	 *
	 * @since  1.0.0
	 * @covers ::get_value
	 *
	 * @return void
	 */
	public function test_get_value_with_empty_section_option(): void {
		$instance = Settings::get_instance();
		$value    = $instance->get_value( 'test_page', '', '' );

		$this->assertEquals(
			$instance->get_default_value( 'test_page', '', '' ),
			$value,
			'Should handle empty section and option parameters'
		);
	}

	/**
	 * Test getting value with non-existent sub-page.
	 *
	 * @since  1.0.0
	 * @covers ::get_value
	 *
	 * @return void
	 */
	public function test_get_value_with_non_existent_page(): void {
		$instance = Settings::get_instance();
		$value    = $instance->get_value( 'non_existent_page', 'test_section', 'test_option' );

		$this->assertEquals(
			$instance->get_default_value( 'non_existent_page', 'test_section', 'test_option' ),
			$value,
			'Should return default value for non-existent sub-page'
		);
	}

	/**
	 * Test getting default value with valid structure.
	 *
	 * @since  1.0.0
	 * @covers ::get_default_value
	 *
	 * @return void
	 */
	public function test_get_default_value_with_valid_structure(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		$expected = 'test_default';

		$instance->method( 'get_sub_pages' )
		->willReturn(
			array(
				'test_page' => array(
					'sections' => array(
						'test_section' => array(
							'options' => array(
								'test_option' => array(
									'field' => array(
										'options' => array(
											'default' => $expected,
										),
									),
								),
							),
						),
					),
				),
			)
		);

		$value = $instance->get_default_value( 'gatherpress_test_page', 'test_section', 'test_option' );

		$this->assertEquals(
			$expected,
			$value,
			'Should return correct default value from sub_pages structure'
		);
	}

	/**
	 * Test getting default value with invalid page.
	 *
	 * @since  1.0.0
	 * @covers ::get_default_value
	 *
	 * @return void
	 */
	public function test_get_default_value_with_invalid_page(): void {
		$instance = Settings::get_instance();

		$value = $instance->get_default_value( 'invalid_page', 'test_section', 'test_option' );

		$this->assertEmpty(
			$value,
			'Should return empty string for invalid page'
		);
	}

	/**
	 * Test getting default value with empty parameters.
	 *
	 * @since  1.0.0
	 * @covers ::get_default_value
	 *
	 * @return void
	 */
	public function test_get_default_value_with_empty_parameters(): void {
		$instance = Settings::get_instance();

		$value = $instance->get_default_value( '', '', '' );

		$this->assertEmpty(
			$value,
			'Should return empty string when parameters are empty'
		);
	}

	/**
	 * Tests for the get_options method.
	 *
	 * @since  1.0.0
	 * @covers ::get_options
	 *
	 * @return void
	 */
	public function test_get_options_with_existing_option(): void {
		$instance    = Settings::get_instance();
		$test_option = array( 'test' => 'value' );

		add_option( 'gatherpress_test', $test_option );

		$result = $instance->get_options( 'gatherpress_test' );

		$this->assertEquals(
			$test_option,
			$result,
			'Should return existing option when set and valid'
		);

		delete_option( 'gatherpress_test' );
	}

	/**
	 * Test get_options returns defaults when option empty.
	 *
	 * @since  1.0.0
	 * @covers ::get_options
	 *
	 * @return void
	 */
	public function test_get_options_returns_defaults_when_empty(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_option_defaults' ) )
			->disableOriginalConstructor()
			->getMock();

		$default_options = array( 'default' => 'value' );

		$instance->method( 'get_option_defaults' )
			->willReturn( $default_options );

		$result = $instance->get_options( 'gatherpress_test' );

		$this->assertEquals(
			$default_options,
			$result,
			'Should return defaults when option not set'
		);
	}

	/**
	 * Test get_options returns defaults when option not array.
	 *
	 * @since  1.0.0
	 * @covers ::get_options
	 *
	 * @return void
	 */
	public function test_get_options_returns_defaults_when_not_array(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_option_defaults' ) )
			->disableOriginalConstructor()
			->getMock();

		$default_options = array( 'default' => 'value' );

		add_option( 'gatherpress_test', 'string_value' );

		$instance->method( 'get_option_defaults' )
			->willReturn( $default_options );

		$result = $instance->get_options( 'gatherpress_test' );

		$this->assertEquals(
			$default_options,
			$result,
			'Should return defaults when option is not array'
		);

		delete_option( 'gatherpress_test' );
	}

	/**
	 * Tests for the get_option_defaults method.
	 *
	 * @since  1.0.0
	 * @covers ::get_option_defaults
	 *
	 * @return void
	 */
	public function test_get_option_defaults_with_valid_structure(): void {
		$instance  = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();
		$sub_pages = array(
			'test_page' => array(
				'sections' => array(
					'section_one' => array(
						'options' => array(
							'option_one' => array(
								'default' => 'default_one',
							),
							'option_two' => array(
								'default' => 'default_two',
							),
						),
					),
					'section_two' => array(
						'options' => array(
							'option_three' => array(
								'default' => 'default_three',
							),
						),
					),
				),
			),
		);

		$instance->method( 'get_sub_pages' )
			->willReturn( $sub_pages );

		$expected = array(
			'section_one' => array(
				'option_one' => 'default_one',
				'option_two' => 'default_two',
			),
			'section_two' => array(
				'option_three' => 'default_three',
			),
		);

		$result = $instance->get_option_defaults( 'gatherpress_test_page' );

		$this->assertEquals(
			$expected,
			$result,
			'Should return structured defaults from sub_pages configuration'
		);
	}

	/**
	 * Test getting defaults with invalid section options.
	 *
	 * @since  1.0.0
	 * @covers ::get_option_defaults
	 *
	 * @return void
	 */
	public function test_get_option_defaults_with_invalid_section_options(): void {
		$instance  = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();
		$sub_pages = array(
			'test_page' => array(
				'sections' => array(
					'section_one' => array(
						'options' => 'invalid_options',
					),
				),
			),
		);

		$instance->method( 'get_sub_pages' )
			->willReturn( $sub_pages );

		$result = $instance->get_option_defaults( 'gatherpress_test_page' );

		$this->assertEmpty(
			$result,
			'Should return empty array when section options are invalid'
		);
	}


	/**
	 * Test getting defaults with non-existent page.
	 *
	 * @since  1.0.0
	 * @covers ::get_option_defaults
	 *
	 * @return void
	 */
	public function test_get_option_defaults_with_nonexistent_page(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		$instance->method( 'get_sub_pages' )
			->willReturn( array() );

		$result = $instance->get_option_defaults( 'gatherpress_nonexistent' );

		$this->assertEmpty(
			$result,
			'Should return empty array for non-existent page'
		);
	}

	/**
	 * Test getting defaults with missing default values.
	 *
	 * @since  1.0.0
	 * @covers ::get_option_defaults
	 *
	 * @return void
	 */
	public function test_get_option_defaults_with_missing_defaults(): void {
		$instance  = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();
		$sub_pages = array(
			'test_page' => array(
				'sections' => array(
					'section_one' => array(
						'options' => array(
							'option_one' => array(),
							'option_two' => array(
								'default' => 'has_default',
							),
						),
					),
				),
			),
		);

		$instance->method( 'get_sub_pages' )
			->willReturn( $sub_pages );

		$expected = array(
			'section_one' => array(
				'option_one' => '',
				'option_two' => 'has_default',
			),
		);
		$result   = $instance->get_option_defaults( 'gatherpress_test_page' );

		$this->assertEquals(
			$expected,
			$result,
			'Should handle missing default values correctly'
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

	/**
	 * Coverage for sort_sub_pages_by_priority method.
	 *
	 * @covers ::sort_sub_pages_by_priority
	 *
	 * @return void
	 */
	public function test_sort_sub_pages_by_priority(): void {
		$instance  = Settings::get_instance();
		$sub_pages = $instance->get_sub_pages();

		$this->assertSame(
			-1,
			$instance->sort_sub_pages_by_priority( array( 'priority' => 2 ), array( 'priority' => 42 ) ),
			'Failed to assert that it returns a negative number while the first sub-page has a lower priority.'
		);

		$this->assertSame(
			1,
			$instance->sort_sub_pages_by_priority( array( 'priority' => 42 ), array( 'priority' => 2 ) ),
			'Failed to assert that it returns a positive number while the second sub-page has a lower priority.'
		);

		$this->assertSame(
			0,
			$instance->sort_sub_pages_by_priority( array( 'priority' => 42 ), array( 'priority' => 42 ) ),
			'Failed to assert that it returns 0 while their priorities are equal.'
		);
	}

	/**
	 * Tests for the select_menu method.
	 *
	 * @since  1.0.0
	 * @covers ::select_menu
	 *
	 * @return void
	 */
	public function test_select_menu_with_existing_submenu(): void {
		$instance = Settings::get_instance();
		$submenu  = 'existing_submenu';

		$result = $instance->select_menu( $submenu );

		$this->assertEquals(
			'existing_submenu',
			$result,
			'Should return existing submenu when provided'
		);
	}

	/**
	 * Test select menu with empty submenu and existing page.
	 *
	 * @since  1.0.0
	 * @covers ::select_menu
	 *
	 * @return void
	 */
	public function test_select_menu_with_empty_submenu_and_existing_page(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		Utility::set_and_get_hidden_property( $instance, 'current_page', 'gatherpress_test_page' );
		Utility::set_and_get_hidden_property( $instance, 'main_sub_page', 'main_page' );

		$sub_pages = array(
			'test_page' => array(
				'name' => 'Test Page',
			),
		);

		$instance->method( 'get_sub_pages' )
			->willReturn( $sub_pages );

		$result = $instance->select_menu( '' );

		$this->assertEquals(
			'gatherpress_main_page',
			$result,
			'Should return prefixed main sub page when current page exists'
		);
	}

	/**
	 * Test select menu with empty submenu and non-existent page.
	 *
	 * @since  1.0.0
	 * @covers ::select_menu
	 *
	 * @return void
	 */
	public function test_select_menu_with_empty_submenu_and_nonexistent_page(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		Utility::set_and_get_hidden_property( $instance, 'current_page', 'gatherpress_nonexistent' );

		$sub_pages = array(
			'test_page' => array(
				'name' => 'Test Page',
			),
		);

		$instance->method( 'get_sub_pages' )
			->willReturn( $sub_pages );

		$result = $instance->select_menu( '' );

		$this->assertEquals(
			'',
			$result,
			'Should return empty string when current page does not exist'
		);
	}

	/**
	 * Test select menu with empty submenu and empty sub pages.
	 *
	 * @since  1.0.0
	 * @covers ::select_menu
	 *
	 * @return void
	 */
	public function test_select_menu_with_empty_submenu_and_empty_subpages(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		$instance->method( 'get_sub_pages' )
			->willReturn( array() );

		$result = $instance->select_menu( '' );

		$this->assertEquals(
			'',
			$result,
			'Should return empty string when sub pages are empty'
		);
	}

	/**
	 * Test select field rendering.
	 *
	 * @since  1.0.0
	 * @covers ::select
	 *
	 * @return void
	 */
	public function test_select(): void {
		$instance = Settings::get_instance();

		// Just verify method executes without error.
		$instance->select(
			'gatherpress_sub_page',
			'section',
			'option',
			array(
				'field'       => array(
					'label'   => 'Unit test',
					'options' => array(
						'option1' => 'Option 1',
						'option2' => 'Option 2',
					),
				),
				'description' => 'unit test description',
			)
		);

		$this->assertTrue( true, 'Select method executed successfully.' );
	}

	/**
	 * Test datetime_preview method with date format.
	 *
	 * @since  1.0.0
	 * @covers ::datetime_preview
	 *
	 * @return void
	 */
	public function test_datetime_preview_with_date_format(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'datetime_preview' ),
			array(
				'gatherpress_general[formatting][date_format]',
				'F j, Y',
			)
		);

		$this->assertStringContainsString(
			'datetime-preview',
			$output,
			'Failed to assert preview is rendered for date format.'
		);
	}

	/**
	 * Test datetime_preview method with time format.
	 *
	 * @since  1.0.0
	 * @covers ::datetime_preview
	 *
	 * @return void
	 */
	public function test_datetime_preview_with_time_format(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'datetime_preview' ),
			array(
				'gatherpress_general[formatting][time_format]',
				'g:i a',
			)
		);

		$this->assertStringContainsString(
			'datetime-preview',
			$output,
			'Failed to assert preview is rendered for time format.'
		);
	}

	/**
	 * Test datetime_preview method with non-datetime field.
	 *
	 * @since  1.0.0
	 * @covers ::datetime_preview
	 *
	 * @return void
	 */
	public function test_datetime_preview_with_other_field(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'datetime_preview' ),
			array(
				'gatherpress_general[other][field]',
				'value',
			)
		);

		$this->assertEmpty(
			$output,
			'Failed to assert no preview is rendered for non-datetime field.'
		);
	}

	/**
	 * Test url_rewrite_preview method with events URL.
	 *
	 * @since  1.0.0
	 * @covers ::url_rewrite_preview
	 *
	 * @return void
	 */
	public function test_url_rewrite_preview_with_events(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'url_rewrite_preview' ),
			array(
				'gatherpress_general[urls][events]',
				'events',
			)
		);

		$this->assertStringContainsString(
			'urlrewrite-preview',
			$output,
			'Failed to assert preview is rendered for events URL.'
		);
		$this->assertStringContainsString(
			'sample-event',
			$output,
			'Failed to assert preview contains sample-event.'
		);
	}

	/**
	 * Test url_rewrite_preview method with venues URL.
	 *
	 * @since  1.0.0
	 * @covers ::url_rewrite_preview
	 *
	 * @return void
	 */
	public function test_url_rewrite_preview_with_venues(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'url_rewrite_preview' ),
			array(
				'gatherpress_general[urls][venues]',
				'venues',
			)
		);

		$this->assertStringContainsString(
			'urlrewrite-preview',
			$output,
			'Failed to assert preview is rendered for venues URL.'
		);
		$this->assertStringContainsString(
			'sample-venue',
			$output,
			'Failed to assert preview contains sample-venue.'
		);
	}

	/**
	 * Test url_rewrite_preview method with topics URL.
	 *
	 * @since  1.0.0
	 * @covers ::url_rewrite_preview
	 *
	 * @return void
	 */
	public function test_url_rewrite_preview_with_topics(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'url_rewrite_preview' ),
			array(
				'gatherpress_general[urls][topics]',
				'topics',
			)
		);

		$this->assertStringContainsString(
			'urlrewrite-preview',
			$output,
			'Failed to assert preview is rendered for topics URL.'
		);
		$this->assertStringContainsString(
			'sample-topic-term',
			$output,
			'Failed to assert preview contains sample-topic-term.'
		);
	}

	/**
	 * Test url_rewrite_preview method with other field.
	 *
	 * @since  1.0.0
	 * @covers ::url_rewrite_preview
	 *
	 * @return void
	 */
	public function test_url_rewrite_preview_with_other_field(): void {
		$instance = Settings::get_instance();
		$output   = Utility::buffer_and_return(
			array( $instance, 'url_rewrite_preview' ),
			array(
				'gatherpress_general[other][field]',
				'value',
			)
		);

		$this->assertEmpty(
			$output,
			'Failed to assert no preview is rendered for non-url field.'
		);
	}

	/**
	 * Test maybe_flush_rewrite_rules when URLs change.
	 *
	 * @since  1.0.0
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules_when_urls_change(): void {
		$instance = Settings::get_instance();

		// Set initial rewrite rules.
		add_option( 'rewrite_rules', array( 'test' => 'value' ) );

		$old_value = array(
			'urls' => array(
				'events' => 'events',
			),
		);

		$new_value = array(
			'urls' => array(
				'events' => 'gatherings',
			),
		);

		$instance->maybe_flush_rewrite_rules( $old_value, $new_value );

		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert rewrite rules were deleted when URLs changed.'
		);
	}

	/**
	 * Test maybe_flush_rewrite_rules when URLs are the same.
	 *
	 * @since  1.0.0
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules_when_urls_same(): void {
		$instance = Settings::get_instance();

		// Set initial rewrite rules.
		add_option( 'rewrite_rules', array( 'test' => 'value' ) );

		$old_value = array(
			'urls' => array(
				'events' => 'events',
			),
		);

		$new_value = array(
			'urls' => array(
				'events' => 'events',
			),
		);

		$instance->maybe_flush_rewrite_rules( $old_value, $new_value );

		$this->assertNotFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert rewrite rules were not deleted when URLs stayed same.'
		);

		delete_option( 'rewrite_rules' );
	}

	/**
	 * Test maybe_flush_rewrite_rules when old value has no URLs.
	 *
	 * @since  1.0.0
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules_when_old_value_no_urls(): void {
		$instance = Settings::get_instance();

		// Set initial rewrite rules.
		add_option( 'rewrite_rules', array( 'test' => 'value' ) );

		$old_value = array();

		$new_value = array(
			'urls' => array(
				'events' => 'events',
			),
		);

		$instance->maybe_flush_rewrite_rules( $old_value, $new_value );

		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert rewrite rules were deleted when URLs added.'
		);
	}

	/**
	 * Test maybe_flush_rewrite_rules when new value has no URLs.
	 *
	 * @since  1.0.0
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules_when_new_value_no_urls(): void {
		$instance = Settings::get_instance();

		// Set initial rewrite rules.
		add_option( 'rewrite_rules', array( 'test' => 'value' ) );

		$old_value = array(
			'urls' => array(
				'events' => 'events',
			),
		);

		$new_value = array();

		$instance->maybe_flush_rewrite_rules( $old_value, $new_value );

		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'Failed to assert rewrite rules were deleted when URLs removed.'
		);
	}

	/**
	 * Test sanitize_page_settings callback with various field types.
	 *
	 * @since  1.0.0
	 * @covers ::sanitize_page_settings
	 *
	 * @return void
	 */
	public function test_sanitize_page_settings(): void {
		$instance = Settings::get_instance();

		$sub_page_settings = array(
			'sections' => array(
				'test_section' => array(
					'options' => array(
						'checkbox_field'     => array(
							'field' => array( 'type' => 'checkbox' ),
						),
						'number_field'       => array(
							'field' => array( 'type' => 'number' ),
						),
						'text_field'         => array(
							'field' => array( 'type' => 'text' ),
						),
						'select_field'       => array(
							'field' => array( 'type' => 'select' ),
						),
						'autocomplete_field' => array(
							'field' => array( 'type' => 'autocomplete' ),
						),
					),
				),
			),
		);

		$callback = $instance->sanitize_page_settings( $sub_page_settings );

		$input = array(
			'test_section' => array(
				'checkbox_field'     => '1',
				'number_field'       => '42',
				'text_field'         => 'Test <strong>text</strong>',
				'select_field'       => 'option1',
				'autocomplete_field' => '[{"id":"3","slug":"test","value":"Test"}]',
			),
		);

		$result = $callback( $input );

		$this->assertTrue(
			$result['test_section']['checkbox_field'],
			'Failed to assert checkbox was converted to boolean.'
		);

		$this->assertSame(
			42,
			$result['test_section']['number_field'],
			'Failed to assert number was converted to integer.'
		);

		$this->assertSame(
			'Test text',
			$result['test_section']['text_field'],
			'Failed to assert text was sanitized.'
		);

		$this->assertSame(
			'option1',
			$result['test_section']['select_field'],
			'Failed to assert select value was sanitized.'
		);

		$this->assertStringContainsString(
			'"id":3',
			$result['test_section']['autocomplete_field'],
			'Failed to assert autocomplete was sanitized.'
		);
	}

	/**
	 * Test settings_page method.
	 *
	 * @since  1.0.0
	 * @covers ::settings_page
	 *
	 * @return void
	 */
	public function test_settings_page(): void {
		$instance = Settings::get_instance();

		$output = Utility::buffer_and_return( array( $instance, 'settings_page' ), array() );

		$this->assertStringContainsString(
			'wrap',
			$output,
			'Failed to assert settings page was rendered.'
		);
	}

	/**
	 * Test sort_sub_pages_by_priority with default priorities.
	 *
	 * @since  1.0.0
	 * @covers ::sort_sub_pages_by_priority
	 *
	 * @return void
	 */
	public function test_sort_sub_pages_by_priority_with_defaults(): void {
		$instance = Settings::get_instance();

		// Test with both missing priority (should default to 10).
		$this->assertSame(
			0,
			$instance->sort_sub_pages_by_priority( array(), array() ),
			'Failed to assert equal priorities with defaults.'
		);
	}
}
