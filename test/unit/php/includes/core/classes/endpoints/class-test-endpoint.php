<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Endpoints;

use GatherPress\Core\Endpoints\Endpoint;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint
 * @group              endpoints
 */
class Test_Endpoint extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	
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

		// Create a mock for Endpoint.
		$endpoint_template = new Endpoint_Template( $slug, $callback, $plugin_default );

	} */

	/**
	 * Coverage for get_slugs method.
	 *
	 * @covers ::get_slugs
	 *
	 * @return void
	 */
	public function test_get_slugs(): void {

		// // Simulate 'init' hook being fired
		// \do_action('init');
					
		$callback = function(){};
		$instance = new Endpoint(
			'query_var',
			'post',
			$callback,
			array(
				new Endpoint_Template( 'endpoint_template_1', $callback ),
				new Endpoint_Template( 'endpoint_template_2', $callback ),
				new Endpoint_Redirect( 'endpoint_redirect_1', $callback ),
			),
			'reg_ex',
		);
		// var_dump($instance->types);

		$this->assertSame(
			array(
				'endpoint_template_1',
				'endpoint_template_2',
				'endpoint_redirect_1',
			),
			Utility::invoke_hidden_method( $instance, 'get_slugs' ),
			'Failed to assert that endpoint slugs match.'
		);

		// $this->mock->wp()->reset();

	}

}
