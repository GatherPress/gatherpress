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
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'process_venue_detail_field' ),
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

		$block_content = '<p class="wp-block-example gatherpress--has-login-url">' .
			'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.</p>';
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

		$block_content = '<p class="wp-block-example">' .
			'Please <a href="#gatherpress-login-url">Login to RSVP</a> to this event.</p>';
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

		$this->assertStringContainsString(
			'wp-login.php',
			$result,
			'Login URL should always be replaced regardless of class.'
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

		$this->assertStringContainsString(
			'wp-login.php',
			$result,
			'Login URL should always be replaced regardless of className attribute.'
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

		$block_content = '<p class="wp-block-example gatherpress--has-registration-url">' .
			'Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.</p>';
		$block         = array(
			'attrs'        => array(
				'className' => 'wp-block-example gatherpress--has-registration-url',
			),
			'innerHTML'    => 'Don\'t have an account? ' .
				'<a href="#gatherpress-registration-url">Register here</a> to create one.',
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
	 * @covers ::process_guests_field
	 *
	 * @return void
	 */
	public function test_process_guests_field_hides_when_limit_zero(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '0' );

		$block_content = '<div class="gatherpress-rsvp-field-guests">Guest Count Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guests_field( $block_content, $block );

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
	 * @covers ::process_guests_field
	 *
	 * @return void
	 */
	public function test_process_guests_field_visible_when_limit_nonzero(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '5' );

		$block_content = '<div class="gatherpress-rsvp-field-guests">Guest Count Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guests_field( $block_content, $block );

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
	 * @covers ::process_guests_field
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_methods_skip_non_event_posts(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Keep default post type (not gatherpress_event).
		$guest_block_content = '<div class="gatherpress-rsvp-field-guests">Guest Count Field</div>';
		$anon_block_content  = '<div class="gatherpress-rsvp-field-anonymous">Anonymous Field</div>';
		$block               = array( 'attrs' => array( 'postId' => $post_id ) );

		$guest_result = $general_block->process_guests_field( $guest_block_content, $block );
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

	/**
	 * Test that all guest fields are hidden when max guest limit is 0 (multiple fields).
	 *
	 * This test ensures that if there are multiple guest fields in the same block content,
	 * ALL of them get hidden, not just the first one. This prevents regression of break
	 * statements that would stop processing after the first match.
	 *
	 * @since 1.0.0
	 * @covers ::process_guests_field
	 *
	 * @return void
	 */
	public function test_process_guests_field_hides_all_multiple_fields(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '0' );

		// Block content with multiple guest fields.
		$block_content = '<div class="form-wrapper">' .
			'<div class="gatherpress-rsvp-field-guests">First Guest Field</div>' .
			'<div class="some-other-field">Other Field</div>' .
			'<div class="gatherpress-rsvp-field-guests">Second Guest Field</div>' .
			'<div class="gatherpress-rsvp-field-guests another-class">Third Guest Field</div>' .
			'</div>';

		$block = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guests_field( $block_content, $block );

		// Verify ALL guest fields have the hidden class.
		$this->assertEquals(
			3,
			substr_count( $result, 'gatherpress--is-hidden' ),
			'All guest fields should have the hidden class when max limit is 0.'
		);

		// Verify the structure is preserved.
		$this->assertStringContainsString( 'First Guest Field', $result );
		$this->assertStringContainsString( 'Second Guest Field', $result );
		$this->assertStringContainsString( 'Third Guest Field', $result );
		$this->assertStringContainsString( 'Other Field', $result );

		// Verify non-guest fields are not affected.
		$this->assertStringNotContainsString( 'some-other-field gatherpress--is-hidden', $result );
	}

	/**
	 * Test that all anonymous fields are hidden when anonymous RSVP is disabled (multiple fields).
	 *
	 * This test ensures that if there are multiple anonymous fields in the same block content,
	 * ALL of them get hidden, not just the first one. This prevents regression of break
	 * statements that would stop processing after the first match.
	 *
	 * @since 1.0.0
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_anonymous_field_hides_all_multiple_fields(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '' );

		// Block content with multiple anonymous fields.
		$block_content = '<div class="form-wrapper">' .
			'<div class="gatherpress-rsvp-field-anonymous">First Anonymous Field</div>' .
			'<div class="regular-field">Regular Field</div>' .
			'<div class="gatherpress-rsvp-field-anonymous">Second Anonymous Field</div>' .
			'<div class="gatherpress-rsvp-field-anonymous extra-class">Third Anonymous Field</div>' .
			'</div>';

		$block = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_anonymous_field( $block_content, $block );

		// Verify ALL anonymous fields have the hidden class.
		$this->assertEquals(
			3,
			substr_count( $result, 'gatherpress--is-hidden' ),
			'All anonymous fields should have the hidden class when anonymous RSVP is disabled.'
		);

		// Verify the structure is preserved.
		$this->assertStringContainsString( 'First Anonymous Field', $result );
		$this->assertStringContainsString( 'Second Anonymous Field', $result );
		$this->assertStringContainsString( 'Third Anonymous Field', $result );
		$this->assertStringContainsString( 'Regular Field', $result );

		// Verify non-anonymous fields are not affected.
		$this->assertStringNotContainsString( 'regular-field gatherpress--is-hidden', $result );
	}

	/**
	 * Test converting anchor tag to submit button.
	 *
	 * @since 1.0.0
	 * @covers ::convert_submit_button
	 *
	 * @return void
	 */
	public function test_convert_submit_button_converts_anchor_to_button(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div class="wp-block-button gatherpress-submit-button">' .
			'<a href="#" role="button" class="wp-block-button__link">Submit</a></div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress-submit-button',
			),
		);

		$result = $general_block->convert_submit_button( $block_content, $block );

		$this->assertStringContainsString( '<button', $result, 'Anchor should be converted to button.' );
		$this->assertStringContainsString( 'type="submit"', $result, 'Button should have type="submit".' );
		$this->assertStringContainsString( '</button>', $result, 'Should have closing button tag.' );
		$this->assertStringNotContainsString( '<a', $result, 'Should not contain anchor tag.' );
		$this->assertStringNotContainsString( 'href=', $result, 'Should not have href attribute.' );
		$this->assertStringNotContainsString( 'role=', $result, 'Should not have role attribute.' );
	}

	/**
	 * Test adding type="submit" to existing button element.
	 *
	 * @since 1.0.0
	 * @covers ::convert_submit_button
	 *
	 * @return void
	 */
	public function test_convert_submit_button_adds_type_to_button(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div class="wp-block-button gatherpress-submit-button">' .
			'<button class="wp-block-button__link">Submit</button></div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress-submit-button',
			),
		);

		$result = $general_block->convert_submit_button( $block_content, $block );

		$this->assertStringContainsString( 'type="submit"', $result, 'Button should have type="submit" attribute.' );
		$this->assertStringContainsString( '<button', $result, 'Should contain button tag.' );
	}

	/**
	 * Test that button without gatherpress-submit-button class is not modified.
	 *
	 * @since 1.0.0
	 * @covers ::convert_submit_button
	 *
	 * @return void
	 */
	public function test_convert_submit_button_skips_without_class(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div class="wp-block-button"><a href="#" class="wp-block-button__link">Click Me</a></div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-button',
			),
		);

		$result = $general_block->convert_submit_button( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block without gatherpress-submit-button class should remain unchanged.'
		);
	}

	/**
	 * Test convert_submit_button when block has class but no button/anchor elements.
	 *
	 * Tests the fallback return path when the block has the gatherpress-submit-button
	 * class but doesn't contain any anchor or button tags to process.
	 *
	 * @since 1.0.0
	 * @covers ::convert_submit_button
	 *
	 * @return void
	 */
	public function test_convert_submit_button_no_elements_to_process(): void {
		$general_block = General_Block::get_instance();

		// Block has the class but only contains a div with text (no <a> or <button>).
		$block_content = '<div class="wp-block-group gatherpress-submit-button"><p>This is just text</p></div>';
		$block         = array(
			'attrs' => array(
				'className' => 'wp-block-group gatherpress-submit-button',
			),
		);

		$result = $general_block->convert_submit_button( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block with class but no anchor/button elements should return unchanged.'
		);
	}

	/**
	 * Test process_guests_field with non-publish status returns content unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::process_guests_field
	 *
	 * @return void
	 */
	public function test_process_guests_field_skips_non_published_posts(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post( array( 'post_status' => 'draft' ) )->get()->ID;

		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '0' );

		$block_content = '<div class="gatherpress-rsvp-field-guests">Guest Count Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_guests_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Guest field processing should skip draft posts.'
		);
	}

	/**
	 * Test process_anonymous_field with non-publish status returns content unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_process_anonymous_field_skips_non_published_posts(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post( array( 'post_status' => 'draft' ) )->get()->ID;

		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '' );

		$block_content = '<div class="gatherpress-rsvp-field-anonymous">Anonymous Field</div>';
		$block         = array( 'attrs' => array( 'postId' => $post_id ) );

		$result = $general_block->process_anonymous_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Anonymous field processing should skip draft posts.'
		);
	}

	/**
	 * Test venue detail field returns unchanged when block has no venue conditional class.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_unchanged_without_venue_class(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div class="some-other-class">Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'some-other-class',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block without venue conditional class should remain unchanged.'
		);
	}

	/**
	 * Test venue detail field returns unchanged when block has no className attribute.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_unchanged_without_classname(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div>Test content</div>';
		$block         = array(
			'attrs' => array(),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block without className attribute should remain unchanged.'
		);
	}

	/**
	 * Test venue detail field returns unchanged when field name is not in mapping.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_unchanged_for_unknown_field(): void {
		$general_block = General_Block::get_instance();

		$block_content = '<div class="gatherpress--has-venue-unknown">Test content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-unknown',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block with unknown venue field should remain unchanged.'
		);
	}

	/**
	 * Test venue detail field returns unchanged when post is not a venue type.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_unchanged_for_non_venue_post(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block should remain unchanged when post is not a venue type.'
		);
	}

	/**
	 * Test venue detail field returns empty when venue info JSON is invalid.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_empty_for_invalid_json(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add invalid JSON to venue meta.
		add_post_meta( $post_id, 'gatherpress_venue_information', 'not valid json' );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEmpty(
			$result,
			'Block should be empty when venue info JSON is invalid.'
		);
	}

	/**
	 * Test venue detail field returns empty when venue field is empty.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_empty_for_empty_field(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add venue info with empty phone number.
		$venue_info = array(
			'phoneNumber' => '',
			'fullAddress' => '123 Main St',
			'website'     => 'https://example.com',
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEmpty(
			$result,
			'Block should be empty when venue phone field is empty.'
		);
	}

	/**
	 * Test venue detail field returns content when venue field has value.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_content_when_field_has_value(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add venue info with phone number.
		$venue_info = array(
			'phoneNumber' => '555-123-4567',
			'fullAddress' => '123 Main St',
			'website'     => 'https://example.com',
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block should remain unchanged when venue phone field has value.'
		);
	}

	/**
	 * Test venue detail field works with address field.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_works_with_address_field(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add venue info with address.
		$venue_info = array(
			'phoneNumber' => '',
			'fullAddress' => '123 Main St, City, State 12345',
			'website'     => '',
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-address">Address content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-address',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block should remain unchanged when venue address field has value.'
		);
	}

	/**
	 * Test venue detail field works with website field.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_works_with_website_field(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add venue info with website.
		$venue_info = array(
			'phoneNumber' => '',
			'fullAddress' => '',
			'website'     => 'https://example.com',
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-website">Website content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-website',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block should remain unchanged when venue website field has value.'
		);
	}

	/**
	 * Test venue detail field returns empty when field is missing from JSON.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_returns_empty_for_missing_field(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Set up the post context.
		$this->go_to( get_permalink( $post_id ) );

		// Add venue info without the phone number field.
		$venue_info = array(
			'fullAddress' => '123 Main St',
			'website'     => 'https://example.com',
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEmpty(
			$result,
			'Block should be empty when venue field is missing from JSON.'
		);
	}

	/**
	 * Test venue detail field uses postId attribute when provided.
	 *
	 * @since 1.0.0
	 * @covers ::process_venue_detail_field
	 *
	 * @return void
	 */
	public function test_process_venue_detail_field_uses_post_id_attribute(): void {
		$general_block = General_Block::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => 'gatherpress_venue' ) );

		// Add venue info with phone number.
		$venue_info = array(
			'phoneNumber' => '555-123-4567',
			'fullAddress' => '123 Main St',
			'website'     => 'https://example.com',
		);
		add_post_meta( $venue_post_id, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$block_content = '<div class="gatherpress--has-venue-phone">Phone content</div>';
		$block         = array(
			'attrs' => array(
				'className' => 'gatherpress--has-venue-phone',
				'postId'    => $venue_post_id,
			),
		);

		$result = $general_block->process_venue_detail_field( $block_content, $block );

		$this->assertEquals(
			$block_content,
			$result,
			'Block should use postId attribute when provided.'
		);
	}

	/**
	 * Test mixed field types processing with multiple fields.
	 *
	 * This test ensures that when both guest and anonymous fields are present
	 * and both conditions are met (guest limit 0 and anonymous disabled),
	 * all appropriate fields are hidden independently.
	 *
	 * @since 1.0.0
	 * @covers ::process_guests_field
	 * @covers ::process_anonymous_field
	 *
	 * @return void
	 */
	public function test_multiple_field_types_processed_independently(): void {
		$general_block = General_Block::get_instance();
		$post_id       = $this->mock->post()->get()->ID;

		// Set the event post type with both conditions.
		set_post_type( $post_id, 'gatherpress_event' );
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', '0' );
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', '' );

		// Block content with mixed field types.
		$block_content = '<div class="form-wrapper">' .
			'<div class="gatherpress-rsvp-field-guests">Guest Field 1</div>' .
			'<div class="gatherpress-rsvp-field-anonymous">Anonymous Field 1</div>' .
			'<div class="gatherpress-rsvp-field-guests">Guest Field 2</div>' .
			'<div class="normal-field">Normal Field</div>' .
			'<div class="gatherpress-rsvp-field-anonymous">Anonymous Field 2</div>' .
			'</div>';

		$block = array( 'attrs' => array( 'postId' => $post_id ) );

		// Process guest fields first.
		$result_after_guests = $general_block->process_guests_field( $block_content, $block );

		// Process anonymous fields on the result.
		$final_result = $general_block->process_anonymous_field( $result_after_guests, $block );

		// Should have 4 hidden classes total (2 guest + 2 anonymous).
		$this->assertEquals(
			4,
			substr_count( $final_result, 'gatherpress--is-hidden' ),
			'Both guest and anonymous fields should be hidden when both conditions are met.'
		);

		// Verify structure preservation.
		$this->assertStringContainsString( 'Guest Field 1', $final_result );
		$this->assertStringContainsString( 'Guest Field 2', $final_result );
		$this->assertStringContainsString( 'Anonymous Field 1', $final_result );
		$this->assertStringContainsString( 'Anonymous Field 2', $final_result );
		$this->assertStringContainsString( 'Normal Field', $final_result );

		// Verify normal field is not affected.
		$this->assertStringNotContainsString( 'normal-field gatherpress--is-hidden', $final_result );
	}
}
