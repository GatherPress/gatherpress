<?php
/**
 * Class handles unit tests for GatherPress\Core\Assets.
 *
 * @package GatherPress\Core
 * @since 0.27.0
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
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'admin_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_assets',
				'priority' => 10,
				'callback' => array( $instance, 'register_block_assets' ),
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
				'name'     => 'enqueue_block_editor_assets',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_aql_integration' ),
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
				'priority' => 10,
				'callback' => array( $instance, 'add_interactivity_state' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_footer',
				'priority' => 11,
				'callback' => array( $instance, 'event_communication_modal' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_timezone_shim' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_timezone_shim' ),
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
	 * Coverage for enqueue_timezone_shim when wp-date isn't registered.
	 *
	 * @covers ::enqueue_timezone_shim
	 *
	 * @return void
	 */
	public function test_enqueue_timezone_shim_bails_without_wp_date(): void {
		$instance       = Assets::get_instance();
		$was_registered = wp_script_is( 'wp-date', 'registered' );

		if ( $was_registered ) {
			wp_deregister_script( 'wp-date' );
		}

		$instance->enqueue_timezone_shim();

		$this->assertFalse( wp_script_is( 'wp-date', 'enqueued' ) );

		// Leave the global script registry as we found it.
		if ( $was_registered ) {
			wp_default_packages_scripts( wp_scripts() );
		}
	}

	/**
	 * Coverage for enqueue_timezone_shim when wp-date is registered — enqueues
	 * the shim and attaches the inline script that normalizes `UTC+0` / `UTC-0`.
	 *
	 * @covers ::enqueue_timezone_shim
	 *
	 * @return void
	 */
	public function test_enqueue_timezone_shim_enqueues_and_attaches_inline_script(): void {
		$instance = Assets::get_instance();

		if ( ! wp_script_is( 'wp-date', 'registered' ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion -- Test registration; no real asset.
			wp_register_script( 'wp-date', '', array(), '1', false );
		}

		$instance->enqueue_timezone_shim();

		$this->assertTrue( wp_script_is( 'wp-date', 'enqueued' ) );

		$inline = wp_scripts()->get_data( 'wp-date', 'after' );
		$joined = is_array( $inline ) ? implode( "\n", $inline ) : (string) $inline;

		$this->assertStringContainsString( 'wp.date.setSettings', $joined );
		$this->assertStringContainsString( 'UTC', $joined );
	}

	/**
	 * Coverage for add_interactivity_state method.
	 *
	 * Regression for #1752: the eventApiUrl must be available on event
	 * archives (and Query Loops), not only singular event pages — RSVP and
	 * other interactive blocks render there too. The previous `is_singular()`
	 * gate left `eventApiUrl` undefined on the archive, so the RSVP view
	 * scripts requested `/event/undefined/nonce` (404) and every RSVP from an
	 * archive failed.
	 *
	 * @covers ::add_interactivity_state
	 *
	 * @return void
	 */
	public function test_add_interactivity_state(): void {
		$instance = Assets::get_instance();

		// Visit the event archive — the context that previously bailed.
		$this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$this->go_to( get_post_type_archive_link( Event::POST_TYPE ) );

		$instance->add_interactivity_state();
		$state = wp_interactivity_state( 'gatherpress' );

		$this->assertArrayHasKey(
			'eventApiUrl',
			$state,
			'Failed to assert eventApiUrl is set in interactivity state on the event archive.'
		);
		$this->assertSame(
			rest_url( sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ) ),
			$state['eventApiUrl'],
			'Failed to assert eventApiUrl matches rest_url() so it adapts to the permalink structure.'
		);
	}

	/**
	 * Coverage for add_interactivity_state method without pretty permalinks.
	 *
	 * Regression: eventApiUrl was previously built via
	 * `home_url( 'wp-json/' . $slug )`, a hardcoded path that only resolves
	 * when pretty permalinks are enabled. With the plain permalink structure,
	 * WordPress serves the REST API via `?rest_route=` instead, so the
	 * hardcoded `/wp-json/` path 404s. Using `rest_url()` adapts to either
	 * structure.
	 *
	 * @covers ::add_interactivity_state
	 *
	 * @return void
	 */
	public function test_add_interactivity_state_without_pretty_permalinks(): void {
		global $wp_rewrite;

		$instance            = Assets::get_instance();
		$permalink_structure = $wp_rewrite->permalink_structure;
		$wp_rewrite->set_permalink_structure( '' );

		$this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$this->go_to( get_post_type_archive_link( Event::POST_TYPE ) );

		$instance->add_interactivity_state();
		$state    = wp_interactivity_state( 'gatherpress' );
		$expected = rest_url( sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE ) );

		$wp_rewrite->set_permalink_structure( $permalink_structure );

		$this->assertSame(
			$expected,
			$state['eventApiUrl'],
			'Failed to assert eventApiUrl matches rest_url() when pretty permalinks are disabled.'
		);
		$this->assertStringContainsString(
			'rest_route=',
			$state['eventApiUrl'],
			'Failed to assert eventApiUrl uses the ?rest_route= form without pretty permalinks.'
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
	 * Coverage for register_block_assets.
	 *
	 * Registers `gatherpress-utility-style` in every context and additionally
	 * enqueues it in the block editor (where `is_admin()` is true) so the
	 * stylesheet lands inside the editor canvas iframe via the
	 * `enqueue_block_assets` hook.
	 *
	 * @covers ::register_block_assets
	 *
	 * @return void
	 */
	public function test_register_block_assets(): void {
		$instance = Assets::get_instance();

		// Frontend context: style registered but not enqueued (the per-block
		// `maybe_enqueue_styles` filter handles conditional frontend loading).
		set_current_screen( 'front' );
		wp_dequeue_style( 'gatherpress-utility-style' );

		$instance->register_block_assets();

		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'registered' ),
			'Failed to assert gatherpress-utility-style is registered on frontend.'
		);
		$this->assertFalse(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is not enqueued on frontend.'
		);

		// Admin / block-editor context: style is also enqueued so it reaches
		// the editor canvas iframe (issue #1645).
		set_current_screen( 'post.php' );
		wp_dequeue_style( 'gatherpress-utility-style' );

		$instance->register_block_assets();

		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is enqueued in the block editor.'
		);

		// Reset for downstream tests.
		set_current_screen( 'front' );
		wp_dequeue_style( 'gatherpress-utility-style' );
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
	 * Coverage for get_asset_data when the asset file was already loaded.
	 *
	 * Regression for #1768: the method used require_once, which returns `true`
	 * (not the array) when the file has already been loaded in the request.
	 * `(array) true` is `[ 0 => true ]`, breaking the dependencies/version
	 * lookups. Plain require always returns the array.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data_returns_array_when_file_already_loaded(): void {
		$instance = Assets::get_instance();
		$path     = GATHERPRESS_CORE_PATH . '/build/editor.asset.php';

		// Simulate the asset file already being loaded earlier in the request.
		require_once $path;

		Utility::set_and_get_hidden_property( $instance, 'asset_data', array() );
		$asset = Utility::invoke_hidden_method(
			$instance,
			'get_asset_data',
			array( 'editor_already_loaded', $path )
		);

		$this->assertArrayHasKey(
			'version',
			$asset,
			'get_asset_data should return the asset array even when the file was already loaded.'
		);
		$this->assertArrayHasKey(
			'dependencies',
			$asset,
			'get_asset_data should expose dependencies even when the file was already loaded.'
		);
	}

	/**
	 * Coverage for get_asset_data when the asset file is missing.
	 *
	 * @covers ::get_asset_data
	 *
	 * @return void
	 */
	public function test_get_asset_data_returns_empty_array_for_missing_file(): void {
		$instance = Assets::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'asset_data', array() );
		$asset = Utility::invoke_hidden_method(
			$instance,
			'get_asset_data',
			array( 'does_not_exist', GATHERPRESS_CORE_PATH . '/build/missing.asset.php' )
		);

		$this->assertSame(
			array(),
			$asset,
			'get_asset_data should return an empty array when the asset file is missing.'
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
		$instance->register_block_assets();

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
		$instance->register_block_assets();

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
		$instance->register_block_assets();

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
	 * Coverage for maybe_enqueue_styles with a third-party prefix added via the
	 * `gatherpress_asset_utility_style_block_prefixes` filter.
	 *
	 * @covers ::maybe_enqueue_styles
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_styles_filter_adds_extra_prefix(): void {
		$instance = Assets::get_instance();

		$instance->register_block_assets();
		wp_dequeue_style( 'gatherpress-utility-style' );

		$callback = static function (): array {
			return array( 'gatherpress-awesome/' );
		};
		add_filter( 'gatherpress_asset_utility_style_block_prefixes', $callback );

		$block_content = '<div>Test</div>';
		$block         = array( 'blockName' => 'gatherpress-awesome/showcase' );

		$instance->maybe_enqueue_styles( $block_content, $block );

		remove_filter( 'gatherpress_asset_utility_style_block_prefixes', $callback );

		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is enqueued for a filter-added prefix.'
		);
	}

	/**
	 * `gatherpress/` is appended after the filter runs, so a filter that omits
	 * (or replaces) the array still leaves GatherPress's own blocks covered.
	 *
	 * @covers ::maybe_enqueue_styles
	 *
	 * @return void
	 */
	public function test_maybe_enqueue_styles_filter_cannot_remove_gatherpress_prefix(): void {
		$instance = Assets::get_instance();

		$instance->register_block_assets();
		wp_dequeue_style( 'gatherpress-utility-style' );

		$callback = static function (): array {
			return array();
		};
		add_filter( 'gatherpress_asset_utility_style_block_prefixes', $callback );

		$block_content = '<div>Test</div>';
		$block         = array( 'blockName' => 'gatherpress/event-date' );

		$instance->maybe_enqueue_styles( $block_content, $block );

		remove_filter( 'gatherpress_asset_utility_style_block_prefixes', $callback );

		$this->assertTrue(
			wp_style_is( 'gatherpress-utility-style', 'enqueued' ),
			'Failed to assert gatherpress-utility-style is still enqueued for gatherpress/ blocks '
			. 'when the filter returns an empty array.'
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

		// First register the utility style. The utility-style enqueue itself
		// moved into register_block_assets() so it loads on the
		// `enqueue_block_assets` hook and reaches the editor canvas iframe
		// (issue #1645); editor_enqueue_scripts() now only enqueues the
		// editor script.
		$instance->register_block_assets();

		$this->assertFalse(
			wp_script_is( 'gatherpress-editor', 'enqueued' ),
			'Failed to assert gatherpress-editor is not enqueued before calling editor_enqueue_scripts.'
		);

		$instance->editor_enqueue_scripts();

		$this->assertTrue(
			wp_script_is( 'gatherpress-editor', 'enqueued' ),
			'Failed to assert gatherpress-editor is enqueued.'
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
		$hook = 'gatherpress_event_page_gatherpress_events';

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
		$instance->register_block_assets();

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
		$instance->register_block_assets();

		// Call enqueue_tooltip_assets twice - second call should return early.
		Utility::invoke_hidden_method( $instance, 'enqueue_tooltip_assets' );
		Utility::invoke_hidden_method( $instance, 'enqueue_tooltip_assets' );

		// The test passes if no errors occur - the early return path is covered.
		$this->assertTrue( true, 'Second call should return early without error.' );
	}

	/**
	 * Coverage for enqueue_aql_integration when AQL is not active.
	 *
	 * @covers ::enqueue_aql_integration
	 *
	 * @return void
	 */
	public function test_enqueue_aql_integration_without_aql(): void {
		$instance = Assets::get_instance();

		// Ensure AQL is not registered.
		wp_deregister_script( 'advanced-query-loop' );

		$instance->enqueue_aql_integration();

		$this->assertFalse(
			wp_script_is( 'gatherpress-aql-integration', 'enqueued' ),
			'AQL integration should not be enqueued when AQL plugin is not active.'
		);
	}

	/**
	 * Coverage for enqueue_aql_integration when AQL is active but asset file is missing.
	 *
	 * @covers ::enqueue_aql_integration
	 *
	 * @return void
	 */
	public function test_enqueue_aql_integration_missing_asset_file(): void {
		$instance = Assets::get_instance();

		// Register a fake AQL script to simulate the plugin being active.
		wp_register_script( 'advanced-query-loop', 'https://example.com/aql.js', array(), '1.0.0', true );

		// Use reflection to temporarily set path to a non-existent directory.
		$reflection = new \ReflectionClass( $instance );
		$property   = $reflection->getProperty( 'path' );
		$property->setAccessible( true );
		$original_path = $property->getValue( $instance );
		$property->setValue( $instance, '/non/existent/path/' );

		$instance->enqueue_aql_integration();

		$this->assertFalse(
			wp_script_is( 'gatherpress-aql-integration', 'enqueued' ),
			'AQL integration should not be enqueued when asset file is missing.'
		);

		// Restore original path and clean up.
		$property->setValue( $instance, $original_path );
		wp_deregister_script( 'advanced-query-loop' );
	}

	/**
	 * Coverage for enqueue_aql_integration when AQL is active.
	 *
	 * @covers ::enqueue_aql_integration
	 *
	 * @return void
	 */
	public function test_enqueue_aql_integration_with_aql(): void {
		$instance = Assets::get_instance();

		// Register a fake AQL script to simulate the plugin being active.
		wp_register_script( 'advanced-query-loop', 'https://example.com/aql.js', array(), '1.0.0', true );

		$instance->enqueue_aql_integration();

		$this->assertTrue(
			wp_script_is( 'gatherpress-aql-integration', 'enqueued' ),
			'AQL integration should be enqueued when AQL plugin is active.'
		);

		// Verify AQL is a dependency.
		$script = wp_scripts()->registered['gatherpress-aql-integration'] ?? null;
		$this->assertNotNull( $script, 'Script should be registered.' );
		$this->assertContains(
			'advanced-query-loop',
			$script->deps,
			'AQL should be listed as a dependency.'
		);

		// Clean up.
		wp_dequeue_script( 'gatherpress-aql-integration' );
		wp_deregister_script( 'gatherpress-aql-integration' );
		wp_deregister_script( 'advanced-query-loop' );
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
		$instance->register_block_assets();

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

	/**
	 * Coverage for get_block_variations.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations(): void {
		$instance = Assets::get_instance();

		$this->assertSame(
			array(
				'query',
				'query-no-results',
				'query-pagination',
				'query-pagination-next',
				'query-pagination-numbers',
				'query-pagination-previous',
			),
			$instance->get_block_variations(),
			'Failed to assert, to get all block variations from the "/src" directory.'
		);
	}

	/**
	 * Coverage for get_block_variations when directory doesn't exist.
	 *
	 * Covers: Early return when variations directory doesn't exist.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations_directory_not_exists(): void {
		$instance         = Assets::get_instance();
		$variations_dir   = sprintf( '%1$s/build/variations/core/', GATHERPRESS_CORE_PATH );
		$temp_renamed_dir = sprintf( '%1$s/build/variations/core-temp-renamed/', GATHERPRESS_CORE_PATH );

		// Temporarily rename the variations directory to simulate non-existence.
		if ( file_exists( $variations_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Necessary for testing.
			rename( $variations_dir, $temp_renamed_dir );
		}

		// Reset the cached property to force a fresh check.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );

		// Now the directory doesn't exist, should return empty array.
		$result = $instance->get_block_variations();

		$this->assertSame( array(), $result, 'Should return empty array when variations directory does not exist.' );

		// Restore the directory.
		if ( file_exists( $temp_renamed_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Necessary for testing.
			rename( $temp_renamed_dir, $variations_dir );
		}

		// Reset the cache again for other tests.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );
	}

	/**
	 * Coverage for get_block_variations caching behavior.
	 *
	 * Covers: Caching of block variation names.
	 *
	 * @covers ::get_block_variations
	 *
	 * @return void
	 */
	public function test_get_block_variations_caching(): void {
		$instance = Assets::get_instance();

		// Reset the cache to ensure we're starting fresh.
		Utility::set_and_get_hidden_property( $instance, 'block_variation_names', array() );

		// Verify block_variation_names is empty initially.
		$cache_before = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertEmpty( $cache_before );

		// First call should populate the cache.
		$first_result = $instance->get_block_variations();

		// Verify cache is now populated (target code executed).
		$cache_after_first = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertNotEmpty( $cache_after_first );

		// Second call should use cached values (target code check causes early return from cache).
		$second_result = $instance->get_block_variations();

		// Verify cache wasn't modified by second call.
		$cache_after_second = Utility::get_hidden_property( $instance, 'block_variation_names' );
		$this->assertSame( $cache_after_first, $cache_after_second );

		// Both results should be identical.
		$this->assertSame( $first_result, $second_result, 'Should return cached variation names on subsequent calls.' );

		// Verify the final result matches the cached data after array_filter.
		$this->assertSame( array_filter( $cache_after_second ), $second_result );
	}
}
