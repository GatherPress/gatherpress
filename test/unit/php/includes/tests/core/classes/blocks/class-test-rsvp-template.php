<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Template
 */
class Test_Rsvp_Template extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP Template block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp_Template::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp_Template::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'ensure_block_styles_loaded' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'generate_rsvp_template_block' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}
}
