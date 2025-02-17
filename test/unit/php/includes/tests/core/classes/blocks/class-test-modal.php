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

	/**
	 * Test modal attributes are applied with custom name.
	 *
	 * @since  1.0.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_custom_name(): void {
		$instance = Modal::get_instance();

		$input_html = '<div>Modal Content</div>';
		$block      = array(
			'attrs' => array(
				'metadata' => array(
					'name' => 'Custom Modal Name',
				),
			),
		);

		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'role="dialog"',
			$output_html,
			'Output should contain dialog role'
		);
		$this->assertStringContainsString(
			'aria-modal="true"',
			$output_html,
			'Output should contain aria-modal attribute'
		);
		$this->assertStringContainsString(
			'aria-hidden="true"',
			$output_html,
			'Output should contain aria-hidden attribute'
		);
		$this->assertStringContainsString(
			'aria-label="Custom Modal Name"',
			$output_html,
			'Output should contain custom aria-label'
		);
		$this->assertStringContainsString(
			'tabindex="-1"',
			$output_html,
			'Output should contain negative tabindex'
		);
	}

	/**
	 * Test modal attributes are applied with default name.
	 *
	 * @since  1.0.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_default_name(): void {
		$instance = Modal::get_instance();

		$input_html = '<div>Modal Content</div>';
		$block      = array(
			'attrs' => array(),
		);

		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'role="dialog"',
			$output_html,
			'Output should contain dialog role'
		);
		$this->assertStringContainsString(
			'aria-modal="true"',
			$output_html,
			'Output should contain aria-modal attribute'
		);
		$this->assertStringContainsString(
			'aria-hidden="true"',
			$output_html,
			'Output should contain aria-hidden attribute'
		);
		$this->assertStringContainsString(
			'aria-label="Modal"',
			$output_html,
			'Output should contain default aria-label'
		);
		$this->assertStringContainsString(
			'tabindex="-1"',
			$output_html,
			'Output should contain negative tabindex'
		);
	}

	/**
	 * Test modal attributes preserve existing content.
	 *
	 * @since  1.0.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_preserve_content(): void {
		$instance = Modal::get_instance();

		$input_html = '<div class="existing-class">Existing Content</div>';
		$block      = array(
			'attrs' => array(),
		);

		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'class="existing-class"',
			$output_html,
			'Output should preserve existing classes'
		);
		$this->assertStringContainsString(
			'Existing Content',
			$output_html,
			'Output should preserve existing content'
		);
	}

	/**
	 * Test modal attributes with empty HTML.
	 *
	 * @since  1.0.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_empty_html(): void {
		$instance = Modal::get_instance();

		$input_html = '';
		$block      = array(
			'attrs' => array(),
		);

		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'Output should remain empty when input is empty'
		);
	}
}
