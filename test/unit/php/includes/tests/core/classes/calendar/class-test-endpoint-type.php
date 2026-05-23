<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Endpoint_Type.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Endpoint_Type;
use GatherPress\Core\Calendar\Redirect;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint_Type.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Endpoint_Type
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
		$endpoint_template = new Template( $slug, $callback );

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
		$instance = new Redirect( 'slug', function () {} );

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_of_class', array( 'GatherPress\Core\Calendar\Redirect' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'is_of_class', array( 'GatherPress\Core\Calendar\Template' ) ),
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
		$instance = new Redirect( 'slug', function () {} );

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'GatherPress\Core\Calendar\Redirect' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'GatherPress\Core\Calendar\Template' ) ),
			'Failed to validate class in namespace.'
		);

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'is_in_class', array( 'WP_Post' ) ),
			'Failed to validate class is not in namespace.'
		);
	}
}
