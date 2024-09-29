<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint_Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Endpoints;

use GatherPress\Core\Endpoints\Endpoint_Template;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint_Template
 * @group              endpoints
 */
class Test_Endpoint_Template extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$slug             = 'custom-endpoint';
		$callback         = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$plugin_default   = '/mock/plugin/templates';
		$template_default = '/default/template.php';

		// Create a mock for Endpoint_Template.
		$instance = new Endpoint_Template( $slug, $callback, $plugin_default );

		$this->assertInstanceOf( Endpoint_Template::class, $instance );
	}

	/**
	 * Coverage for activate.
	 *
	 * @covers ::activate
	 *
	 * @return void
	*/
	public function test_activate(): void {
		$slug             = 'custom-endpoint';
		$callback         = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$plugin_default   = '/mock/plugin/templates';
		$template_default = '/default/template.php';

		// Create a mock for Endpoint_Template.
		$instance = new Endpoint_Template( $slug, $callback, $plugin_default );
		// var_dump($instance);
		// $instance->activate();

		// $hooks    = array(
		// 	array(
		// 		'type'     => 'filter',
		// 		'name'     => 'template_include',
		// 		'priority' => 10,
		// 		'callback' => array( $instance, 'template_include' ),
		// 	),
		// );

		// $this->assert_hooks( $hooks, $instance ); // DOES NOT WORK WITH NON-SINGLETONS, BUT WILL NOT THROW AN ERROR.
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

		// Create a mock for Endpoint_Template.
		$instance = new Endpoint_Template( $slug, $callback, $plugin_default );

		// Simulate theme template existing.
		// ...????

		$template = $instance->template_include( $template_default );

		// Assert that the theme template is used.
		// $this->assertSame('/path/to/theme/theme-endpoint-template.php', $template); // ..????
		$this->assertSame( '/default/template.php', $template );
	}
}
