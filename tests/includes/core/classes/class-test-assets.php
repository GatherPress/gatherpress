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
	 * Coverage for setup_hooks.
	 *
	 * @return void
	 */
	public function test_setup_hooks() {
		$instance = Assets::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_print_scripts',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_enqueue_scripts',
				'priority' => 10,
				'callback' => array( $instance, 'admin_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_assets',
				'priority' => 10,
				'callback' => array( $instance, 'enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'enqueue_block_editor_assets',
				'priority' => 10,
				'callback' => array( $instance, 'editor_enqueue_scripts' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_head',
				'priority' => PHP_INT_MIN,
				'callback' => array( $instance, 'add_global_object' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for unregister_blocks.
	 *
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
					'gatherpress/event-venue',
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
					'gatherpress/event-venue',
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
					'gatherpress/event-venue',
				),
			),
		);
	}

	/**
	 * Coverage for unregister_blocks.
	 *
	 * @param string $post_type       Post type.
	 * @param array  $expected_blocks Array of blocks.
	 *
	 * @dataProvider date_unregister_blocks_admin
	 * @covers ::unregister_blocks
	 *
	 * @return void
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
