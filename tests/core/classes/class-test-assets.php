<?php
/**
 * Class handles unit tests for GatherPress\Core\Assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Assets;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Assets.
 *
 * @coversDefaultClass \GatherPress\Core\Assets
 */
class Test_Assets extends Base {

	/**
	 * @covers ::unregister_blocks
	 */
	public function test_unregister_blocks_frontend() {
		$instance = Assets::get_instance();

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( array(), $blocks );
		$this->mock->wp()->reset();
	}

	/**
	 * Data provider for unregister_blocks_admin test.
	 *
	 * @return array
	 */
	public function date_unregister_blocks_admin(): array {
		return array(
			array(
				'post',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/attendance-list',
					'gatherpress/attendance-selector',
					'gatherpress/event-date',
					'gatherpress/venue',
					'gatherpress/venue-information',
				),
			),
			array(
				'page',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/attendance-list',
					'gatherpress/attendance-selector',
					'gatherpress/event-date',
					'gatherpress/venue',
					'gatherpress/venue-information',
				),
			),
			array(
				'gp_event',
				array(
					'gatherpress/venue-information',
				),
			),
			array(
				'gp_venue',
				array(
					'gatherpress/add-to-calendar',
					'gatherpress/attendance-list',
					'gatherpress/attendance-selector',
					'gatherpress/event-date',
					'gatherpress/venue',
				),
			),
		);
	}
	/**
	 * @dataProvider date_unregister_blocks_admin
	 * @covers ::unregister_blocks
	 */
	public function test_unregister_blocks_admin( $post_type, $expected_blocks ) {
		$instance = Assets::get_instance();

		$this->mock->post( array( 'post_type' => $post_type ) );
		$this->mock->user( 'admin', 'wp-admin-page' );

		$blocks = Utility::invoke_hidden_method( $instance, 'unregister_blocks' );
		$this->assertSame( $expected_blocks, $blocks );

		$this->mock->wp()->reset();
	}

}
