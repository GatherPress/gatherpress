<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Settings;
use GatherPress\Core\Utility as GatherPress_Utility;
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
			array(
				'type'     => 'filter',
				'name'     => 'block_editor_settings_all',
				'priority' => 10,
				'callback' => array( $instance, 'add_editor_settings' ),
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
	 * Coverage for add_editor_settings method.
	 *
	 * @covers ::add_editor_settings
	 *
	 * @return void
	 */
	public function test_add_editor_settings(): void {
		$instance = Settings::get_instance();
		$settings = $instance->add_editor_settings( array() );

		$this->assertArrayHasKey(
			'gatherpress',
			$settings,
			'Failed to assert gatherpress key exists in editor settings.'
		);
		$this->assertArrayHasKey(
			'settings',
			$settings['gatherpress'],
			'Failed to assert settings key exists in gatherpress editor settings.'
		);

		// Verify all defaults map keys are present as camelCase.
		$defaults_map = Utility::invoke_hidden_method( $instance, 'get_defaults_map' );

		foreach ( array_keys( $defaults_map ) as $option ) {
			$camel_key = GatherPress_Utility::snake_to_camel( $option );
			$this->assertArrayHasKey(
				$camel_key,
				$settings['gatherpress']['settings'],
				sprintf( 'Failed to assert %s key exists in editor settings.', $camel_key )
			);
		}

		$this->assertNotEmpty(
			$settings['gatherpress']['settings'],
			'Failed to assert editor settings are not empty.'
		);

		// Verify config keys are separate from settings.
		$this->assertArrayHasKey(
			'config',
			$settings['gatherpress'],
			'Failed to assert config key exists in gatherpress editor settings.'
		);

		$expected_config_keys = array(
			'timezoneChoices',
			'siteTimezone',
			'pluginUrl',
			'homeUrl',
			'mapTileUrl',
			'mapTileAttribution',
		);

		foreach ( $expected_config_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$settings['gatherpress']['config'],
				sprintf( 'Failed to assert %s key exists in editor config.', $key )
			);
			$this->assertArrayNotHasKey(
				$key,
				$settings['gatherpress']['settings'],
				sprintf( 'Failed to assert %s is not in settings (should be in config).', $key )
			);
		}

		// Verify it preserves existing gatherpress keys.
		$existing = array(
			'gatherpress' => array(
				'customKey' => 'customValue',
			),
		);
		$merged   = $instance->add_editor_settings( $existing );

		$this->assertSame(
			'customValue',
			$merged['gatherpress']['customKey'],
			'Failed to assert existing gatherpress keys are preserved.'
		);
		$this->assertArrayHasKey(
			'settings',
			$merged['gatherpress'],
			'Failed to assert settings were added alongside existing gatherpress keys.'
		);
		$this->assertArrayHasKey(
			'config',
			$merged['gatherpress'],
			'Failed to assert config were added alongside existing gatherpress keys.'
		);
	}

	/**
	 * Default map tile URL matches the CartoDB Positron template and is filterable.
	 *
	 * @covers ::get_map_tile_url
	 *
	 * @return void
	 */
	public function test_get_map_tile_url_default_and_filter(): void {
		$this->assertSame( Settings::MAP_TILE_URL, Settings::get_map_tile_url() );

		add_filter(
			'gatherpress_map_tile_url',
			static function (): string {
				return 'https://tiles.example.com/{z}/{x}/{y}.png';
			}
		);

		$this->assertSame(
			'https://tiles.example.com/{z}/{x}/{y}.png',
			Settings::get_map_tile_url(),
			'Filter should replace the default tile URL.'
		);

		remove_all_filters( 'gatherpress_map_tile_url' );

		// An empty filter value falls back to the default rather than breaking Leaflet.
		add_filter( 'gatherpress_map_tile_url', '__return_empty_string' );
		$this->assertSame( Settings::MAP_TILE_URL, Settings::get_map_tile_url() );
		remove_all_filters( 'gatherpress_map_tile_url' );
	}

	/**
	 * Default map attribution is filterable.
	 *
	 * @covers ::get_map_tile_attribution
	 *
	 * @return void
	 */
	public function test_get_map_tile_attribution_default_and_filter(): void {
		$this->assertSame( Settings::MAP_TILE_ATTRIBUTION, Settings::get_map_tile_attribution() );

		add_filter(
			'gatherpress_map_tile_attribution',
			static function (): string {
				return 'Custom attribution';
			}
		);

		$this->assertSame( 'Custom attribution', Settings::get_map_tile_attribution() );

		remove_all_filters( 'gatherpress_map_tile_attribution' );
	}

	/**
	 * Coverage for get_main_sub_page method.
	 *
	 * @covers ::get_main_sub_page
	 *
	 * @return void
	 */
	public function test_get_main_sub_page(): void {
		$instance = Settings::get_instance();

		$this->assertSame(
			'events',
			$instance->get_main_sub_page(),
			'Failed to assert main sub page is events.'
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
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_with_all_parameters(): void {
		$instance = Settings::get_instance();
		$expected = 'test_value';

		add_option(
			'gatherpress_settings',
			array(
				'test_option' => $expected,
			)
		);

		$value = $instance->get( 'test_option' );

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
	 * @covers ::get
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

		$value = $instance->get( 'test_option' );

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
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_with_empty_section_option(): void {
		$instance = Settings::get_instance();
		$value    = $instance->get( 'nonexistent_option' );

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
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_with_non_existent_page(): void {
		$instance = Settings::get_instance();
		$value    = $instance->get( 'nonexistent_option' );

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

	/**
	 * Coverage for export_settings.
	 *
	 * @covers ::export_settings
	 *
	 * @return void
	 */
	public function test_export_settings(): void {
		$instance = Settings::get_instance();

		// Set a value so export isn't empty.
		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$export = $instance->export_settings();

		$this->assertArrayHasKey( 'version', $export );
		$this->assertArrayHasKey( 'exported_at', $export );
		$this->assertArrayHasKey( 'settings', $export );
		$this->assertSame( 'google', $export['settings']['map_platform'] );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for export_settings with empty settings.
	 *
	 * @covers ::export_settings
	 *
	 * @return void
	 */
	public function test_export_settings_empty(): void {
		$instance = Settings::get_instance();

		delete_option( 'gatherpress_settings' );

		$export = $instance->export_settings();

		$this->assertEmpty( $export['settings'] );
	}

	/**
	 * Coverage for validate_import with valid data.
	 *
	 * @covers ::validate_import
	 *
	 * @return void
	 */
	public function test_validate_import_valid(): void {
		$instance = Settings::get_instance();

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array(
				'map_platform' => 'google',
			),
		);

		$result = $instance->validate_import( $data );

		$this->assertTrue( $result['valid'] );
		$this->assertContains( 'map_platform', $result['changes'] );
		$this->assertEmpty( $result['unknown'] );
	}

	/**
	 * Coverage for validate_import with missing settings key.
	 *
	 * @covers ::validate_import
	 *
	 * @return void
	 */
	public function test_validate_import_missing_settings(): void {
		$instance = Settings::get_instance();

		$result = $instance->validate_import( array( 'version' => '1.0.0' ) );

		$this->assertFalse( $result['valid'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Coverage for validate_import with unknown keys.
	 *
	 * @covers ::validate_import
	 *
	 * @return void
	 */
	public function test_validate_import_unknown_keys(): void {
		$instance = Settings::get_instance();

		$data = array(
			'settings' => array(
				'unknown_key' => 'value',
			),
		);

		$result = $instance->validate_import( $data );

		$this->assertTrue( $result['valid'] );
		$this->assertContains( 'unknown_key', $result['unknown'] );
	}

	/**
	 * Coverage for validate_import with version mismatch.
	 *
	 * @covers ::validate_import
	 *
	 * @return void
	 */
	public function test_validate_import_version_mismatch(): void {
		$instance = Settings::get_instance();

		$data = array(
			'version'  => '0.0.1',
			'settings' => array(
				'map_platform' => 'google',
			),
		);

		$result = $instance->validate_import( $data );

		$this->assertTrue( $result['valid'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Coverage for import_settings with merge mode.
	 *
	 * @covers ::import_settings
	 *
	 * @return void
	 */
	public function test_import_settings_merge(): void {
		$instance = Settings::get_instance();

		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array(
				'max_attendance_limit' => 100,
			),
		);

		$result = $instance->import_settings( $data, 'merge' );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'max_attendance_limit', $result['imported'] );

		$settings = get_option( 'gatherpress_settings' );
		$this->assertSame( 'google', $settings['map_platform'], 'Existing value should be preserved in merge.' );
		$this->assertSame( 100, $settings['max_attendance_limit'], 'Imported value should be set.' );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import_settings with replace mode.
	 *
	 * @covers ::import_settings
	 *
	 * @return void
	 */
	public function test_import_settings_replace(): void {
		$instance = Settings::get_instance();

		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array(
				'max_attendance_limit' => 100,
			),
		);

		$result = $instance->import_settings( $data, 'replace' );

		$this->assertTrue( $result['success'] );

		$settings = get_option( 'gatherpress_settings' );
		$this->assertArrayNotHasKey( 'map_platform', $settings, 'Old value should be gone in replace.' );
		$this->assertSame( 100, $settings['max_attendance_limit'], 'Imported value should be set.' );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import_settings with invalid data.
	 *
	 * @covers ::import_settings
	 *
	 * @return void
	 */
	public function test_import_settings_invalid(): void {
		$instance = Settings::get_instance();

		$result = $instance->import_settings( array( 'version' => '1.0.0' ) );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['warnings'] );
	}

	/**
	 * Coverage for import_settings skipping unknown keys.
	 *
	 * @covers ::import_settings
	 *
	 * @return void
	 */
	public function test_import_settings_skips_unknown_keys(): void {
		$instance = Settings::get_instance();

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array(
				'unknown_key'  => 'value',
				'map_platform' => 'google',
			),
		);

		$result = $instance->import_settings( $data );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'unknown_key', $result['skipped'] );
		$this->assertContains( 'map_platform', $result['imported'] );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for get_defaults_map.
	 *
	 * @covers ::get_defaults_map
	 *
	 * @return void
	 */
	public function test_get_defaults_map(): void {
		$instance = Settings::get_instance();

		$defaults = Utility::invoke_hidden_method( $instance, 'get_defaults_map' );

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'map_platform', $defaults );
		$this->assertSame( 'osm', $defaults['map_platform'] );
	}

	/**
	 * Coverage for get_rewrite_keys.
	 *
	 * @covers ::get_rewrite_keys
	 *
	 * @return void
	 */
	public function test_get_rewrite_keys(): void {
		$instance = Settings::get_instance();

		$keys = Utility::invoke_hidden_method( $instance, 'get_rewrite_keys' );

		$this->assertIsArray( $keys );
		$this->assertContains( 'events_url', $keys );
		$this->assertContains( 'venues_url', $keys );
		$this->assertContains( 'topics_url', $keys );
	}

	/**
	 * Coverage for build_field_type_map.
	 *
	 * @covers ::build_field_type_map
	 *
	 * @return void
	 */
	public function test_build_field_type_map(): void {
		$instance  = Settings::get_instance();
		$sub_pages = $instance->get_sub_pages();

		$map = Utility::invoke_hidden_method( $instance, 'build_field_type_map', array( $sub_pages ) );

		$this->assertIsArray( $map );
		$this->assertSame( 'select', $map['map_platform'] );
		$this->assertSame( 'checkbox', $map['post_or_event_date'] );
		$this->assertSame( 'number', $map['max_attendance_limit'] );
		$this->assertSame( 'text', $map['date_format'] );
		$this->assertSame( 'autocomplete', $map['organizer'] );
	}

	/**
	 * Coverage for render_field with unknown type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_with_unknown_type(): void {
		$instance = Settings::get_instance();

		// An unknown type should attempt to render a template that doesn't exist.
		// Utility::render_template handles missing templates gracefully.
		$output = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'test_option',
				array(
					'field' => array(
						'type' => 'nonexistent',
					),
				),
			)
		);

		// Should produce empty output since the template doesn't exist.
		$this->assertEmpty( $output, 'Unknown field type should produce no output.' );
	}

	/**
	 * Test register_settings skips a sub-page that has no sections key.
	 *
	 * @covers ::register_settings
	 *
	 * @return void
	 */
	public function test_register_settings_skip_sub_page_without_sections(): void {
		$instance = Settings::get_instance();

		add_filter(
			'gatherpress_sub_pages',
			static function ( $sub_pages ) {
				// Add a sub-page with no 'sections' key alongside valid ones.
				$sub_pages['no_sections_page'] = array(
					'name'     => 'No Sections Page',
					'priority' => 99,
				);
				return $sub_pages;
			}
		);

		// Should not error when a sub-page has no sections.
		$instance->register_settings();

		$this->assertTrue(
			true,
			'register_settings should handle sub-pages without sections gracefully.'
		);

		remove_all_filters( 'gatherpress_sub_pages' );
	}

	/**
	 * Test build_field_type_map guard clauses for missing sections and options.
	 *
	 * @covers ::build_field_type_map
	 *
	 * @return void
	 */
	public function test_build_field_type_map_guard_clauses(): void {
		$instance = Settings::get_instance();

		$sub_pages = array(
			'no_sections' => array( 'name' => 'Test' ),
			'no_options'  => array(
				'sections' => array(
					'section1' => array( 'name' => 'Test Section' ),
				),
			),
			'valid'       => array(
				'sections' => array(
					'section1' => array(
						'options' => array(
							'test_key' => array( 'field' => array( 'type' => 'text' ) ),
						),
					),
				),
			),
		);

		$map = Utility::invoke_hidden_method( $instance, 'build_field_type_map', array( $sub_pages ) );

		$this->assertSame(
			array( 'test_key' => 'text' ),
			$map,
			'Should only contain option from the valid sub-page.'
		);
	}

	/**
	 * Test build_field_type_map detects duplicate keys and registers admin notice.
	 *
	 * @covers ::build_field_type_map
	 *
	 * @return void
	 */
	public function test_build_field_type_map_duplicate_keys(): void {
		$instance = Settings::get_instance();

		$sub_pages = array(
			'page1' => array(
				'sections' => array(
					's1' => array(
						'options' => array(
							'dupe_key' => array( 'field' => array( 'type' => 'text' ) ),
						),
					),
				),
			),
			'page2' => array(
				'sections' => array(
					's2' => array(
						'options' => array(
							'dupe_key' => array( 'field' => array( 'type' => 'checkbox' ) ),
						),
					),
				),
			),
		);

		Utility::invoke_hidden_method( $instance, 'build_field_type_map', array( $sub_pages ) );

		$this->assertNotFalse(
			has_action( 'admin_notices' ),
			'An admin notice should be registered for duplicate keys.'
		);

		// Capture the admin notice output.
		ob_start();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'admin_notices' );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'dupe_key',
			$output,
			'Admin notice should mention the duplicate key.'
		);
	}

	/**
	 * Test sanitize_page_settings strips values that match their defaults.
	 *
	 * @covers ::sanitize_page_settings
	 *
	 * @return void
	 */
	public function test_sanitize_page_settings_strips_defaults(): void {
		$instance = Settings::get_instance();

		// Ensure no existing option interferes.
		delete_option( 'gatherpress_settings' );

		// Use a real registered key with its actual default value.
		$field_type_map = array(
			'map_platform' => 'select',
		);

		$callback = $instance->sanitize_page_settings( $field_type_map );

		// Pass the default value 'osm' for map_platform.
		$result = $callback( array( 'map_platform' => 'osm' ) );

		$this->assertArrayNotHasKey(
			'map_platform',
			$result,
			'Value matching default should be stripped from the result.'
		);

		// Now pass a non-default value.
		$result = $callback( array( 'map_platform' => 'google' ) );

		$this->assertArrayHasKey(
			'map_platform',
			$result,
			'Value not matching default should remain in the result.'
		);
		$this->assertSame(
			'google',
			$result['map_platform'],
			'Non-default value should be preserved.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Test get_defaults_map builds and caches defaults.
	 *
	 * @covers ::get_defaults_map
	 *
	 * @return void
	 */
	public function test_get_defaults_map_cache_building(): void {
		$instance = Settings::get_instance();

		// Reset the cache to force a rebuild.
		Utility::set_and_get_hidden_property( $instance, 'defaults_cache', null );

		$defaults = Utility::invoke_hidden_method( $instance, 'get_defaults_map' );

		$this->assertArrayHasKey(
			'map_platform',
			$defaults,
			'Defaults should contain map_platform.'
		);
		$this->assertSame(
			'osm',
			$defaults['map_platform'],
			'Default for map_platform should be osm.'
		);

		// Verify cache is set.
		$cached = Utility::get_hidden_property( $instance, 'defaults_cache' );
		$this->assertSame(
			$defaults,
			$cached,
			'Defaults cache should match the returned defaults.'
		);
	}

	/**
	 * Test get_defaults_map guard clauses with missing sections and options.
	 *
	 * @covers ::get_defaults_map
	 *
	 * @return void
	 */
	public function test_get_defaults_map_guard_clauses(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		// Reset the cache to force a rebuild.
		Utility::set_and_get_hidden_property( $instance, 'defaults_cache', null );

		$instance->method( 'get_sub_pages' )->willReturn(
			array(
				'no_sections' => array( 'name' => 'Test' ),
				'no_options'  => array(
					'sections' => array(
						'section1' => array( 'name' => 'Test' ),
					),
				),
			)
		);

		$defaults = Utility::invoke_hidden_method( $instance, 'get_defaults_map' );

		$this->assertEmpty(
			$defaults,
			'Defaults should be empty when sub-pages have no sections or options.'
		);
	}

	/**
	 * Test get_rewrite_keys guard clauses with missing sections and options.
	 *
	 * @covers ::get_rewrite_keys
	 *
	 * @return void
	 */
	public function test_get_rewrite_keys_guard_clauses(): void {
		$instance = $this->getMockBuilder( Settings::class )
			->setMethods( array( 'get_sub_pages' ) )
			->disableOriginalConstructor()
			->getMock();

		$instance->method( 'get_sub_pages' )->willReturn(
			array(
				'no_sections' => array( 'name' => 'Test' ),
				'no_options'  => array(
					'sections' => array(
						'section1' => array( 'name' => 'Test' ),
					),
				),
			)
		);

		$keys = Utility::invoke_hidden_method( $instance, 'get_rewrite_keys' );

		$this->assertEmpty(
			$keys,
			'Rewrite keys should be empty when sub-pages have no sections or options.'
		);
	}

	/**
	 * Test set method stores a non-default value.
	 *
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_set(): void {
		$instance = Settings::get_instance();

		delete_option( 'gatherpress_settings' );

		$instance->set( 'map_platform', 'google' );

		$options = get_option( 'gatherpress_settings' );

		$this->assertSame(
			'google',
			$options['map_platform'],
			'Failed to assert value was set.'
		);

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Test set method removes value when it matches default.
	 *
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_set_strips_default(): void {
		$instance = Settings::get_instance();

		// Set a non-default value first.
		$instance->set( 'map_platform', 'google' );

		// Now set it back to the default.
		$instance->set( 'map_platform', 'osm' );

		$options = get_option( 'gatherpress_settings', array() );

		$this->assertArrayNotHasKey(
			'map_platform',
			$options,
			'Failed to assert default value was stripped.'
		);

		delete_option( 'gatherpress_settings' );
	}
}
