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
		$instance    = Modal::get_instance();
		$input_html  = '<div>Modal Content</div>';
		$block       = array(
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
		$instance    = Modal::get_instance();
		$input_html  = '<div>Modal Content</div>';
		$block       = array(
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
		$instance    = Modal::get_instance();
		$input_html  = '<div class="existing-class">Existing Content</div>';
		$block       = array(
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
		$instance    = Modal::get_instance();
		$input_html  = '';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'Output should remain empty when input is empty'
		);
	}

	/**
	 * Test z-index is applied with default value.
	 *
	 * @since  1.0.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_default_value(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'z-index: 1000;',
			$output_html,
			'Output should contain default z-index value'
		);
	}

	/**
	 * Test z-index is applied with custom value.
	 *
	 * @since  1.0.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_custom_value(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Content</div>';
		$block       = array(
			'attrs' => array(
				'zIndex' => 2000,
			),
		);
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'z-index: 2000;',
			$output_html,
			'Output should contain custom z-index value'
		);
	}

	/**
	 * Test z-index merges with existing styles.
	 *
	 * @since  1.0.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_merges_with_existing_styles(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div style="color: red; margin: 10px;">Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'color: red',
			$output_html,
			'Output should preserve existing styles'
		);
		$this->assertStringContainsString(
			'margin: 10px',
			$output_html,
			'Output should preserve all existing styles'
		);
		$this->assertStringContainsString(
			'z-index: 1000',
			$output_html,
			'Output should append z-index to existing styles'
		);
	}

	/**
	 * Test z-index handles empty style attribute.
	 *
	 * @since  1.0.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_with_empty_style(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div style="">Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'style="; z-index: 1000;"',
			$output_html,
			'Output should set z-index when style is empty'
		);
	}

	/**
	 * Test login modal is removed for logged-in users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_removed_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Login Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-login',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'Login modal should be empty for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test login modal remains for logged-out users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_remains_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Login Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-login',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Login modal should remain for logged-out users'
		);
	}


	/**
	 * Test non-login modal remains for logged-in users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_non_login_modal_remains_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Regular Modal</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'other-class',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Non-login modal should remain for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test modal without className attribute.
	 *
	 * @since  1.0.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_without_classname(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Modal without className should remain unchanged'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Test RSVP modal is removed for logged-out users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_removed_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>RSVP Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-rsvp',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'RSVP modal should be empty for logged-out users'
		);
	}


	/**
	 * Test RSVP modal remains for logged-in users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_remains_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>RSVP Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-rsvp',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'RSVP modal should remain for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test non-RSVP modal remains for logged-out users.
	 *
	 * @since  1.0.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_non_rsvp_modal_remains_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Regular Modal</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'other-class',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Non-RSVP modal should remain for logged-out users'
		);
	}


	/**
	 * Test modal without className attribute.
	 *
	 * @since  1.0.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_without_classname(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Modal without className should remain unchanged'
		);
	}
}
