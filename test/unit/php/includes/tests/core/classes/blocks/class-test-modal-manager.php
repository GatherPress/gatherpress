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

	/**
	 * Test modal open behavior is applied to non-link elements with open modal class.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_button(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-open"><button>Button</button></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Output should contain keydown handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'tabindex="0"',
			$output_html,
			'Output should contain tabindex'
		);
	}

	/**
	 * Test modal open behavior is applied to link elements with open modal class.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_link(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-open"><a href="#">Open Modal</a></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringNotContainsString(
			'data-wp-on--keydown',
			$output_html,
			'Output should not contain keydown handler for links'
		);
		$this->assertStringNotContainsString(
			'tabindex',
			$output_html,
			'Output should not contain tabindex for links'
		);
	}

	/**
	 * Test modal open behavior with button element having modal class directly.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_direct_button_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<button class="gatherpress-modal--trigger-open">Open Modal</button>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Output should contain keydown handler for buttons'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
	}

	/**
	 * Test modal open behavior with anchor element having modal class directly.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_direct_anchor_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<a href="#" class="gatherpress-modal--trigger-open">Open Modal</a>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role for links'
		);
		$this->assertStringNotContainsString(
			'data-wp-on--keydown',
			$output_html,
			'Output should not contain keydown handler for links'
		);
	}

	/**
	 * Test modal open behavior is not applied to elements without open modal class.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_not_applied_without_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="other-class">Regular Content</div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Content should remain unchanged when modal class is not present'
		);
	}

	/**
	 * Test modal open behavior with multiple elements in content.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_multiple_elements(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '
			<div>
				<div class="gatherpress-modal--trigger-open"><button>Button</button></div>
				<p>Regular content</p>
				<div class="gatherpress-modal--trigger-open"><button>Button</button></div>
			</div>
		';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertEquals(
			2,
			substr_count( $output_html, 'data-wp-interactive="gatherpress"' ),
			'Should apply interactive attribute to both modal trigger elements'
		);
		$this->assertEquals(
			2,
			substr_count( $output_html, 'role="button"' ),
			'Should apply button role to both modal trigger elements'
		);
		$this->assertStringContainsString(
			'Regular content',
			$output_html,
			'Should preserve non-modal content'
		);
	}

	/**
	 * Test modal open behavior with mixed class names.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_mixed_classes(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="custom-class gatherpress-modal--trigger-open another-class">'
			. '<button>Button</button></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Should apply interactive attribute when modal class is mixed with others'
		);
		$this->assertStringContainsString(
			'custom-class',
			$output_html,
			'Should preserve existing classes'
		);
		$this->assertStringContainsString(
			'another-class',
			$output_html,
			'Should preserve all existing classes'
		);
	}

	/**
	 * Test modal close behavior is applied to div with nested button element.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_button(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
			. '<button type="button">Close Modal</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.closeModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.closeModalOnEnter"',
			$output_html,
			'Output should contain keydown handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'tabindex="0"',
			$output_html,
			'Output should contain tabindex'
		);
	}

	/**
	 * Test modal close behavior is applied to div with nested anchor element.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_link(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
			. '<a href="#">Close Modal</a></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.closeModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringNotContainsString(
			'data-wp-on--keydown',
			$output_html,
			'Output should not contain keydown handler for links'
		);
		$this->assertStringNotContainsString(
			'tabindex',
			$output_html,
			'Output should not contain tabindex for links'
		);
	}

	/**
	 * Test close behavior is not applied to elements without close modal class.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_not_applied_without_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="other-class"><button type="button">Regular Button</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Content should remain unchanged when modal class is not present'
		);
	}

	/**
	 * Test close behavior with multiple elements in content.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_multiple_elements(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '
			<div>
				<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
				. '<button type="button">Close First Modal</button></div>
				<p>Regular content</p>
				<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
				. '<a href="#">Close Second Modal</a></div>
			</div>
		';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertEquals(
			2,
			substr_count( $output_html, 'data-wp-interactive="gatherpress"' ),
			'Should apply interactive attribute to both modal trigger elements'
		);
		$this->assertEquals(
			2,
			substr_count( $output_html, 'role="button"' ),
			'Should apply button role to both modal trigger elements'
		);
		$this->assertStringContainsString(
			'Regular content',
			$output_html,
			'Should preserve non-modal content'
		);
	}

	/**
	 * Test close behavior with mixed class names.
	 *
	 * @since  1.0.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_mixed_classes(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="custom-class gatherpress-modal--trigger-close another-class" '
			. 'data-close-modal="true"><button type="button">Close Modal</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Should apply interactive attribute when modal class is mixed with others'
		);
		$this->assertStringContainsString(
			'custom-class',
			$output_html,
			'Should preserve existing classes'
		);
		$this->assertStringContainsString(
			'another-class',
			$output_html,
			'Should preserve all existing classes'
		);
	}
}
