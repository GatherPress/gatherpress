<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Template.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
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
	 * When a theme provides the override file, `template_include` returns the
	 * theme path without consulting the plugin directory. Exercises the real
	 * `locate_template()` resolution by pointing the registered stylesheet
	 * directory at a scratch dir.
	 *
	 * @covers ::template_include
	 * @covers ::get_template_presets
	 *
	 * @return void
	 */
	public function test_template_include_returns_theme_template_when_present(): void {
		$file_name = 'gatherpress_ical-test-' . wp_generate_password( 6, false, false ) . '.php';
		$tmp_dir   = sys_get_temp_dir() . '/gatherpress-test-theme-' . wp_generate_password( 6, false, false );

		wp_mkdir_p( $tmp_dir );
		// Theme files live as the unprefixed name (the prefix is plugin-side).
		$theme_path = trailingslashit( $tmp_dir ) . $file_name;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tmp scratch dir under sys_get_temp_dir().
		file_put_contents( $theme_path, "<?php // Test stub.\n" );

		$override = static function () use ( $tmp_dir ) {
			return $tmp_dir;
		};
		add_filter( 'stylesheet_directory', $override );
		add_filter( 'template_directory', $override );

		try {
			$callback = static function () use ( $file_name ) {
				return array( 'file_name' => $file_name );
			};
			$instance = new Template( 'ical', $callback, '/nonexistent' );

			$this->assertSame(
				$theme_path,
				$instance->template_include( '/default/template.php' ),
				'template_include should return the theme template when locate_template resolves it.'
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
	 * Delegates to template_include and loads the resolved template via
	 * `Utility::render_template()`.
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
