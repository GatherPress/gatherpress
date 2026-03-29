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
				'name'     => 'update_option_gatherpress_settings',
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
			'events',
			Utility::get_hidden_property( $instance, 'main_sub_page' ),
			'Failed to assert main sub page is set to events'
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

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_GET === $type && 'page' === $var_name ) {
					return 'unit-test';
				}

				return null;
			},
			10,
			3
		);

		Utility::invoke_hidden_method( $instance, 'set_current_page' );

		remove_all_filters( 'gatherpress_pre_get_http_input' );

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

		$response = Utility::buffer_and_return(
			array( $instance, 'render_settings_form' ),
			array( 'gatherpress_events' )
		);
		$this->assertStringContainsString(
			'value=\'gatherpress_settings\'',
			$response,
			'Failed to assert settings form rendered.'
		);
	}

	/**
	 * Coverage for render_field with text type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_text(): void {
		$instance = Settings::get_instance();
		$text     = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'option',
				array(
					'field'       => array(
						'type'  => 'text',
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
			'<input id="gatherpress_option" type="text" name="gatherpress_settings[option]" ' .
			'class="regular-text" value="" />',
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
	 * Coverage for render_field with checkbox type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_checkbox(): void {
		$instance = Settings::get_instance();
		$checkbox = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'option',
				array(
					'field'       => array(
						'type'  => 'checkbox',
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
			'<input id="gatherpress_option" type="checkbox" name="gatherpress_settings[option]" value="1"  />',
			$checkbox,
			'Failed to assert that input matches.'
		);
		$this->assertStringContainsString(
			'<input type="hidden" name="gatherpress_settings[option]" value="0" />',
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
	 * Coverage for render_field with number type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_number(): void {
		$instance = Settings::get_instance();
		$text     = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'option',
				array(
					'field' => array(
						'type'    => 'number',
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
			'<input id="gatherpress_option" type="number" name="gatherpress_settings[option]" ' .
			'class="regular-text" value="" min="1" max="5" />',
			$text,
			'Failed to assert that input matches.'
		);
	}

	/**
	 * Coverage for render_field with autocomplete type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_autocomplete(): void {
		$instance     = Settings::get_instance();
		$autocomplete = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'option',
				array(
					'type'  => 'page',
					'label' => 'Select unit test page',
					'limit' => 2,
					'field' => array(
						'type'    => 'autocomplete',
						'options' => array(
							'unit' => 'test',
						),
					),
				),
			)
		);

		$this->assertStringContainsString(
			'<div class="regular-text" data-gatherpress_component_name="autocomplete" ' .
			'data-gatherpress_component_attrs="{&quot;name&quot;:&quot;gatherpress_settings[option]&quot;,' .
			'&quot;option&quot;:&quot;gatherpress_option&quot;,&quot;value&quot;:&quot;[]&quot;,' .
			'&quot;fieldOptions&quot;:{&quot;unit&quot;:&quot;test&quot;}}"></div>',
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
			'gatherpress_settings',
			array(
				'test_option' => $expected,
			)
		);

		$value = $instance->get_value( 'test_option' );

		$this->assertEquals(
			$expected,
			$value,
			'Should return the correct value when all parameters are set'
		);

		delete_option( 'gatherpress_settings' );
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
			'gatherpress_settings',
			array(
				'test_option' => '',
			)
		);

		$value = $instance->get_value( 'test_option' );

		// The actual default value will come from get_flat_default.
		$default_value = $instance->get_flat_default( 'test_option' );

		$this->assertEquals(
			$default_value,
			$value,
			'Should return default value when option is empty'
		);

		delete_option( 'gatherpress_settings' );
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
		$value    = $instance->get_value( 'nonexistent_option' );

		$this->assertEquals(
			$instance->get_flat_default( 'nonexistent_option' ),
			$value,
			'Should handle nonexistent option parameter'
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
		$value    = $instance->get_value( 'nonexistent_option' );

		$this->assertEquals(
			$instance->get_flat_default( 'nonexistent_option' ),
			$value,
			'Should return default value for non-existent option'
		);
	}

	/**
	 * Test getting flat default value with valid structure.
	 *
	 * @since  1.0.0
	 * @covers ::get_flat_default
	 *
	 * @return void
	 */
	public function test_get_flat_default_with_valid_structure(): void {
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

		$value = $instance->get_flat_default( 'test_option' );

		$this->assertEquals(
			$expected,
			$value,
			'Should return correct default value from sub_pages structure'
		);
	}

	/**
	 * Test getting flat default value with nonexistent option.
	 *
	 * @since  1.0.0
	 * @covers ::get_flat_default
	 *
	 * @return void
	 */
	public function test_get_flat_default_with_nonexistent_option(): void {
		$instance = Settings::get_instance();

		$value = $instance->get_flat_default( 'nonexistent_option' );

		$this->assertEmpty(
			$value,
			'Should return empty string for nonexistent option'
		);
	}

	/**
	 * Test getting flat default value with empty option.
	 *
	 * @since  1.0.0
	 * @covers ::get_flat_default
	 *
	 * @return void
	 */
	public function test_get_flat_default_with_empty_option(): void {
		$instance = Settings::get_instance();

		$value = $instance->get_flat_default( '' );

		$this->assertEmpty(
			$value,
			'Should return empty string when option is empty'
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
		$expects  = 'gatherpress_settings[option]';

		$this->assertSame( $expects, $instance->get_name_field( 'option' ) );
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

		$this->assertIsArray( $sub_pages['events'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['rsvp_settings'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['formatting'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['roles'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['credits'], 'Failed to assert sub page is an array.' );
		$this->assertSame(
			'events',
			array_key_first( $sub_pages ),
			'Failed to assert that events is first key.'
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
	 * Test render_field with select type.
	 *
	 * @since  1.0.0
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_select(): void {
		$instance = Settings::get_instance();

		$select = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'option',
				array(
					'field'       => array(
						'type'    => 'select',
						'label'   => 'Unit test',
						'options' => array(
							'items' => array(
								'option1' => 'Option 1',
								'option2' => 'Option 2',
							),
						),
					),
					'description' => 'unit test description',
				),
			)
		);

		$this->assertStringContainsString(
			'Unit test',
			$select,
			'Failed to assert that label matches.'
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
			'events_url' => 'events',
		);

		$new_value = array(
			'events_url' => 'gatherings',
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
			'events_url' => 'events',
		);

		$new_value = array(
			'events_url' => 'events',
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
			'events_url' => 'events',
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
			'events_url' => 'events',
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

		// Ensure no existing option interferes with the merge behavior.
		delete_option( 'gatherpress_settings' );

		$field_type_map = array(
			'checkbox_field'     => 'checkbox',
			'number_field'       => 'number',
			'text_field'         => 'text',
			'select_field'       => 'select',
			'autocomplete_field' => 'autocomplete',
		);

		$callback = $instance->sanitize_page_settings( $field_type_map );

		$input = array(
			'checkbox_field'     => '1',
			'number_field'       => '42',
			'text_field'         => 'Test <strong>text</strong>',
			'select_field'       => 'option1',
			'autocomplete_field' => '[{"id":"3","slug":"test","value":"Test"}]',
		);

		$result = $callback( $input );

		$this->assertTrue(
			$result['checkbox_field'],
			'Failed to assert checkbox was converted to boolean.'
		);

		$this->assertSame(
			42,
			$result['number_field'],
			'Failed to assert number was converted to integer.'
		);

		$this->assertSame(
			'Test text',
			$result['text_field'],
			'Failed to assert text was sanitized.'
		);

		$this->assertSame(
			'option1',
			$result['select_field'],
			'Failed to assert select value was sanitized.'
		);

		$this->assertStringContainsString(
			'"id":3',
			$result['autocomplete_field'],
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

	/**
	 * Test options_page method.
	 *
	 * @covers ::options_page
	 *
	 * @return void
	 */
	public function test_options_page(): void {
		global $submenu;

		// Initialize submenu if not set.
		if ( ! is_array( $submenu ) ) {
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$instance = Settings::get_instance();
		$instance->options_page();

		// Check that submenu was populated.
		$this->assertIsArray( $submenu );
	}

	/**
	 * Test remove_sub_options method.
	 *
	 * @covers ::remove_sub_options
	 *
	 * @return void
	 */
	public function test_remove_sub_options(): void {
		global $submenu;

		// Initialize submenu if not set.
		if ( ! is_array( $submenu ) ) {
			$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$instance = Settings::get_instance();

		// First add submenu pages.
		$instance->options_page();

		// Then remove them (except main).
		$instance->remove_sub_options();

		// Should execute without errors.
		$this->assertTrue( true );
	}

	/**
	 * Test register_settings method.
	 *
	 * @covers ::register_settings
	 *
	 * @return void
	 */
	public function test_register_settings(): void {
		global $wp_registered_settings;

		$instance = Settings::get_instance();
		$instance->register_settings();

		// Check that general settings were registered.
		$this->assertArrayHasKey( 'gatherpress_settings', $wp_registered_settings );
	}

	/**
	 * Test register_settings with empty sections.
	 *
	 * @covers ::register_settings
	 *
	 * @return void
	 */
	public function test_register_settings_with_empty_sections(): void {
		$instance = Settings::get_instance();

		// Mock sub_pages with no sections.
		add_filter(
			'gatherpress_settings',
			function ( $settings ) {
				$settings['test_page'] = array(
					'name'     => 'Test Page',
					'priority' => 99,
				);
				return $settings;
			}
		);

		// Should not throw errors when sections are missing.
		$instance->register_settings();

		$this->assertTrue( true );

		remove_all_filters( 'gatherpress_settings' );
	}

	/**
	 * Test register_settings with section but no options.
	 *
	 * @covers ::register_settings
	 *
	 * @return void
	 */
	public function test_register_settings_with_empty_options(): void {
		$instance = Settings::get_instance();

		// Mock sub_pages with section but no options.
		add_filter(
			'gatherpress_settings',
			function ( $settings ) {
				$settings['test_page_2'] = array(
					'name'     => 'Test Page 2',
					'priority' => 99,
					'sections' => array(
						'test_section' => array(
							'name' => 'Test Section',
						),
					),
				);
				return $settings;
			}
		);

		// Should not throw errors when options are missing.
		$instance->register_settings();

		$this->assertTrue( true );

		remove_all_filters( 'gatherpress_settings' );
	}

	/**
	 * Test register_settings with invalid field type.
	 *
	 * @covers ::register_settings
	 *
	 * @return void
	 */
	public function test_register_settings_with_invalid_field_type(): void {
		$instance = Settings::get_instance();

		// Mock sub_pages with invalid field type.
		add_filter(
			'gatherpress_settings',
			function ( $settings ) {
				$settings['test_page_3'] = array(
					'name'     => 'Test Page 3',
					'priority' => 99,
					'sections' => array(
						'test_section' => array(
							'name'    => 'Test Section',
							'options' => array(
								'test_option' => array(
									'field'  => array(
										'type' => 'nonexistent_method',
									),
									'labels' => array(
										'name' => 'Test Option',
									),
								),
							),
						),
					),
				);
				return $settings;
			}
		);

		// Should not set callback when method doesn't exist.
		$instance->register_settings();

		$this->assertTrue( true );

		remove_all_filters( 'gatherpress_settings' );
	}

	/**
	 * Test register_settings section descriptions and field callbacks render.
	 *
	 * Tests section description rendering and
	 * target code (field callback execution).
	 *
	 * @covers ::register_settings
	 * @covers ::render_settings_form
	 *
	 * @return void
	 */
	public function test_register_settings_renders_section_descriptions_and_fields(): void {
		$instance = Settings::get_instance();

		// Add a test page with a section that has a description and a field.
		add_filter(
			'gatherpress_sub_pages',
			function ( $settings ) {
				$settings['test_page_render'] = array(
					'name'     => 'Test Render Page',
					'priority' => 99,
					'sections' => array(
						'test_section_render' => array(
							'name'        => 'Test Section Render',
							'description' => 'This is a test section description.',
							'options'     => array(
								'test_text_field' => array(
									'field'  => array(
										'type'  => 'text',
										'label' => 'Test Field',
									),
									'labels' => array(
										'name' => 'Test Field Name',
									),
								),
							),
						),
					),
				);
				return $settings;
			}
		);

		// Register the settings (sets up sections and fields with closures).
		$instance->register_settings();

		// Render the settings form which triggers both the section description
		// closure (target code) and field callback closure (target code).
		$output = \PMC\Unit_Test\Utility::buffer_and_return(
			array( $instance, 'render_settings_form' ),
			array( 'gatherpress_test_page_render' )
		);

		// Verify section description was rendered (tests target code).
		$this->assertStringContainsString(
			'This is a test section description.',
			$output,
			'Failed to assert section description was rendered.'
		);
		$this->assertStringContainsString(
			'<p class="description">',
			$output,
			'Failed to assert section description has proper markup.'
		);

		// Verify field was rendered via the closure callback (tests target code).
		$this->assertStringContainsString(
			'Test Field',
			$output,
			'Failed to assert field label was rendered via callback.'
		);
		$this->assertStringContainsString(
			'type="text"',
			$output,
			'Failed to assert text field type was rendered via callback.'
		);

		remove_all_filters( 'gatherpress_sub_pages' );
	}
}
