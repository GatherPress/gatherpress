<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Modal_Manager.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Modal_Manager;
use GatherPress\Tests\Base;

/**
 * Class Test_Modal_Manager.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Modal_Manager
 */
class Test_Modal_Manager extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Modal_Manager block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Modal_Manager::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Modal_Manager::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'attach_modal_open_behavior' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'attach_modal_close_behavior' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}
}
