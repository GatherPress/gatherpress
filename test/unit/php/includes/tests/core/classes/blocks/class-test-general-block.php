<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\General_Block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\General_Block;
use GatherPress\Tests\Base;

/**
 * Class Test_General_Block.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\General_Block
 */
class Test_General_Block extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for General_Block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = General_Block::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'process_login_block' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'process_registration_block' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test block content is removed when user is logged in and block has login URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_login_block
	 *
	 * @return void
	 */
	public function test_block_removed_when_user_logged_in(): void {
		$general_block = General_Block::get_instance();

		// Create and log in a test user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example gatherpress--has-login-url',
			),
		);

		$result = $general_block->process_login_block( $block_content, $block );

		$this->assertEmpty(
			$result,
			'Block content should be empty when user is logged in and block has login URL class.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Test block content remains when user is not logged in but block has login URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_login_block
	 *
	 * @return void
	 */
	public function test_block_remains_when_user_not_logged_in(): void {
		$general_block = General_Block::get_instance();

		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example gatherpress--has-login-url',
			),
		);

		$result = $general_block->process_login_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when user is not logged in.'
		);
	}

	/**
	 * Test block content remains when user is logged in but block doesn't have login URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_login_block
	 *
	 * @return void
	 */
	public function test_block_remains_without_login_url_class(): void {
		$general_block = General_Block::get_instance();

		// Create and log in a test user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example',
			),
		);

		$result = $general_block->process_login_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when block does not have login URL class.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Test block content remains when block has no className attribute.
	 *
	 * @since  1.0.0
	 * @covers ::process_login_block
	 *
	 * @return void
	 */
	public function test_login_block_remains_without_classname_attribute(): void {
		$general_block = General_Block::get_instance();

		// Create and log in a test user.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(),
		);

		$result = $general_block->process_login_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when block has no className attribute.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Test block content is removed when registration is disabled and block has registration URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_registration_block
	 *
	 * @return void
	 */
	public function test_block_removed_when_registration_disabled(): void {
		$general_block = General_Block::get_instance();

		// Disable user registration.
		update_option( 'users_can_register', 0 );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example gatherpress--has-registration-url',
			),
		);

		$result = $general_block->process_registration_block( $block_content, $block );

		$this->assertEmpty(
			$result,
			'Block content should be empty when registration is disabled and block has registration URL class.'
		);
	}


	/**
	 * Test block content remains when registration is enabled and block has registration URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_registration_block
	 *
	 * @return void
	 */
	public function test_block_remains_when_registration_enabled(): void {
		$general_block = General_Block::get_instance();

		// Enable user registration.
		update_option( 'users_can_register', 1 );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example gatherpress--has-registration-url',
			),
		);

		$result = $general_block->process_registration_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when registration is enabled.'
		);
	}


	/**
	 * Test block content remains when registration is disabled but block doesn't have registration URL class.
	 *
	 * @since  1.0.0
	 * @covers ::process_registration_block
	 *
	 * @return void
	 */
	public function test_block_remains_without_registration_url_class(): void {
		$general_block = General_Block::get_instance();

		// Disable user registration.
		update_option( 'users_can_register', 0 );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-example',
			),
		);

		$result = $general_block->process_registration_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when block does not have registration URL class.'
		);
	}


	/**
	 * Test block content remains when block has no className attribute.
	 *
	 * @since  1.0.0
	 * @covers ::process_registration_block
	 *
	 * @return void
	 */
	public function test_registration_block_remains_without_classname_attribute(): void {
		$general_block = General_Block::get_instance();

		// Disable user registration.
		update_option( 'users_can_register', 0 );

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(),
		);

		$result = $general_block->process_registration_block( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block content should remain unchanged when block has no className attribute.'
		);
	}
}
