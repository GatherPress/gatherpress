<?php
/**
 * Class handles unit tests for GatherPress\Core\Assets.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Assets;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Assets.
 *
 * @coversDefaultClass \GatherPress\Core\Assets
 */
class Test_Assets extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Assets::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_print_scripts',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'admin_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_assets',
				'priority' => 10,
				'callback' => array( $instance, 'block_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_editor_assets',
				'priority' => 10,
				'callback' => array( $instance, 'editor_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_editor_assets',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_variation_assets' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_variation_assets' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_head',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_footer',
				'priority' => 11,
				'callback' => array( $instance, 'event_communication_modal' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_enqueue_styles' ),
			),
			array(
				'type'          => 'filter',
				'name'          => 'render_block',
				'priority'      => 10,
				'callback'      => array( $instance, 'maybe_enqueue_tooltip_assets' ),
				'accepted_args' => 1,
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add_global_object method.
	 *
	 * @covers ::add_global_object
	 *
	 * @return void
	 */
	public function test_add_global_object(): void {
		$instance = Assets::get_instance();
		$event_id = $this->mock->post(
			array( 'post_type' => Event::POST_TYPE )
		)->get()->ID;
		$object   = Utility::buffer_and_return( array( $instance, 'add_global_object' ) );

		$this->assertMatchesRegularExpression(
			'#<script>window.GatherPress = {.*}</script>#',
			$object,
			'Failed to assert regex of global object matches.'
		);
	}

	/**
	 * Coverage for event_communication_modal method.
	 *
	 * @covers ::event_communication_modal
	 *
	 * @return void
	 */
	public function test_event_communication_modal(): void {
		$instance = Assets::get_instance();
		$this->mock->post( array( 'post_type' => 'post' ) );

		$output = Utility::buffer_and_return( array( $instance, 'event_communication_modal' ) );

		$this->assertEmpty( $output, 'Failed to assert event_communication_modal outputs nothing.' );

		$this->mock->post( array( 'post_type' => Event::POST_TYPE ) );

		$output = Utility::buffer_and_return( array( $instance, 'event_communication_modal' ) );

		$this->assertSame(
			'<div id="gatherpress-event-communication-modal"></div>',
			$output,
			'Failed to assert event_communication_modal output div.'
		);
	}


	/**
	 * Coverage for block_enqueue_scripts.
	 *
	 * @covers ::block_enqueue_scripts
	 *
	 * @return void
	 */
	public function test_block_enqueue_scripts(): void {
		$instance = Assets::get_instance();
		$instance->block_enqueue_scripts();

		$this->assertTrue( wp_style_is( 'dashicons', 'enqueued' ) );
	}

	/**
	 * Coverage for admin_enqueue_scripts.
	 *
	 * @covers ::admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts(): void {
		$instance = Assets::get_instance();

		$this->assertFalse( wp_style_is( 'gatherpress-admin-style', 'registered' ) );
		$instance->admin_enqueue_scripts( 'dummy-admin-page' );
		$this->assertTrue( wp_style_is( 'gatherpress-admin-style', 'registered' ) );

		$this->assertFalse( wp_script_is( 'gatherpress-panels', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'gatherpress-modals', 'enqueued' ) );
		$instance->admin_enqueue_scripts( 'post-new.php' );
		$this->assertTrue( wp_script_is( 'gatherpress-panels', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'gatherpress-modals', 'enqueued' ) );

		// TODO get_sub_pages() hooks.

		$this->assertFalse( wp_script_is( 'gatherpress-profile', 'enqueued' ) );
		$instance->admin_enqueue_scripts( 'profile.php' );
		$this->assertTrue( wp_script_is( 'gatherpress-profile', 'enqueued' ) );
	}

	/**
	 * Coverage for localize method.
	 *
	 * @covers ::localize
	 *
	 * @return void
	 */
	public function test_localize(): void {
		$instance = Assets::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event    = new Event( $event_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-12 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$output = Utility::invoke_hidden_method( $instance, 'localize', array( $event_id ) );

		$expected_datetime = array(
			'datetime_start'     => '2020-05-11 15:00:00',
			'datetime_start_gmt' => '2020-05-11 19:00:00',
			'datetime_end'       => '2020-05-12 17:00:00',
			'datetime_end_gmt'   => '2020-05-12 21:00:00',
			'timezone'           => 'America/New_York',
		);

		$this->assertSame(
			$expected_datetime,
			$output['eventDetails']['dateTime'],
			'Failed to assert that datetime array matches.'
		);
		$this->assertEquals(
			1,
			$output['eventDetails']['hasEventPast'],
			'Failed to assert that has_event_past is true'
		);
		$this->assertEquals( $event_id, $output['eventDetails']['postId'], 'Failed to assert that post_id matches.' );
	}

	/**
	 * Coverage for unregister_blocks.
	 *
	 * @covers ::unregister_blocks
	 *
	 * @return void
	 */
	public function test_unregister_blocks_frontend(): void {
		$instance = Assets::get_instance();

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( array(), $blocks );
		$this->mock->wp()->reset();
	}

	/**
	 * Data provider for unregister_blocks_admin test.
	 *
	 * @return array
	 */
	public function date_unregister_blocks_admin(): array {
		return array(
			array(
				'post',
				array(
					'gatherpress/online-event',
					'gatherpress/venue',
				),
			),
			array(
				'page',
				array(
					'gatherpress/online-event',
					'gatherpress/venue',
				),
			),
			array(
				'gatherpress_event',
				array(),
			),
			array(
				'gatherpress_venue',
				array(
					'gatherpress/online-event',
				),
			),
		);
	}

	/**
	 * Coverage for unregister_blocks.
	 *
	 * @param string $post_type       Post type.
	 * @param array  $expected_blocks Array of blocks.
	 *
	 * @dataProvider date_unregister_blocks_admin
	 * @covers ::unregister_blocks
	 *
	 * @return void
	 */
	public function test_unregister_blocks_admin( string $post_type, array $expected_blocks ): void {
		$instance = Assets::get_instance();

		$this->mock->post( array( 'post_type' => $post_type ) );
		$this->mock->user( 'admin', 'wp-admin-page' );

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( $expected_blocks, $blocks );

		$this->mock->wp()->reset();
	}

	/**
	 * Coverage for get_asset_data method.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data(): void {
		$instance = Assets::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'asset_data', array() );

		$asset = Utility::invoke_hidden_method( $instance, 'get_asset_data', array( 'editor' ) );

		$this->assertIsArray( $asset['dependencies'], 'Failed to assert that dependencies is an array.' );
		$this->assertIsString( $asset['version'], 'Failed to assert that version is a string.' );
		$this->assertIsArray(
			Utility::get_hidden_property( $instance, 'asset_data' ),
			'Failed to assert that asset_data is an array.'
		);
	}

	/**
	 * Coverage for maybe_enqueue_styles method with GatherPress block.
	 *
	 * @covers ::maybe_enqueue_styles
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_styles_with_gatherpress_block(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		$block_content = '<div class="wp-block-gatherpress-event-date">Test</div>';
		$block         = array(
			'blockName' => 'gatherpress/event-date',
		);

		$this->assertFalse(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is not enqueued before filter.'
		);

		$result = $instance->maybe_enqueue_styles( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content is unchanged.'
		);
		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is enqueued for GatherPress blocks.'
		);
	}

	/**
	 * Coverage for maybe_enqueue_styles method with non-GatherPress block.
	 *
	 * @covers ::maybe_enqueue_styles
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_styles_with_non_gatherpress_block(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		// Dequeue if it was enqueued by previous test.
		wp_dequeue_style( 'gatherpress-utility-style' );

		$block_content = '<div class="wp-block-paragraph">Test</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
		);

		$result = $instance->maybe_enqueue_styles( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content is unchanged.'
		);
		$this->assertFalse(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is not enqueued for non-GatherPress blocks.'
		);
	}

	/**
	 * Coverage for maybe_enqueue_styles method with missing blockName.
	 *
	 * @covers ::maybe_enqueue_styles
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_styles_missing_block_name(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		// Dequeue if it was enqueued by previous test.
		wp_dequeue_style( 'gatherpress-utility-style' );

		$block_content = '<div>Test</div>';
		$block         = array();

		$result = $instance->maybe_enqueue_styles( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content is unchanged.'
		);
		$this->assertFalse(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is not enqueued when blockName is missing.'
		);
	}

	/**
	 * Coverage for editor_enqueue_scripts method.
	 *
	 * @covers ::editor_enqueue_scripts
	 *
	 * @return void
	 */
	public function test_editor_enqueue_scripts(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		$this->assertFalse(
			wp_script_is( 'gatherpress-editor', 'enqueued' ),
			'Failed to assert gatherpress-editor is not enqueued before calling editor_enqueue_scripts.'
		);

		$instance->editor_enqueue_scripts();

		$this->assertTrue(
			wp_script_is( 'gatherpress-editor', 'enqueued' ),
			'Failed to assert gatherpress-editor is enqueued.'
		);
		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is enqueued in editor.'
		);
	}

	/**
	 * Coverage for register_variation_assets method.
	 *
	 * @covers ::register_variation_assets
	 *
	 * @return void
	 */
	public function test_register_variation_assets(): void {
		$instance = Assets::get_instance();

		$instance->register_variation_assets();

		// The method should register variation assets.
		// We can't easily verify all variations, but we can verify the method runs.
		$this->assertTrue(
			true,
			'The register_variation_assets method should execute without error.'
		);
	}

	/**
	 * Coverage for enqueue_variation_assets method.
	 *
	 * @covers ::enqueue_variation_assets
	 *
	 * @return void
	 */
	public function test_enqueue_variation_assets(): void {
		$instance = Assets::get_instance();

		// First register the assets.
		$instance->register_variation_assets();

		// Then enqueue them.
		$instance->enqueue_variation_assets();

		// The method should enqueue variation assets.
		// We can't easily verify all variations, but we can verify the method runs.
		$this->assertTrue(
			true,
			'The enqueue_variation_assets method should execute without error.'
		);
	}

	/**
	 * Coverage for register_asset method.
	 *
	 * @covers ::register_asset
	 *
	 * @return void
	 */
	public function test_register_asset(): void {
		$instance = Assets::get_instance();

		// Test with a variation that exists.
		Utility::invoke_hidden_method( $instance, 'register_asset', array( 'query', 'variations/core/' ) );

		$this->assertTrue(
			wp_script_is( 'gatherpress-query', 'registered' ),
			'Failed to assert gatherpress-query script is registered.'
		);
	}

	/**
	 * Coverage for register_asset with non-existent folder.
	 *
	 * @covers ::register_asset
	 * @covers ::asset_exists
	 *
	 * @return void
	 */
	public function test_register_asset_nonexistent_folder(): void {
		$instance = Assets::get_instance();

		add_filter( 'gatherpress_asset_critical', '__return_false' );

		// Call register_asset with a bogus folder name that doesn't exist.
		Utility::invoke_hidden_method(
			$instance,
			'register_asset',
			array( 'fake-nonexistent-folder', 'fake-build-dir/' )
		);

		remove_all_filters( 'gatherpress_asset_critical' );

		// Verify script was NOT registered due to early return.
		$this->assertFalse(
			wp_script_is( 'gatherpress-fake-nonexistent-folder', 'registered' ),
			'Script should not be registered when folder does not exist.'
		);
	}

	/**
	 * Coverage for register_asset with CSS file.
	 *
	 * @covers ::register_asset
	 *
	 * @return void
	 */
	public function test_register_asset_with_css(): void {
		$instance  = Assets::get_instance();
		$css_path  = GATHERPRESS_CORE_PATH . '/build/variations/core/query/index.css';
		$css_exist = file_exists( $css_path );

		// Create a temporary CSS file for testing if it doesn't exist.
		if ( ! $css_exist ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Necessary for testing.
			$file = fopen( $css_path, 'w' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Necessary for testing.
			fwrite( $file, '/* Test CSS */' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Necessary for testing.
			fclose( $file );
		}

		// Test with 'query' which now has both JS and CSS files.
		Utility::invoke_hidden_method( $instance, 'register_asset', array( 'query', 'variations/core/' ) );

		$this->assertTrue(
			wp_script_is( 'gatherpress-query', 'registered' ),
			'Failed to assert gatherpress-query script is registered.'
		);
		$this->assertTrue(
			wp_style_is( 'gatherpress-query', 'registered' ),
			'Failed to assert gatherpress-query style is registered when CSS file exists.'
		);

		// Clean up temporary CSS file if we created it.
		if ( ! $css_exist && file_exists( $css_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Necessary for testing.
			unlink( $css_path );
		}
	}

	/**
	 * Coverage for enqueue_asset method.
	 *
	 * @covers ::enqueue_asset
	 *
	 * @return void
	 */
	public function test_enqueue_asset(): void {
		$instance = Assets::get_instance();

		// First register the asset.
		Utility::invoke_hidden_method( $instance, 'register_asset', array( 'query', 'variations/core/' ) );

		$this->assertFalse(
			wp_script_is( 'gatherpress-query', 'enqueued' ),
			'Failed to assert gatherpress-query is not enqueued before calling enqueue_asset.'
		);

		// Then enqueue it.
		Utility::invoke_hidden_method( $instance, 'enqueue_asset', array( 'query' ) );

		$this->assertTrue(
			wp_script_is( 'gatherpress-query', 'enqueued' ),
			'Failed to assert gatherpress-query is enqueued after calling enqueue_asset.'
		);
	}

	/**
	 * Coverage for asset_exists method with existing file.
	 *
	 * @covers ::asset_exists
	 *
	 * @return void
	 */
	public function test_asset_exists_with_existing_file(): void {
		$instance = Assets::get_instance();
		$path     = GATHERPRESS_CORE_PATH . '/build/editor.asset.php';

		$result = Utility::invoke_hidden_method( $instance, 'asset_exists', array( $path, 'editor', true ) );

		$this->assertTrue(
			$result,
			'Failed to assert asset_exists returns true for existing file.'
		);
	}

	/**
	 * Coverage for asset_exists method with non-existent file and non-critical.
	 *
	 * @covers ::asset_exists
	 *
	 * @return void
	 */
	public function test_asset_exists_with_non_existent_file_non_critical(): void {
		$instance = Assets::get_instance();
		$path     = GATHERPRESS_CORE_PATH . '/build/non-existent-asset.asset.php';

		$result = Utility::invoke_hidden_method( $instance, 'asset_exists', array( $path, 'non-existent', false ) );

		$this->assertFalse(
			$result,
			'Failed to assert asset_exists returns false for non-existent non-critical file.'
		);
	}

	/**
	 * Coverage for asset_exists method with critical file missing in development.
	 *
	 * @covers ::asset_exists
	 *
	 * @return void
	 */
	public function test_asset_exists_critical_file_missing_development(): void {
		$instance = Assets::get_instance();
		$path     = GATHERPRESS_CORE_PATH . '/build/missing-critical-asset.asset.php';

		// WordPress test suite runs in 'development' by default, so no need to filter.
		// Just test that an Error is thrown for missing critical files.

		$this->expectException( \Error::class );
		$this->expectExceptionMessageMatches( '/You need to run `npm start` or `npm run build`/' );

		Utility::invoke_hidden_method( $instance, 'asset_exists', array( $path, 'missing-critical', true ) );
	}

	/**
	 * Coverage for enqueue_asset when style is not registered.
	 *
	 * @covers ::enqueue_asset
	 *
	 * @return void
	 */
	public function test_enqueue_asset_without_style(): void {
		$instance = Assets::get_instance();

		// Register only script, no style.
		wp_register_script( 'gatherpress-test-asset', 'test.js', array(), '1.0.0', true );

		Utility::invoke_hidden_method( $instance, 'enqueue_asset', array( 'test-asset' ) );

		$this->assertTrue(
			wp_script_is( 'gatherpress-test-asset', 'enqueued' ),
			'Script should be enqueued.'
		);
		$this->assertFalse(
			wp_style_is( 'gatherpress-test-asset', 'enqueued' ),
			'Style should not be enqueued when not registered.'
		);

		// Clean up.
		wp_dequeue_script( 'gatherpress-test-asset' );
		wp_deregister_script( 'gatherpress-test-asset' );
	}

	/**
	 * Coverage for enqueue_asset when style is registered.
	 *
	 * @covers ::enqueue_asset
	 *
	 * @return void
	 */
	public function test_enqueue_asset_with_style(): void {
		$instance = Assets::get_instance();

		// Register both script and style.
		wp_register_script( 'gatherpress-test-with-style', 'test.js', array(), '1.0.0', true );
		wp_register_style( 'gatherpress-test-with-style', 'test.css', array(), '1.0.0', 'all' );

		Utility::invoke_hidden_method( $instance, 'enqueue_asset', array( 'test-with-style' ) );

		$this->assertTrue(
			wp_script_is( 'gatherpress-test-with-style', 'enqueued' ),
			'Script should be enqueued.'
		);
		$this->assertTrue(
			wp_style_is( 'gatherpress-test-with-style', 'enqueued' ),
			'Style should be enqueued when registered.'
		);

		// Clean up.
		wp_dequeue_script( 'gatherpress-test-with-style' );
		wp_deregister_script( 'gatherpress-test-with-style' );
		wp_dequeue_style( 'gatherpress-test-with-style' );
		wp_deregister_style( 'gatherpress-test-with-style' );
	}

	/**
	 * Coverage for admin_enqueue_scripts with settings page.
	 *
	 * @covers ::admin_enqueue_scripts
	 *
	 * @return void
	 */
	public function test_admin_enqueue_scripts_settings_page(): void {
		$instance = Assets::get_instance();

		// Test with a settings page hook.
		$hook = 'gatherpress_event_page_gatherpress_general';

		$this->assertFalse(
			wp_style_is( 'gatherpress-settings-style', 'enqueued' ),
			'Failed to assert gatherpress-settings-style is not enqueued before calling admin_enqueue_scripts.'
		);
		$this->assertFalse(
			wp_script_is( 'gatherpress-settings', 'enqueued' ),
			'Failed to assert gatherpress-settings is not enqueued before calling admin_enqueue_scripts.'
		);

		$instance->admin_enqueue_scripts( $hook );

		$this->assertTrue(
			wp_style_is( 'gatherpress-settings-style', 'enqueued' ),
			'Failed to assert gatherpress-settings-style is enqueued for settings page.'
		);
		$this->assertTrue(
			wp_script_is( 'gatherpress-settings', 'enqueued' ),
			'Failed to assert gatherpress-settings is enqueued for settings page.'
		);
		$this->assertTrue(
			wp_style_is( 'wp-edit-blocks', 'enqueued' ),
			'Failed to assert wp-edit-blocks is enqueued for settings page.'
		);
	}

	/**
	 * Coverage for enqueue_tooltip_assets method.
	 *
	 * This test must run first to ensure the static $enqueued variable is false.
	 * The 'a_' prefix ensures alphabetical ordering runs it early.
	 *
	 * @covers ::enqueue_tooltip_assets
	 *
	 * @return void
	 */
	public function test_a_enqueue_tooltip_assets(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		// Dequeue if it was enqueued by previous test.
		wp_dequeue_style( 'gatherpress-utility-style' );

		// Use invoke_hidden_method to call the protected method.
		Utility::invoke_hidden_method( $instance, 'enqueue_tooltip_assets' );

		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is enqueued by enqueue_tooltip_assets.'
		);
	}

	/**
	 * Coverage for enqueue_tooltip_assets early return when already enqueued.
	 *
	 * @covers ::enqueue_tooltip_assets
	 *
	 * @return void
	 */
	public function test_enqueue_tooltip_assets_early_return(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		// Call enqueue_tooltip_assets twice - second call should return early.
		Utility::invoke_hidden_method( $instance, 'enqueue_tooltip_assets' );
		Utility::invoke_hidden_method( $instance, 'enqueue_tooltip_assets' );

		// The test passes if no errors occur - the early return path is covered.
		$this->assertTrue( true, 'Second call should return early without error.' );
	}

	/**
	 * Coverage for maybe_enqueue_tooltip_assets method with tooltip markup.
	 *
	 * @covers ::maybe_enqueue_tooltip_assets
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_tooltip_assets_with_tooltip_markup(): void {
		$instance = Assets::get_instance();

		$block_content = '<div class="gatherpress-tooltip">Tooltip content</div>';

		$result = $instance->maybe_enqueue_tooltip_assets( $block_content );

		// The method should return block content unchanged.
		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content is unchanged.'
		);
	}

	/**
	 * Coverage for maybe_enqueue_tooltip_assets method without tooltip markup.
	 *
	 * @covers ::maybe_enqueue_tooltip_assets
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_tooltip_assets_without_tooltip_markup(): void {
		$instance = Assets::get_instance();

		// First register the utility style.
		$instance->block_enqueue_scripts();

		// Dequeue if it was enqueued by previous test.
		wp_dequeue_style( 'gatherpress-utility-style' );

		$block_content = '<div class="some-other-class">Content</div>';

		$result = $instance->maybe_enqueue_tooltip_assets( $block_content );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content is unchanged.'
		);
		$this->assertFalse(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is not enqueued without tooltip markup.'
		);
	}
}
