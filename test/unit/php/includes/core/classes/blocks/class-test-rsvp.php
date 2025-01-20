<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp;
use PMC\Unit_Test\Base;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp
 */
class Test_Rsvp extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'transform_block_content' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_rsvp_button_interactivity' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 11,
				'callback' => array( $instance, 'apply_guest_count_watch' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests the apply_guest_count_watch method with no guests.
	 *
	 * Ensures that when the guest count is zero, the appropriate
	 * data-wp-watch attribute and visibility class are applied
	 * to the guest count display element.
	 *
	 * @since 1.0.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_with_no_guests(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div data-user-details=\'{"guests":0}\'><div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div data-user-details=\'{"guests":0}\'><div data-wp-watch="callbacks.updateGuestCountDisplay" class="wp-block-gatherpress-rsvp-guest-count-display gatherpress--is-not-visible">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame( $expected, $result );
	}
}
