<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Credits;
use GatherPress\Core\Settings\Events;
use GatherPress\Core\Settings\Network;
use GatherPress\Core\Settings\Roles;
use GatherPress\Core\Settings\Rsvp;
use GatherPress\Core\Settings\Tools;
use GatherPress\Core\Settings\Venues;
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
	 * Settings now owns the instantiation of every settings-page subclass
	 * (Credits, Events, Network, Roles, Rsvp, Tools, Venues) so
	 * `Setup::instantiate_classes()` can hand off with a single
	 * `Settings::get_instance()` call. Checks the subclass registrations
	 * landed — `Credits` is the proxy: its constructor wires an
	 * `admin_init` action, and that action is only present if
	 * `Settings::instantiate_classes()` instantiated it.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_registers_subclasses(): void {
		// Force the method to run inside the test's coverage window —
		// Settings is a singleton cached during plugin bootstrap, so
		// `get_instance()` here just returns the cached instance and
		// doesn't re-fire the constructor.
		Utility::invoke_hidden_method( Settings::get_instance(), 'instantiate_classes' );

		// Asserting per-subclass proof-of-construction catches the case
		// where a single subclass silently drops out of
		// `Settings::instantiate_classes()` — testing only one subclass
		// (Credits, say) lets Tools / Venues regressions slip through.
		// Most subclasses inherit `Settings\Base`'s `admin_init → init`
		// wiring; `Network` overrides `setup_hooks()` and uses
		// `network_admin_menu → register_page` instead.
		$expected_hooks = array(
			Credits::class => array( 'admin_init', array( Credits::get_instance(), 'init' ) ),
			Events::class  => array( 'admin_init', array( Events::get_instance(), 'init' ) ),
			Network::class => array( 'network_admin_menu', array( Network::get_instance(), 'register_page' ) ),
			Roles::class   => array( 'admin_init', array( Roles::get_instance(), 'init' ) ),
			Rsvp::class    => array( 'admin_init', array( Rsvp::get_instance(), 'init' ) ),
			Tools::class   => array( 'admin_init', array( Tools::get_instance(), 'init' ) ),
			Venues::class  => array( 'admin_init', array( Venues::get_instance(), 'init' ) ),
		);

		foreach ( $expected_hooks as $class_name => $expected ) {
			list( $hook, $callback ) = $expected;
			$this->assertSame(
				10,
				has_action( $hook, $callback ),
				sprintf( '%s must be instantiated so its %s hook registers.', $class_name, $hook )
			);
		}
	}

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
			'events_settings',
			Utility::get_hidden_property( $instance, 'main_sub_page' ),
			'Failed to assert main sub page is set to events_settings'
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
			'venuesMapsSettingsUrl',
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
			'gatherpress_interactive_map_tile_url',
			static function (): string {
				return 'https://tiles.example.com/{z}/{x}/{y}.png';
			}
		);

		$this->assertSame(
			'https://tiles.example.com/{z}/{x}/{y}.png',
			Settings::get_map_tile_url(),
			'Filter should replace the default tile URL.'
		);

		remove_all_filters( 'gatherpress_interactive_map_tile_url' );

		// An empty filter value falls back to the default rather than breaking Leaflet.
		add_filter( 'gatherpress_interactive_map_tile_url', '__return_empty_string' );
		$this->assertSame( Settings::MAP_TILE_URL, Settings::get_map_tile_url() );
		remove_all_filters( 'gatherpress_interactive_map_tile_url' );
	}

	/**
	 * Default map attribution is filterable.
	 *
	 * @covers ::get_map_tile_attribution
	 *
	 * @return void
	 */
	public function test_get_map_tile_attribution_default_and_filter(): void {
		// Default is built via sprintf() + __() so the "contributors" word is translatable;
		// assert it contains both provider credits rather than matching a frozen string.
		$default = Settings::get_map_tile_attribution();
		$this->assertStringContainsString( 'OpenStreetMap', $default );
		$this->assertStringContainsString( Settings::MAP_TILE_ATTRIBUTION_OSM_URL, $default );
		$this->assertStringContainsString( 'CARTO', $default );
		$this->assertStringContainsString( Settings::MAP_TILE_ATTRIBUTION_CARTO_URL, $default );

		add_filter(
			'gatherpress_interactive_map_tile_attribution',
			static function (): string {
				return 'Custom attribution';
			}
		);

		$this->assertSame( 'Custom attribution', Settings::get_map_tile_attribution() );

		remove_all_filters( 'gatherpress_interactive_map_tile_attribution' );
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
			'events_settings',
			$instance->get_main_sub_page(),
			'Failed to assert main sub page is events_settings.'
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
	 * Coverage for render_field with password type.
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_password(): void {
		$instance = Settings::get_instance();
		$html     = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'api_key_test',
				array(
					'field'       => array(
						'type'  => 'password',
						'label' => 'API key',
						'size'  => 'regular',
					),
					'description' => 'Keep this referrer-restricted.',
				),
			)
		);

		$this->assertStringContainsString(
			'type="password"',
			$html,
			'Failed to assert password input type.'
		);
		$this->assertStringContainsString(
			'autocomplete="off"',
			$html,
			'Failed to assert autocomplete is off.'
		);
	}

	/**
	 * Google Maps API key field renders as a plain text input (key is already exposed to the browser for embeds).
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_google_maps_api_key_uses_text_input(): void {
		$instance = Settings::get_instance();
		update_option(
			Settings::OPTION_NAME,
			array( 'map_platform' => 'osm' )
		);

		$html = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'google_maps_api_key',
				array(
					'field'       => array(
						'type'  => 'text',
						'label' => 'Google Maps API key:',
						'size'  => 'large',
					),
					'description' => 'Test description.',
				),
			)
		);

		$this->assertStringContainsString(
			'type="text"',
			$html,
			'Failed to assert API key field uses text input.'
		);
		$this->assertStringNotContainsString(
			'gatherpress-settings-google-api-key',
			$html,
			'Failed to assert no visibility wrapper is emitted.'
		);

		delete_option( Settings::OPTION_NAME );
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
	 *
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
			'&quot;fieldOptions&quot;:{&quot;unit&quot;:&quot;test&quot;},&quot;disabled&quot;:false}"></div>',
			$autocomplete,
			'Failed to assert that markup matches.'
		);
	}

	/**
	 * Test getting value with all parameters set.
	 *
	 * @since  0.34.0
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
	 * @since  0.33.0
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
	 * @since  0.34.0
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
	 * @since  0.34.0
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
	 * @since  0.34.0
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
	 * @since  0.34.0
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
	 * @since  0.34.0
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

		$this->assertIsArray( $sub_pages['events_settings'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['rsvp_settings'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['venues_settings'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['roles_settings'], 'Failed to assert sub page is an array.' );
		$this->assertIsArray( $sub_pages['credits_settings'], 'Failed to assert sub page is an array.' );
		$this->assertSame(
			'events_settings',
			array_key_first( $sub_pages ),
			'Failed to assert that events_settings is first key.'
		);
		$this->assertSame(
			'credits_settings',
			array_key_last( $sub_pages ),
			'Failed to assert that credits_settings is last key.'
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.34.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * @since  0.33.0
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
			'password_field'     => 'password',
			'select_field'       => 'select',
			'autocomplete_field' => 'autocomplete',
		);

		$callback = $instance->sanitize_page_settings( $field_type_map );

		$input = array(
			'checkbox_field'     => '1',
			'number_field'       => '42',
			'text_field'         => 'Test <strong>text</strong>',
			'password_field'     => 'secret-key-value',
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
			'secret-key-value',
			$result['password_field'],
			'Failed to assert password field was sanitized as text.'
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
	 * Empty-string submissions for number fields round-trip as '' instead
	 * of getting coerced to 0 via intval — a field that accepts empty (e.g.
	 * Width/Height "Auto") would otherwise silently save 0 on every post.
	 * Explicit "0" submissions must still be kept as int 0, distinct from
	 * empty, so callers can tell "unset" from "set to zero".
	 *
	 * @since 0.34.0
	 * @covers ::sanitize_page_settings
	 *
	 * @return void
	 */
	public function test_sanitize_page_settings_preserves_empty_number(): void {
		$instance = Settings::get_instance();

		delete_option( 'gatherpress_settings' );

		$callback = $instance->sanitize_page_settings(
			array(
				'empty_number' => 'number',
				'zero_number'  => 'number',
			)
		);

		$result = $callback(
			array(
				'empty_number' => '',
				'zero_number'  => '0',
			)
		);

		// Explicit "0" is preserved as int 0 (distinct from empty).
		$this->assertSame( 0, $result['zero_number'], 'Explicit "0" is kept as int 0.' );

		// Empty string matches the '' default for an unregistered option
		// and gets stripped from the saved array — effectively "unset",
		// which is the desired auto sentinel.
		$this->assertArrayNotHasKey(
			'empty_number',
			$result,
			'Empty submission is stripped (matches "" default).'
		);
	}

	/**
	 * Test settings_page method.
	 *
	 * @since  0.33.0
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
	 * @since  0.33.0
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
	 * Direct-invoke coverage for `register_sub_page_sections` — extracted
	 * from `register_settings()` to keep the latter under SonarCloud's
	 * cognitive-complexity threshold. xdebug doesn't reliably trace lines
	 * inside same-class protected helpers called from a tight loop, so the
	 * `register_settings` parent test would otherwise leave this body
	 * uncovered.
	 *
	 * @covers ::register_sub_page_sections
	 *
	 * @return void
	 */
	public function test_register_sub_page_sections_walks_sections_and_options(): void {
		$instance = Settings::get_instance();
		$sections = array(
			'test_section' => array(
				'name'        => 'Test Section',
				'description' => 'A description that should render.',
				'options'     => array(
					'test_option' => array(
						'labels' => array( 'name' => 'Test Option' ),
						'field'  => array( 'type' => 'text' ),
					),
				),
			),
		);

		Utility::invoke_hidden_method(
			$instance,
			'register_sub_page_sections',
			array( 'test_sub_page', $sections )
		);

		global $wp_settings_sections, $wp_settings_fields;

		$page = 'gatherpress_test_sub_page';

		$this->assertArrayHasKey( $page, $wp_settings_sections );
		$this->assertArrayHasKey( 'test_section', $wp_settings_sections[ $page ] );
		$this->assertArrayHasKey( $page, $wp_settings_fields );
		$this->assertArrayHasKey( 'test_section', $wp_settings_fields[ $page ] );
		$this->assertArrayHasKey( 'test_option', $wp_settings_fields[ $page ]['test_section'] );

		// Invoking the registered callback exercises the closure body that
		// defers to render_field — without this the closure stays uncovered
		// even though the registration path runs.
		ob_start();
		call_user_func( $wp_settings_fields[ $page ]['test_section']['test_option']['callback'] );
		ob_end_clean();
	}

	/**
	 * Direct-invoke coverage for the section-with-no-options branch of
	 * `register_sub_page_sections` — the `continue` path skips
	 * `add_settings_field` calls entirely.
	 *
	 * @covers ::register_sub_page_sections
	 *
	 * @return void
	 */
	public function test_register_sub_page_sections_skips_when_section_has_no_options(): void {
		$instance = Settings::get_instance();
		$sections = array(
			'no_options_section' => array(
				'name' => 'Section Without Options',
			),
		);

		Utility::invoke_hidden_method(
			$instance,
			'register_sub_page_sections',
			array( 'no_options_sub_page', $sections )
		);

		global $wp_settings_sections, $wp_settings_fields;

		$page = 'gatherpress_no_options_sub_page';

		$this->assertArrayHasKey( $page, $wp_settings_sections );
		// Section registered, but no fields under it.
		$this->assertTrue(
			empty( $wp_settings_fields[ $page ]['no_options_section'] ),
			'Section without options should not register any fields.'
		);
	}

	/**
	 * The section description callback rendered by
	 * `register_sub_page_sections` echoes a `<p class="description">` block
	 * when the section carries a description, and renders nothing when it
	 * doesn't — exercises both arms of the inner conditional.
	 *
	 * @covers ::register_sub_page_sections
	 *
	 * @return void
	 */
	public function test_register_sub_page_sections_description_callback_branches(): void {
		$instance = Settings::get_instance();
		$sections = array(
			'with_description' => array(
				'name'        => 'With Description',
				'description' => 'My description.',
			),
			'no_description'   => array(
				'name' => 'No Description',
			),
		);

		Utility::invoke_hidden_method(
			$instance,
			'register_sub_page_sections',
			array( 'desc_test_sub_page', $sections )
		);

		global $wp_settings_sections;

		$page    = 'gatherpress_desc_test_sub_page';
		$with_cb = $wp_settings_sections[ $page ]['with_description']['callback'];
		$no_cb   = $wp_settings_sections[ $page ]['no_description']['callback'];

		ob_start();
		$with_cb();
		$with_html = ob_get_clean();

		ob_start();
		$no_cb();
		$no_html = ob_get_clean();

		$this->assertStringContainsString( 'My description.', $with_html );
		$this->assertSame( '', $no_html );
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

	/**
	 * Coverage for is_option_inherited on single-site (not multisite).
	 *
	 * @covers ::is_option_inherited
	 *
	 * @return void
	 */
	public function test_is_option_inherited_returns_false_single_site(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site only.' );
		}

		$this->assertFalse( Settings::get_instance()->is_option_inherited( 'date_format' ) );
	}

	/**
	 * Coverage for is_option_inherited filter — can force `true` even when
	 * the network config would otherwise say no.
	 *
	 * @covers ::is_option_inherited
	 *
	 * @return void
	 */
	public function test_is_option_inherited_filter_can_force_true(): void {
		$filter = static function (): bool {
			return true;
		};
		add_filter( 'gatherpress_network_is_option_inherited', $filter );

		$this->assertTrue( Settings::get_instance()->is_option_inherited( 'date_format' ) );

		remove_filter( 'gatherpress_network_is_option_inherited', $filter );
	}

	/**
	 * Coverage for is_option_inherited when multisite + network config forces it.
	 *
	 * @covers ::is_option_inherited
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_is_option_inherited_respects_network_config(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		update_site_option(
			'gatherpress_network_settings',
			array(
				'enabled'   => true,
				'inherited' => array( 'date_format' ),
			)
		);
		\GatherPress\Core\Settings\Network::flush_config_cache();

		$this->assertTrue( Settings::get_instance()->is_option_inherited( 'date_format' ) );
		$this->assertFalse( Settings::get_instance()->is_option_inherited( 'time_format' ) );

		delete_site_option( 'gatherpress_network_settings' );
		\GatherPress\Core\Settings\Network::flush_config_cache();
	}

	/**
	 * Coverage for get() inheritance branch — reads the network site option
	 * when the option is flagged as inherited.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_reads_network_value_when_inherited(): void {
		update_site_option( 'gatherpress_settings', array( 'date_format' => 'Y-m-d' ) );

		$filter = static function (): bool {
			return true;
		};
		add_filter( 'gatherpress_network_is_option_inherited', $filter );

		$this->assertSame( 'Y-m-d', Settings::get_instance()->get( 'date_format' ) );

		remove_filter( 'gatherpress_network_is_option_inherited', $filter );
		delete_site_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for get() falling back to default when inherited but the
	 * network site option has nothing for this key.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_falls_back_to_default_when_inherited_and_network_empty(): void {
		delete_site_option( 'gatherpress_settings' );

		$filter = static function (): bool {
			return true;
		};
		add_filter( 'gatherpress_network_is_option_inherited', $filter );

		// `show_timezone` defaults to true.
		$this->assertTrue( Settings::get_instance()->get( 'show_timezone' ) );

		remove_filter( 'gatherpress_network_is_option_inherited', $filter );
	}

	/**
	 * Coverage for render_field wrapping output with the inherited marker +
	 * the super-admin variant of the note (link included).
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_wraps_when_inherited_for_super_admin(): void {
		$instance = Settings::get_instance();
		$user_id  = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user     = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_network_options' );
		wp_set_current_user( $user_id );

		$filter = static function (): bool {
			return true;
		};
		add_filter( 'gatherpress_network_is_option_inherited', $filter );

		$output = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'date_format',
				array(
					'field' => array(
						'type'    => 'text',
						'size'    => 'regular',
						'options' => array( 'default' => 'F j, Y' ),
					),
				),
			)
		);

		remove_filter( 'gatherpress_network_is_option_inherited', $filter );
		wp_delete_user( $user_id );

		$this->assertStringContainsString( 'gatherpress-field-inherited', $output );
		$this->assertStringContainsString( 'settings.php?page=gatherpress-network-settings', $output );
	}

	/**
	 * Coverage for render_field wrapping output with the inherited marker +
	 * the regular-admin variant (no link).
	 *
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_wraps_when_inherited_for_regular_admin(): void {
		$instance = Settings::get_instance();
		$user_id  = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$filter = static function (): bool {
			return true;
		};
		add_filter( 'gatherpress_network_is_option_inherited', $filter );

		$output = Utility::buffer_and_return(
			array( $instance, 'render_field' ),
			array(
				'date_format',
				array(
					'field' => array(
						'type'    => 'text',
						'size'    => 'regular',
						'options' => array( 'default' => 'F j, Y' ),
					),
				),
			)
		);

		remove_filter( 'gatherpress_network_is_option_inherited', $filter );
		wp_delete_user( $user_id );

		$this->assertStringContainsString( 'gatherpress-field-inherited', $output );
		$this->assertStringContainsString( 'Inherited from the network.', $output );
		$this->assertStringNotContainsString( 'settings.php?page=gatherpress-network-settings', $output );
	}

	/**
	 * Coverage for read_stored_options — blog-scope branch reads from the
	 * standard blog option.
	 *
	 * @covers ::read_stored_options
	 *
	 * @return void
	 */
	public function test_read_stored_options_blog_scope(): void {
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$result = Utility::invoke_hidden_method(
			Settings::get_instance(),
			'read_stored_options',
			array( 'blog' )
		);

		delete_option( Settings::OPTION_NAME );

		$this->assertSame( array( 'map_platform' => 'google' ), $result );
	}

	/**
	 * Coverage for read_stored_options — network-scope branch reads the
	 * network-wide site option.
	 *
	 * @covers ::read_stored_options
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_read_stored_options_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		update_site_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$result = Utility::invoke_hidden_method(
			Settings::get_instance(),
			'read_stored_options',
			array( 'network' )
		);

		delete_site_option( Settings::OPTION_NAME );

		$this->assertSame( array( 'map_platform' => 'google' ), $result );
	}

	/**
	 * Coverage for write_stored_options — both scope branches.
	 *
	 * @covers ::write_stored_options
	 *
	 * @return void
	 */
	public function test_write_stored_options_blog_scope(): void {
		Utility::invoke_hidden_method(
			Settings::get_instance(),
			'write_stored_options',
			array( 'blog', array( 'map_platform' => 'osm' ) )
		);

		$this->assertSame(
			array( 'map_platform' => 'osm' ),
			get_option( Settings::OPTION_NAME )
		);

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Coverage for write_stored_options network scope.
	 *
	 * @covers ::write_stored_options
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_write_stored_options_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		Utility::invoke_hidden_method(
			Settings::get_instance(),
			'write_stored_options',
			array( 'network', array( 'map_platform' => 'osm' ) )
		);

		$this->assertSame(
			array( 'map_platform' => 'osm' ),
			get_site_option( Settings::OPTION_NAME )
		);

		delete_site_option( Settings::OPTION_NAME );
	}

	/**
	 * Coverage for delete_stored_options — both scope branches.
	 *
	 * @covers ::delete_stored_options
	 *
	 * @return void
	 */
	public function test_delete_stored_options_blog_scope(): void {
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );

		Utility::invoke_hidden_method(
			Settings::get_instance(),
			'delete_stored_options',
			array( 'blog' )
		);

		$this->assertFalse( get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * Coverage for delete_stored_options network scope.
	 *
	 * @covers ::delete_stored_options
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_delete_stored_options_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		update_site_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );

		Utility::invoke_hidden_method(
			Settings::get_instance(),
			'delete_stored_options',
			array( 'network' )
		);

		$this->assertFalse( get_site_option( Settings::OPTION_NAME ) );
	}

	/**
	 * Coverage for import_settings with replace mode in network scope — covers
	 * the Network::flush_config_cache() call path at the tail of import.
	 *
	 * @covers ::import_settings
	 *
	 * @group   multisite
	 *
	 * @return void
	 */
	public function test_import_settings_replace_network_scope(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		update_site_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );
		\GatherPress\Core\Settings\Network::flush_config_cache();

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'map_platform' => 'google' ),
		);

		$result = Settings::get_instance()->import_settings( $data, 'replace', 'network' );

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			array( 'map_platform' => 'google' ),
			get_site_option( Settings::OPTION_NAME )
		);

		delete_site_option( Settings::OPTION_NAME );
		\GatherPress\Core\Settings\Network::flush_config_cache();
	}

	/**
	 * Build the row class string with no show_if returns the base hook class
	 * by itself. Every row gets the base class so the show_if JS can target
	 * rows uniformly; the `--hidden` modifier is added only when a condition
	 * exists and isn't satisfied.
	 *
	 * @since 0.34.0
	 * @covers ::build_row_class
	 *
	 * @return void
	 */
	public function test_build_row_class_without_show_if(): void {
		$instance = Settings::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'build_row_class',
			array(
				array( 'field' => array( 'type' => 'text' ) ),
			)
		);

		$this->assertSame( 'gatherpress-settings-row', $result );
	}

	/**
	 * Build the row class string when the show_if condition isn't satisfied
	 * tacks on the `--hidden` modifier so the row paints hidden on first
	 * render. JS later toggles it off if the user changes the controlling
	 * field to a matching value.
	 *
	 * @since 0.34.0
	 * @covers ::build_row_class
	 *
	 * @return void
	 */
	public function test_build_row_class_with_unsatisfied_show_if(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'build_row_class',
			array(
				array(
					'field'   => array( 'type' => 'text' ),
					'show_if' => array( 'map_platform' => 'google' ),
				),
			)
		);

		$this->assertSame(
			'gatherpress-settings-row gatherpress--is-hidden',
			$result
		);

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Build the row class string when the show_if condition IS satisfied
	 * omits the `--hidden` modifier so the row paints visible on first
	 * render — JS leaves it alone unless the controlling field later changes.
	 *
	 * @since 0.34.0
	 * @covers ::build_row_class
	 *
	 * @return void
	 */
	public function test_build_row_class_with_satisfied_show_if(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'build_row_class',
			array(
				array(
					'field'   => array( 'type' => 'text' ),
					'show_if' => array( 'map_platform' => 'google' ),
				),
			)
		);

		$this->assertSame( 'gatherpress-settings-row', $result );

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Evaluate show_if returns true when every controlling key matches the
	 * currently saved value. Single-value conditions use string equality.
	 *
	 * @since 0.34.0
	 * @covers ::evaluate_show_if
	 *
	 * @return void
	 */
	public function test_evaluate_show_if_single_value_match(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'evaluate_show_if',
			array( array( 'map_platform' => 'google' ) )
		);

		$this->assertTrue( $result );

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Evaluate show_if returns false when the controlling key has a value
	 * other than the one expected.
	 *
	 * @since 0.34.0
	 * @covers ::evaluate_show_if
	 *
	 * @return void
	 */
	public function test_evaluate_show_if_single_value_mismatch(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'evaluate_show_if',
			array( array( 'map_platform' => 'google' ) )
		);

		$this->assertFalse( $result );

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Evaluate show_if accepts an array of expected values and returns true
	 * when the current value is a member (OR semantics within one key).
	 *
	 * @since 0.34.0
	 * @covers ::evaluate_show_if
	 *
	 * @return void
	 */
	public function test_evaluate_show_if_array_of_values(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$result = Utility::invoke_hidden_method(
			$instance,
			'evaluate_show_if',
			array( array( 'map_platform' => array( 'google', 'mapbox' ) ) )
		);
		$this->assertTrue( $result );

		// Non-member current value.
		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'osm' ) );
		$result = Utility::invoke_hidden_method(
			$instance,
			'evaluate_show_if',
			array( array( 'map_platform' => array( 'google', 'mapbox' ) ) )
		);
		$this->assertFalse( $result );

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Evaluate show_if `array( 'not' => … )` matches when the current
	 * value is not (one of) the excluded value(s).
	 *
	 * @since 0.35.0
	 * @covers ::evaluate_show_if
	 *
	 * @return void
	 */
	public function test_evaluate_show_if_negation(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );

		// Scalar negation: true while the value is not 'disabled'.
		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'enabled' ) );
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( array( 'rsvp_mode' => array( 'not' => 'disabled' ) ) )
			),
			'A non-excluded value matches the negation.'
		);

		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'disabled' ) );
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( array( 'rsvp_mode' => array( 'not' => 'disabled' ) ) )
			),
			'The excluded value fails the negation.'
		);

		// Array negation: excluded when the value is any of the listed.
		update_option( Settings::OPTION_NAME, array( 'rsvp_mode' => 'per_event_disabled' ) );
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( array( 'rsvp_mode' => array( 'not' => array( 'disabled', 'per_event_disabled' ) ) ) )
			),
			'A value in the excluded list fails the negation.'
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( array( 'rsvp_mode' => array( 'not' => array( 'disabled', 'enabled' ) ) ) )
			),
			'A value outside the excluded list matches.'
		);

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Evaluate show_if combines multiple keys with AND — every key's
	 * condition must hold for the result to be true.
	 *
	 * @since 0.34.0
	 * @covers ::evaluate_show_if
	 *
	 * @return void
	 */
	public function test_evaluate_show_if_multi_key_and(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option(
			Settings::OPTION_NAME,
			array(
				'map_platform'                  => 'google',
				'venue_map_default_render_mode' => 'interactive',
			)
		);

		$matching_conditions = array(
			'map_platform'                  => 'google',
			'venue_map_default_render_mode' => 'interactive',
		);
		$this->assertTrue(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( $matching_conditions )
			)
		);

		// Flip one key out of band — overall result must drop to false.
		$mixed_conditions = array(
			'map_platform'                  => 'google',
			'venue_map_default_render_mode' => 'static',
		);
		$this->assertFalse(
			Utility::invoke_hidden_method(
				$instance,
				'evaluate_show_if',
				array( $mixed_conditions )
			)
		);

		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Render field emits the show_if marker alongside the field template
	 * when the option declares a `show_if` condition. Without this call site
	 * exercise, the helper would be covered in isolation but the wiring
	 * from `render_field` → `render_show_if_marker` could silently break
	 * in a refactor.
	 *
	 * @since 0.34.0
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_emits_show_if_marker_when_declared(): void {
		$instance = Settings::get_instance();

		ob_start();
		$instance->render_field(
			'google_maps_api_key',
			array(
				'labels'  => array( 'name' => 'Google Maps API Key' ),
				'field'   => array(
					'label' => 'Google Maps API key:',
					'type'  => 'text',
					'size'  => 'regular',
				),
				'show_if' => array( 'map_platform' => 'google' ),
			)
		);
		$output = ob_get_clean();

		// Field input itself renders.
		$this->assertStringContainsString(
			'name="gatherpress_settings[google_maps_api_key]"',
			$output
		);
		// And the show_if marker is emitted alongside it.
		$this->assertStringContainsString(
			'class="gatherpress-show-if-marker"',
			$output
		);
		$this->assertStringContainsString(
			'data-show-if="{&quot;map_platform&quot;:&quot;google&quot;}"',
			$output
		);
	}

	/**
	 * Render field skips the show_if marker when no condition is declared,
	 * keeping the field output free of the JS hook for the vast majority of
	 * fields that don't use the feature.
	 *
	 * @since 0.34.0
	 * @covers ::render_field
	 *
	 * @return void
	 */
	public function test_render_field_omits_show_if_marker_by_default(): void {
		$instance = Settings::get_instance();

		ob_start();
		$instance->render_field(
			'plain_text_field',
			array(
				'labels' => array( 'name' => 'Plain Text Field' ),
				'field'  => array(
					'label' => 'Plain text:',
					'type'  => 'text',
					'size'  => 'regular',
				),
			)
		);
		$output = ob_get_clean();

		$this->assertStringNotContainsString(
			'gatherpress-show-if-marker',
			$output
		);
	}

	/**
	 * Render show_if marker emits a hidden input carrying the condition map
	 * as a JSON-encoded data attribute. The marker has no `name` attribute
	 * so it never enters the POST payload — it's a JS hook, not a value.
	 *
	 * @since 0.34.0
	 * @covers ::render_show_if_marker
	 *
	 * @return void
	 */
	public function test_render_show_if_marker_emits_hidden_input(): void {
		$instance = Settings::get_instance();

		ob_start();
		Utility::invoke_hidden_method(
			$instance,
			'render_show_if_marker',
			array( array( 'map_platform' => 'google' ) )
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="hidden"', $output );
		$this->assertStringContainsString(
			'class="gatherpress-show-if-marker"',
			$output
		);
		$this->assertStringContainsString(
			'data-show-if="{&quot;map_platform&quot;:&quot;google&quot;}"',
			$output
		);
		$this->assertStringNotContainsString( 'name=', $output );
	}

	/**
	 * Saving a partial input (omitting a show_if-hidden field) preserves the
	 * field's previously stored value rather than dropping it. This is the
	 * key guarantee behind the "hidden ≠ cleared" promise of the show_if
	 * feature: even if the browser somehow omits the field from POST, the
	 * sanitize_page_settings closure merges with read_stored_options() and
	 * keeps the value in place.
	 *
	 * Uses a synthetic non-default value for the controlling field so the
	 * "values matching defaults are stripped" pass doesn't interfere with
	 * the assertion on the hidden field.
	 *
	 * @since 0.34.0
	 * @covers ::sanitize_page_settings
	 *
	 * @return void
	 */
	public function test_sanitize_page_settings_preserves_hidden_field_value(): void {
		$instance = Settings::get_instance();

		delete_option( Settings::OPTION_NAME );
		update_option(
			Settings::OPTION_NAME,
			array(
				'map_platform'        => 'google',
				'google_maps_api_key' => 'prev-saved-key',
			)
		);

		$callback = $instance->sanitize_page_settings(
			array(
				'map_platform'        => 'select',
				'google_maps_api_key' => 'text',
			)
		);

		// Submit only the controlling field — simulating what happens if a
		// show_if-hidden field were somehow omitted from POST. Keep it at
		// 'google' (the stored value) so it doesn't get stripped via the
		// "matches default" pass and we can assert on it.
		$result = $callback( array( 'map_platform' => 'google' ) );

		$this->assertSame( 'google', $result['map_platform'] );
		$this->assertSame(
			'prev-saved-key',
			$result['google_maps_api_key'],
			'Hidden field value must survive a partial save.'
		);

		delete_option( Settings::OPTION_NAME );
	}
}
