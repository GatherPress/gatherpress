<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Base.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Base;
use GatherPress\Tests\Base as Base_Unit_Test;
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
		$instance = new Test_Base_Concrete();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_init',
				'priority' => 10,
				'callback' => array( $instance, 'init' ),
			),
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
		$instance  = new Test_Base_Concrete();
		$slug      = Utility::set_and_get_hidden_property( $instance, 'slug', 'unit-test' );
		$sub_pages = $instance->set_sub_page( array() );

		$this->assertIsArray( $sub_pages[ $slug ], 'Failed to assert sub page array was set.' );
		$this->assertSame( 'Test Name', $sub_pages[ $slug ]['name'], 'Failed to assert name is Test Name.' );
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
		$instance = new Test_Base_Concrete();
		$slug     = Utility::set_and_get_hidden_property( $instance, 'slug', 'unit-test' );

		$this->assertNull( $instance->get( 'unit-test' ), 'Failed to assert property is null.' );
		$this->assertSame( $slug, $instance->get( 'slug' ), 'Failed to assert property is unit-test.' );
	}

	/**
	 * Coverage for init method.
	 *
	 * @covers ::init
	 * @covers ::get_name
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_init(): void {
		$instance = new Test_Base_Concrete();

		// Call init to set name and sections.
		$instance->init();

		// Verify name is set from get_name().
		$this->assertSame( 'Test Name', $instance->get( 'name' ), 'Failed to assert name is Test Name.' );

		// Verify sections is set from get_sections().
		$this->assertIsArray( $instance->get( 'sections' ), 'Failed to assert sections is an array.' );
		$this->assertEmpty( $instance->get( 'sections' ), 'Failed to assert sections is empty array.' );
	}

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::__construct
	 * @covers ::get_slug
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_constructor_sets_properties(): void {
		$instance = new Test_Base_Concrete();

		// Verify slug is set from get_slug().
		$this->assertSame( 'test-slug', $instance->get( 'slug' ), 'Failed to assert slug is test-slug.' );

		// Verify priority is set from get_priority().
		$this->assertSame( 10, $instance->get( 'priority' ), 'Failed to assert priority is 10.' );
	}

	/**
	 * Coverage for page method.
	 *
	 * @covers ::page
	 * @covers ::get_name
	 * @covers ::get_priority
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_page(): void {
		$instance = new Test_Base_Concrete();
		$page     = $instance->page();

		$this->assertIsArray( $page, 'Failed to assert page is an array.' );
		$this->assertArrayHasKey( 'name', $page, 'Failed to assert page has name key.' );
		$this->assertArrayHasKey( 'priority', $page, 'Failed to assert page has priority key.' );
		$this->assertArrayHasKey( 'sections', $page, 'Failed to assert page has sections key.' );

		$this->assertSame( 'Test Name', $page['name'], 'Failed to assert page name is Test Name.' );
		$this->assertSame( 10, $page['priority'], 'Failed to assert page priority is 10.' );
		$this->assertIsArray( $page['sections'], 'Failed to assert page sections is an array.' );
		$this->assertEmpty( $page['sections'], 'Failed to assert page sections is empty array.' );
	}
}
