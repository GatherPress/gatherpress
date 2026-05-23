<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Template.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Template
 * @group              endpoints
 */
class Test_Template extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$slug     = 'endpoint-template';
		$callback = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$instance = new Template( $slug, $callback );

		$this->assertIsString( Utility::get_hidden_property( $instance, 'plugin_template_dir' ) );
		$this->assertNotEmpty( Utility::get_hidden_property( $instance, 'plugin_template_dir' ) );
		$this->assertSame(
			sprintf(
				'%s/includes/templates/calendar',
				GATHERPRESS_CORE_PATH
			),
			Utility::get_hidden_property( $instance, 'plugin_template_dir' ),
			'Failed to assert, plugin_template_dir is set to fallback directory.'
		);

		$plugin_default = '/mock/plugin/templates';
		$instance       = new Template( $slug, $callback, $plugin_default );

		$this->assertSame(
			'/mock/plugin/templates',
			Utility::get_hidden_property( $instance, 'plugin_template_dir' ),
			'Failed to assert, plugin_template_dir is set to test directory.'
		);
	}

	/**
	 * Activate with no Endpoint registers the template_include filter so the
	 * theme/plugin lookup runs for non-feed endpoints.
	 *
	 * @covers ::activate
	 *
	 * @return void
	 */
	public function test_activate_without_endpoint_hooks_template_include(): void {
		$slug     = 'endpoint-template';
		$callback = static function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$instance = new Template( $slug, $callback );

		remove_all_filters( 'template_include' );

		$instance->activate();

		$this->assertTrue(
			has_filter( 'template_include', array( $instance, 'template_include' ) ) > 0,
			'activate() without an endpoint should hook template_include.'
		);
	}

	/**
	 * Activate with a feed-shaped Endpoint hooks the matching do_feed_{slug}
	 * action instead of template_include — feeds are dispatched via WP core's
	 * feed system, not the regular template loader.
	 *
	 * @covers ::activate
	 *
	 * @return void
	 */
	public function test_activate_with_feed_endpoint_hooks_do_feed_action(): void {
		$slug     = 'ical';
		$callback = static function () {
			return array(
				'file_name' => 'ical-feed.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$instance = new Template( $slug, $callback );

		// Stand in for the Endpoint dependency just enough to satisfy activate().
		$endpoint = $this->getMockBuilder( \GatherPress\Core\Calendar\Endpoint::class )
			->disableOriginalConstructor()
			->getMock();
		$endpoint->method( 'has_feed' )->willReturn( 'ical' );

		remove_all_actions( 'do_feed_ical' );

		$instance->activate( $endpoint );

		$this->assertTrue(
			has_action( 'do_feed_ical', array( $instance, 'load_feed_template' ) ) > 0,
			'activate() with a feed endpoint should hook do_feed_ical.'
		);
	}

	/**
	 * When neither the theme nor the plugin directory has the template file,
	 * template_include falls through to the WP-supplied default.
	 *
	 * @covers ::template_include
	 * @covers ::get_template_presets
	 * @covers ::get_template_from_theme
	 * @covers ::get_template_from_plugin
	 *
	 * @return void
	 */
	public function test_template_include_falls_through_to_default(): void {
		$slug             = 'custom-endpoint';
		$callback         = static function () {
			return array(
				'file_name' => 'gatherpress-missing-template.php',
				'dir_path'  => '/nonexistent/plugin/templates',
			);
		};
		$template_default = '/default/template.php';

		$instance = new Template( $slug, $callback, '/nonexistent/plugin/templates' );

		$template = $instance->template_include( $template_default );

		$this->assertSame(
			'/default/template.php',
			$template,
			'When no override exists template_include should return the WP default.'
		);
	}

	/**
	 * When the plugin's bundled template directory has the file but the active
	 * theme does not, template_include returns the plugin path.
	 *
	 * @covers ::template_include
	 * @covers ::get_template_presets
	 * @covers ::get_template_from_theme
	 * @covers ::get_template_from_plugin
	 *
	 * @return void
	 */
	public function test_template_include_returns_plugin_template_when_present(): void {
		$slug = 'ical';
		// Prefix uses an underscore (`gatherpress_…`) per Utility::prefix_key;
		// the production iCal templates pass through that helper.
		$callback         = static function () {
			return array(
				'file_name' => 'gatherpress_ical-download.php',
			);
		};
		$template_default = '/default/template.php';

		// The bundled `ical-download.php` template ships in the calendar
		// folder under GATHERPRESS_CORE_PATH, so the default plugin_template_dir
		// (set by the constructor when the third arg is empty) resolves it.
		$instance = new Template( $slug, $callback );

		$template = $instance->template_include( $template_default );

		$this->assertSame(
			sprintf( '%s/includes/templates/calendar/ical-download.php', GATHERPRESS_CORE_PATH ),
			$template,
			'template_include should return the bundled plugin template when it exists.'
		);
	}

	/**
	 * When `get_template_from_theme()` returns a non-empty path,
	 * template_include short-circuits and returns it without consulting the
	 * plugin directory. Verifying via reflection-invoked subcomponents keeps
	 * the test independent of `locate_template()`'s theme-resolution rules,
	 * which are difficult to set up reliably in the unit-test environment.
	 *
	 * @covers ::template_include
	 * @covers ::get_template_presets
	 *
	 * @return void
	 */
	public function test_template_include_returns_theme_template_when_present(): void {
		$slug     = 'ical';
		$callback = static function () {
			return array(
				'file_name' => 'gatherpress_ical-download.php',
			);
		};

		$theme_template = '/path/to/theme/ical-download.php';

		// Subclass that stubs the protected theme/plugin lookups so we exercise
		// the early-return branch of template_include() without depending on
		// the filesystem.
		$instance                 = new class( $slug, $callback, '/nonexistent' ) extends Template {

			/**
			 * Stub return value for the overridden lookup.
			 *
			 * @var string
			 */
			public string $theme_template = '';

			/**
			 * Override that returns the stubbed theme template path.
			 *
			 * @param string $file_name Template file name (unused in stub).
			 * @return string
			 */
			protected function get_template_from_theme( string $file_name ): string {
				return $this->theme_template;
			}
		};
		$instance->theme_template = $theme_template;

		$this->assertSame(
			$theme_template,
			$instance->template_include( '/default/template.php' ),
			'template_include should return the theme template when it is non-empty.'
		);
	}

	/**
	 * Direct coverage for `get_template_from_theme()` — exercises
	 * locate_template + locate_block_template against a real on-disk file
	 * placed in the registered stylesheet directory.
	 *
	 * @covers ::get_template_from_theme
	 *
	 * @return void
	 */
	public function test_get_template_from_theme_locates_existing_file(): void {
		$file_name = 'gatherpress-calendar-test-' . wp_generate_password( 6, false, false ) . '.php';
		$tmp_dir   = sys_get_temp_dir() . '/gatherpress-test-theme-' . wp_generate_password( 6, false, false );

		wp_mkdir_p( $tmp_dir );
		$theme_path = trailingslashit( $tmp_dir ) . $file_name;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tmp scratch dir under sys_get_temp_dir().
		file_put_contents( $theme_path, "<?php // Test stub.\n" );

		// Override the stylesheet/template paths so locate_template() looks at
		// our scratch directory instead of the real active theme.
		$override = static function () use ( $tmp_dir ) {
			return $tmp_dir;
		};
		add_filter( 'stylesheet_directory', $override );
		add_filter( 'template_directory', $override );

		try {
			$instance = new Template( 'ical', static function () {} );
			$result   = Utility::invoke_hidden_method(
				$instance,
				'get_template_from_theme',
				array( $file_name )
			);

			$this->assertSame(
				$theme_path,
				$result,
				'get_template_from_theme should resolve via locate_template when the file lives in the theme directory.'
			);
		} finally {
			remove_filter( 'stylesheet_directory', $override );
			remove_filter( 'template_directory', $override );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Tmp scratch dir.
			unlink( $theme_path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Tmp scratch dir.
			rmdir( $tmp_dir );
		}
	}

	/**
	 * Direct coverage for `get_template_from_plugin()` — returns the resolved
	 * path when the file exists in the plugin's template directory, an empty
	 * string when it does not. Also confirms the `gatherpress_` prefix is
	 * stripped when the configured dir matches the bundled template dir.
	 *
	 * @covers ::get_template_from_plugin
	 *
	 * @return void
	 */
	public function test_get_template_from_plugin_resolves_and_falls_back(): void {
		$instance = new Template( 'ical', static function () {} );

		// Bundled template under the default plugin_template_dir — the
		// `gatherpress_` prefix should be stripped before the file check.
		$bundled = Utility::invoke_hidden_method(
			$instance,
			'get_template_from_plugin',
			array( 'gatherpress_ical-download.php', Utility::get_hidden_property( $instance, 'plugin_template_dir' ) )
		);
		$this->assertSame(
			sprintf( '%s/includes/templates/calendar/ical-download.php', GATHERPRESS_CORE_PATH ),
			$bundled,
			'Should resolve the bundled iCal template and strip the gatherpress_ prefix.'
		);

		// Non-existent file → empty string fallback.
		$missing = Utility::invoke_hidden_method(
			$instance,
			'get_template_from_plugin',
			array( 'definitely-missing.php', '/nonexistent/dir' )
		);
		$this->assertSame(
			'',
			$missing,
			'Should return an empty string when the resolved file does not exist.'
		);
	}

	/**
	 * Delegates to template_include and loads the resolved template via
	 * WordPress's `load_template()`.
	 *
	 * @covers ::load_feed_template
	 *
	 * @return void
	 */
	public function test_load_feed_template_loads_resolved_template(): void {
		$tmp_template = wp_tempnam( 'calendar-test-load-feed' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tmp scratch file.
		file_put_contents( $tmp_template, "<?php // Test stub.\n" );

		$slug     = 'ical';
		$dir_path = dirname( $tmp_template );
		$file     = basename( $tmp_template );
		$callback = static function () use ( $file, $dir_path ) {
			return array(
				'file_name' => $file,
				'dir_path'  => $dir_path,
			);
		};

		$instance = new Template( $slug, $callback, $dir_path );

		// Pure smoke test — load_feed_template returns void; we just verify
		// that resolving the template and loading it does not error.
		$instance->load_feed_template();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Tmp scratch file.
		unlink( $tmp_template );

		$this->assertTrue(
			true,
			'load_feed_template should resolve the template path and load it without error.'
		);
	}
}
