<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\General_Block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\General_Block;
use GatherPress\Core\Utility;
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
			array(
				'type'     => 'filter',
				'name'     => 'render_block_core/button',
				'priority' => 10,
				'callback' => array( $instance, 'convert_submit_button' ),
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
	 * Test login URL is dynamically set when user is not logged in and block has login URL class.
	 *
	 * This test verifies that when a user is not logged in, the block content is preserved
	 * and the placeholder login URL is correctly replaced with the actual login URL.
	 *
	 * @since  1.0.0
	 * @covers ::process_login_block
	 *
	 * @return void
	 */
	public function test_login_url_is_set_when_user_not_logged_in(): void {
		$general_block = General_Block::get_instance();
		$post          = $this->mock->post()->get();

		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		$block_content = '<p class="wp-block-example gatherpress--has-login-url">Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.</p>';
		$block         = array(
			'attrs'        => array(
				'className' => 'wp-block-example gatherpress--has-login-url',
			),
			'innerHTML'    => 'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
			'innerContent' => array(
				'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
			),
		);

		$result = $general_block->process_login_block( $block_content, $block );

		$this->assertStringContainsString(
			Utility::get_login_url( $post->ID ),
			$result,
			'Block content should contain the correct login URL.'
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

		$block_content = '<p class="wp-block-example">Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.</p>';
		$block         = array(
			'attrs'        => array(
				'className' => 'wp-block-example',
			),
			'innerHTML'    => 'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
			'innerContent' => array(
				'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
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

		$block_content = '<p>Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.</p>';
		$block         = array(
			'attrs'        => array(),
			'innerHTML'    => 'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
			'innerContent' => array(
				'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.',
			),
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
	 * Test Registration URL is dynamically set when user is not logged in and block has login URL class.
	 *
	 * This test verifies that when a user is not logged in, the block content is preserved
	 * and the placeholder registration URL is correctly replaced with the actual registration URL.
	 *
	 * @since  1.0.0
	 * @covers ::process_registration_block
	 *
	 * @return void
	 */
	public function test_registration_url_is_set_when_user_not_logged_in(): void {
		$general_block = General_Block::get_instance();
		$post          = $this->mock->post()->get();

		// Ensure no user is logged in.
		wp_set_current_user( 0 );

		// Enable user registration.
		update_option( 'users_can_register', 1 );

		$block_content = '<p class="wp-block-example gatherpress--has-registration-url">Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.</p>';
		$block         = array(
			'attrs'        => array(
				'className' => 'wp-block-example gatherpress--has-registration-url',
			),
			'innerHTML'    => 'Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.',
			'innerContent' => array(
				'Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.',
			),
		);

		$result = $general_block->process_registration_block( $block_content, $block );

		$this->assertStringContainsString(
			Utility::get_registration_url( $post->ID ),
			html_entity_decode( $result ),
			'Block content should contain the correct registration URL.'
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

	/**
	 * Test guest count field is hidden when max guest limit is 0.
	 *
	 * @since 1.0.0
	 * @covers ::process_guest_count_field
	 *
	 * @return void
	 */
	public function test_process_guest_count_field_hides_when_limit_zero(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '0' );

		$block_content = '<div class="gatherpress-rsvp-field-guest-count">Guest Count Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guest_count_field( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-hidden',
			$result,
			'Guest count field should have hidden class when max limit is 0.'
		);
	}

	/**
	 * Test guest count field is visible when max guest limit is greater than 0.
	 *
	 * @since 1.0.0
	 * @covers ::process_guest_count_field
	 *
	 * @return void
	 */
	public function test_process_guest_count_field_visible_when_limit_nonzero(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '5' );

		$block_content = '<div class="gatherpress-rsvp-field-guest-count">Guest Count Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guest_count_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Guest count field should remain unchanged when max limit is greater than 0.'
		);
	}

	/**
	 * Test anonymous field is hidden when anonymous RSVP is disabled.
	 *
	 * @since 1.0.0
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_anonymous_field_hides_when_disabled(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '' );

		$block_content = '<div class="gatherpress-rsvp-field-anonymous">Anonymous Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_anonymous_field( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-hidden',
			$result,
			'Anonymous field should have hidden class when anonymous RSVP is disabled.'
		);
	}

	/**
	 * Test anonymous field is visible when anonymous RSVP is enabled.
	 *
	 * @since 1.0.0
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_anonymous_field_visible_when_enabled(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '1' );

		$block_content = '<div class="gatherpress-rsvp-field-anonymous">Anonymous Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_anonymous_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Anonymous field should remain unchanged when anonymous RSVP is enabled.'
		);
	}

	/**
	 * Test process methods return unchanged content for non-event posts.
	 *
	 * @since 1.0.0
	 * @covers ::process_guest_count_field
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_methods_skip_non_event_posts(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Keep default post type (not gatherpress_event).
		$guest_block_content = '<div class="gatherpress-rsvp-field-guest-count">Guest Count Field</div>';
		$anon_block_content  = '<div class="gatherpress-rsvp-field-anonymous">Anonymous Field</div>';
		$block               = array( 'attrs' => array( 'postId' => $post_id ) );

		$guest_result = $general_block->process_guest_count_field( $guest_block_content, $block );
		$anon_result  = $general_block->process_anonymous_field( $anon_block_content, $block );

		$this->assertEquals(
			$guest_block_content,
			$guest_result,
			'Guest count field processing should skip non-event posts.'
		);

		$this->assertEquals(
			$anon_block_content,
			$anon_result,
			'Anonymous field processing should skip non-event posts.'
		);
	}
}
