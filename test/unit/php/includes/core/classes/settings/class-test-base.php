<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Base.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Base;
use PMC\Unit_Test\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;

/**
 * Class Test_General.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Base
 */
class Test_Base extends Base_Unit_Test {
	/**
	 * Coverage for setup_up method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = new Base();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_sub_pages',
				'priority' => 10,
				'callback' => array( $instance, 'set_sub_page' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for set_sub_page method.
	 *
	 * @covers ::set_sub_page
	 * @covers ::page
	 *
	 * @return void
	 */
	public function test_set_sub_page(): void {
		$instance  = new Base();
		$slug      = Utility::set_and_get_hidden_property( $instance, 'slug', 'unit-test' );
		$sub_pages = $instance->set_sub_page( array() );

		$this->assertIsArray( $sub_pages[ $slug ], 'Failed to assert sub page array was set.' );
		$this->assertEmpty( $sub_pages[ $slug ]['name'], 'Failed to assert name is empty.' );
		$this->assertEquals( 10, $sub_pages[ $slug ]['priority'], 'Failed to assert priority is 10.' );
		$this->assertIsArray( $sub_pages[ $slug ]['sections'], 'Failed to assert sections is an array.' );
		$this->assertEmpty( $sub_pages[ $slug ]['sections'], 'Failed to assert sections is an empty array.' );
	}

	/**
	 * Coverage for get method.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get(): void {
		$instance = new Base();
		$slug     = Utility::set_and_get_hidden_property( $instance, 'slug', 'unit-test' );

		$this->assertNull( $instance->get( 'unit-test' ), 'Failed to assert property is null.' );
		$this->assertSame( $slug, $instance->get( 'slug' ), 'Failed to assert property is unit-test.' );
	}
}
