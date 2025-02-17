<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Modal.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Modal;
use GatherPress\Tests\Base;

/**
 * Class Test_Modal.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Modal
 */
class Test_Modal extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Modal block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Modal::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Modal::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_modal_attributes' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'adjust_block_z_index' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'filter_login_modal' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'filter_rsvp_modal' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}
}
