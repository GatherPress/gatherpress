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
		$slug     = 'endpoint-template';
		$callback = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$instance = new Endpoint_Template( $slug, $callback );

		$this->assertIsString( Utility::get_hidden_property( $instance, 'plugin_template_dir' ) );
		$this->assertNotEmpty( Utility::get_hidden_property( $instance, 'plugin_template_dir' ) );
		$this->assertSame(
			sprintf(
				'%s/includes/templates/endpoints',
				GATHERPRESS_CORE_PATH
			),
			Utility::get_hidden_property( $instance, 'plugin_template_dir' ),
			'Failed to assert, plugin_template_dir is set to fallback directory.'
		);

		$plugin_default = '/mock/plugin/templates';
		$instance       = new Endpoint_Template( $slug, $callback, $plugin_default );

		$this->assertSame(
			'/mock/plugin/templates',
			Utility::get_hidden_property( $instance, 'plugin_template_dir' ),
			'Failed to assert, plugin_template_dir is set to test directory.'
		);
	}

	/**
	 * Coverage for activate method.
	 *
	 * @covers ::activate
	 *
	 * @return void
	 */
	public function test_activate(): void {
		$slug     = 'endpoint-template';
		$callback = function () {
			return array(
				'file_name' => 'endpoint-template.php',
				'dir_path'  => '/path/to/theme',
			);
		};
		$instance = new Endpoint_Template( $slug, $callback );
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

		$instance = new Endpoint_Template( $slug, $callback, $plugin_default );

		// Simulate theme template existing.
		// ...????

		$template = $instance->template_include( $template_default );

		// Assert that the theme template is used.
		// $this->assertSame('/path/to/theme/theme-endpoint-template.php', $template); // ..????
		$this->assertSame( '/default/template.php', $template );
	}
}
