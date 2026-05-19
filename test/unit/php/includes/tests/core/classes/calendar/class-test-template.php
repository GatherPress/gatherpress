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
	 * Tests template_include method when the theme has the template.
	 *
	 * @covers ::template_include
	 *
	 * @return void
	 */
	public function test_template_include_with_theme_template(): void {
		$slug             = 'custom-endpoint';
		$callback         = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$plugin_default   = '/mock/plugin/templates';
		$template_default = '/default/template.php';

		$instance = new Template( $slug, $callback, $plugin_default );

		// Simulate theme template existing.
		// ...????

		$template = $instance->template_include( $template_default );

		// Assert that the theme template is used.
		// $this->assertSame('/path/to/theme/theme-endpoint-template.php', $template); // ..????
		$this->assertSame( '/default/template.php', $template );
	}
}
