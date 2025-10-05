<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint_Type.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Endpoints;

use GatherPress\Core\Endpoints\Endpoint_Type;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint_Type.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint_Type
 * @group              endpoints
 */
class Test_Endpoint_Type extends Base {
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

		// Create a mock for Endpoint.
		$endpoint_template = new Endpoint_Template( $slug, $callback );

		$this->assertIsString( $endpoint_template->slug );
		$this->assertIsCallable( Utility::get_hidden_property( $endpoint_template, 'callback' ) );
	}

	/**
	 * Coverage for is_of_class method.
	 *
	 * @covers ::is_of_class
	 *
	 * @return void
	 */
	public function test_is_of_class(): void {
		$instance = new Endpoint_Redirect( 'slug', function () {} );

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_of_class', array( 'GatherPress\Core\Endpoints\Endpoint_Redirect' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'is_of_class', array( 'GatherPress\Core\Endpoints\Endpoint_Template' ) ),
			'Failed to validate non-used class in namespace.'
		);
	}

	/**
	 * Coverage for is_in_class method.
	 *
	 * @covers ::is_in_class
	 *
	 * @return void
	 */
	public function test_is_in_class(): void {
		$instance = new Endpoint_Redirect( 'slug', function () {} );

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'GatherPress\Core\Endpoints\Endpoint_Redirect' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'GatherPress\Core\Endpoints\Endpoint_Template' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'WP_Post' ) ),
			'Failed to validate class is not in namespace.'
		);
	}
}
