<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Form.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Form;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Form.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Form
 */
class Test_Rsvp_Form extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP Form block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp_Form::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp_Form::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'transform_block_content' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'apply_visibility_attribute' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block_gatherpress/form-field',
				'priority' => 10,
				'callback' => array( $instance, 'conditionally_render_form_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'save_post',
				'priority' => 10,
				'callback' => array( $instance, 'save_form_schema' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests the generate_form_id method.
	 *
	 * Verifies that the method generates unique form IDs with the correct prefix.
	 *
	 * @since 1.0.0
	 * @covers ::generate_form_id
	 *
	 * @return void
	 */
	public function test_generate_form_id(): void {
		$instance = Rsvp_Form::get_instance();

		$form_id_1 = Utility::invoke_hidden_method( $instance, 'generate_form_id' );
		$form_id_2 = Utility::invoke_hidden_method( $instance, 'generate_form_id' );

		$this->assertIsString( $form_id_1 );
		$this->assertIsString( $form_id_2 );
		$this->assertStringStartsWith( 'gatherpress_rsvp_', $form_id_1 );
		$this->assertStringStartsWith( 'gatherpress_rsvp_', $form_id_2 );
		$this->assertNotEquals( $form_id_1, $form_id_2 );
	}

	/**
	 * Tests the transform_block_content method.
	 *
	 * Verifies that the block content is correctly transformed from a div
	 * to a form with proper attributes and hidden inputs including form ID.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 * @covers ::generate_form_id
	 *
	 * @return void
	 */
	public function test_transform_block_content(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringStartsWith( '<form', $transformed_content );
		$this->assertStringEndsWith( '</form>', $transformed_content );
		$this->assertStringContainsString( 'action="' . site_url( 'wp-comments-post.php' ) . '"', $transformed_content );
		$this->assertStringContainsString( 'method="post"', $transformed_content );
		$this->assertStringContainsString( 'name="comment_post_ID" value="' . $post_id . '"', $transformed_content );
		$this->assertStringContainsString( 'name="gatherpress_rsvp" value="1"', $transformed_content );
		$this->assertStringContainsString( 'name="gatherpress_rsvp_form_id"', $transformed_content );
		$this->assertStringContainsString( 'value="gatherpress_rsvp_', $transformed_content );
	}

	/**
	 * Tests that transform_block_content preserves original div attributes.
	 *
	 * Verifies that when transforming the div to a form, existing attributes
	 * like classes and IDs are preserved.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_preserves_attributes(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form custom-class">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString( '<form', $transformed_content );
		$this->assertStringContainsString( 'method="post"', $transformed_content );
		$this->assertStringContainsString( 'action="', $transformed_content );
		$this->assertStringContainsString( 'class="wp-block-gatherpress-rsvp-form custom-class"', $transformed_content );
		$this->assertStringContainsString( 'id="gatherpress_rsvp_', $transformed_content );
	}

	/**
	 * Tests that transform_block_content adds interactivity attributes.
	 *
	 * Verifies that the WordPress Interactivity API attributes are properly
	 * added to enable Ajax form handling.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_adds_interactivity_attributes(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString( 'data-wp-interactive="gatherpress"', $transformed_content );
		$this->assertStringContainsString( 'data-wp-init="callbacks.initRsvpForm"', $transformed_content );
		$this->assertStringContainsString( 'data-wp-on--submit="actions.handleRsvpFormSubmit"', $transformed_content );
		$this->assertStringContainsString( 'data-wp-context=', $transformed_content );
		$this->assertStringContainsString( (string) $post_id, $transformed_content );
	}

	/**
	 * Tests that transform_block_content hides success message blocks by default.
	 *
	 * Verifies that elements with gatherpress--rsvp-form-message class
	 * are hidden by adding display:none style when no success parameter is present.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 * @covers ::handle_form_visibility
	 *
	 * @return void
	 */
	public function test_transform_block_content_hides_success_messages(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">
			<div class="wp-block-group" data-gatherpress-rsvp-form-visibility="{&quot;onSuccess&quot;:&quot;show&quot;}">Success message</div>
			<div class="wp-block-gatherpress-form-field" data-gatherpress-rsvp-form-visibility="{&quot;onSuccess&quot;:&quot;hide&quot;}">Form field</div>
		</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString( '&quot;onSuccess&quot;:&quot;show&quot;', $transformed_content );
		$this->assertStringContainsString( 'display: none;', $transformed_content );
		$this->assertStringContainsString( 'aria-hidden="true"', $transformed_content );
	}

	/**
	 * Tests that transform_block_content includes schema form ID.
	 *
	 * Verifies that the form schema ID is included as a hidden input
	 * for schema validation during form submission.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 * @covers ::get_form_schema_id
	 *
	 * @return void
	 */
	public function test_transform_block_content_includes_schema_form_id(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString( 'name="gatherpress_form_schema_id"', $transformed_content );
		$this->assertStringContainsString( 'value="form_0"', $transformed_content );
	}

	/**
	 * Tests the save_form_schema method.
	 *
	 * Verifies that form schemas are correctly extracted and saved as post meta
	 * when a post is saved.
	 *
	 * @since 1.0.0
	 * @covers ::save_form_schema
	 * @covers ::extract_form_schemas_from_blocks
	 * @covers ::extract_form_fields_from_inner_blocks
	 *
	 * @return void
	 */
	public function test_save_form_schema(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:gatherpress/form-field {"fieldName":"custom_field","fieldType":"text","required":true} -->
						<div class="wp-block-gatherpress-form-field"></div>
						<!-- /wp:gatherpress/form-field -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		// Set up user permissions.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertIsArray( $schemas );
		$this->assertArrayHasKey( 'form_0', $schemas );
		$this->assertArrayHasKey( 'fields', $schemas['form_0'] );
		$this->assertArrayHasKey( 'hash', $schemas['form_0'] );
		$this->assertArrayHasKey( 'custom_field', $schemas['form_0']['fields'] );

		$field_config = $schemas['form_0']['fields']['custom_field'];
		$this->assertEquals( 'custom_field', $field_config['name'] );
		$this->assertEquals( 'text', $field_config['type'] );
		$this->assertTrue( $field_config['required'] );
	}

	/**
	 * Tests save_form_schema with multiple forms.
	 *
	 * Verifies that multiple RSVP forms on the same post generate
	 * separate schemas with unique form IDs.
	 *
	 * @since 1.0.0
	 * @covers ::save_form_schema
	 * @covers ::extract_form_schemas_from_blocks
	 *
	 * @return void
	 */
	public function test_save_form_schema_multiple_forms(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
<div class="wp-block-gatherpress-rsvp-form">
<!-- wp:gatherpress/form-field {"fieldName":"field1","fieldType":"text"} -->
<div class="wp-block-gatherpress-form-field"></div>
<!-- /wp:gatherpress/form-field -->
</div>
<!-- /wp:gatherpress/rsvp-form -->

<!-- wp:gatherpress/rsvp-form -->
<div class="wp-block-gatherpress-rsvp-form">
<!-- wp:gatherpress/form-field {"fieldName":"field2","fieldType":"email"} -->
<div class="wp-block-gatherpress-form-field"></div>
<!-- /wp:gatherpress/form-field -->
</div>
<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		// Set up user permissions.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertIsArray( $schemas );
		$this->assertNotEmpty( $schemas );

		// Check that we have the first form at minimum.
		$this->assertArrayHasKey( 'form_0', $schemas );
		$this->assertArrayHasKey( 'field1', $schemas['form_0']['fields'] );

		// If there's a second form, check it too.
		if ( isset( $schemas['form_1'] ) ) {
			$this->assertArrayHasKey( 'field2', $schemas['form_1']['fields'] );
		}
	}

	/**
	 * Tests save_form_schema removes schemas when no forms present.
	 *
	 * Verifies that form schema meta is deleted when a post no longer
	 * contains any RSVP forms.
	 *
	 * @since 1.0.0
	 * @covers ::save_form_schema
	 *
	 * @return void
	 */
	public function test_save_form_schema_removes_when_no_forms(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:paragraph --><p>No RSVP forms here</p><!-- /wp:paragraph -->',
			)
		);

		// Set up user permissions.
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		// First add some schema meta.
		update_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', array( 'form_0' => array() ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertEmpty( $schemas );
	}

	/**
	 * Tests save_form_schema skips when user cannot edit post.
	 *
	 * Verifies that form schema processing is skipped when user
	 * lacks permission to edit the post.
	 *
	 * @since 1.0.0
	 * @covers ::save_form_schema
	 *
	 * @return void
	 */
	public function test_save_form_schema_skips_without_permission(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
<div class="wp-block-gatherpress-rsvp-form">
<!-- wp:gatherpress/form-field {"fieldName":"test_field","fieldType":"text"} -->
<div class="wp-block-gatherpress-form-field"></div>
<!-- /wp:gatherpress/form-field -->
</div>
<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		// Set current user to subscriber (no edit permissions).
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$instance->save_form_schema( $post_id );

		// Should not process schema without permission.
		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );
		$this->assertEmpty( $schemas );
	}

	/**
	 * Tests the sanitize_custom_field_value method with text fields.
	 *
	 * Verifies that text field sanitization works correctly with
	 * sanitization and required field checking.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_text(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid text field.
		$config = array(
			'type'     => 'text',
			'required' => false,
		);
		$result = $instance->sanitize_custom_field_value( 'Valid text input', $config );
		$this->assertEquals( 'Valid text input', $result );

		// Test required field with empty value.
		$config = array(
			'type'     => 'text',
			'required' => true,
		);
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertFalse( $result );

		// Test text sanitization.
		$result = $instance->sanitize_custom_field_value( '<script>alert("xss")</script>Safe text', $config );
		$this->assertEquals( 'Safe text', $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with email fields.
	 *
	 * Verifies that email field sanitization correctly validates
	 * email addresses and handles required fields.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_email(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid email.
		$config = array(
			'type'     => 'email',
			'required' => false,
		);
		$result = $instance->sanitize_custom_field_value( 'test@example.com', $config );
		$this->assertEquals( 'test@example.com', $result );

		// Test invalid email.
		$result = $instance->sanitize_custom_field_value( 'not-an-email', $config );
		$this->assertFalse( $result );

		// Test required field with empty value.
		$config = array(
			'type'     => 'email',
			'required' => true,
		);
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertFalse( $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with number fields.
	 *
	 * Verifies that number field sanitization correctly validates
	 * numeric values and handles required fields.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_number(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid integer.
		$config = array(
			'type'     => 'number',
			'required' => false,
		);
		$result = $instance->sanitize_custom_field_value( '42', $config );
		$this->assertEquals( 42.0, $result );

		// Test valid float.
		$result = $instance->sanitize_custom_field_value( '3.14', $config );
		$this->assertEquals( 3.14, $result );

		// Test invalid number.
		$result = $instance->sanitize_custom_field_value( 'not-a-number', $config );
		$this->assertFalse( $result );

		// Test required field with empty value.
		$config = array(
			'type'     => 'number',
			'required' => true,
		);
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertFalse( $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with select fields.
	 *
	 * Verifies that select field sanitization correctly validates
	 * against allowed options.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_select(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid option.
		$config = array(
			'type'     => 'select',
			'required' => false,
			'options'  => array( 'option1', 'option2', 'option3' ),
		);
		$result = $instance->sanitize_custom_field_value( 'option2', $config );
		$this->assertEquals( 'option2', $result );

		// Test invalid option.
		$result = $instance->sanitize_custom_field_value( 'invalid-option', $config );
		$this->assertFalse( $result );

		// Test required field with empty value.
		$config = array(
			'type'     => 'select',
			'required' => true,
			'options'  => array( 'option1', 'option2', 'option3' ),
		);
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertFalse( $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with checkbox fields.
	 *
	 * Verifies that checkbox field sanitization correctly converts
	 * values to 1 or 0.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_checkbox(): void {
		$instance = Rsvp_Form::get_instance();

		// Test checked checkbox.
		$config = array(
			'type'     => 'checkbox',
			'required' => false,
		);
		$result = $instance->sanitize_custom_field_value( 'on', $config );
		$this->assertEquals( 1, $result );

		// Test unchecked checkbox.
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertEquals( 0, $result );

		// Test various truthy values.
		$result = $instance->sanitize_custom_field_value( '1', $config );
		$this->assertEquals( 1, $result );

		$result = $instance->sanitize_custom_field_value( 'true', $config );
		$this->assertEquals( 1, $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with textarea fields.
	 *
	 * Verifies that textarea field sanitization correctly handles
	 * max length constraints.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_textarea(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid textarea within length limit.
		$config = array(
			'type'       => 'textarea',
			'required'   => false,
			'max_length' => 100,
		);
		$value  = 'This is a valid textarea content.';
		$result = $instance->sanitize_custom_field_value( $value, $config );
		$this->assertEquals( $value, $result );

		// Test textarea exceeding length limit.
		$long_value = str_repeat( 'a', 101 );
		$result     = $instance->sanitize_custom_field_value( $long_value, $config );
		$this->assertFalse( $result );

		// Test default max length (1000).
		$config       = array(
			'type'     => 'textarea',
			'required' => false,
		);
		$medium_value = str_repeat( 'a', 500 );
		$result       = $instance->sanitize_custom_field_value( $medium_value, $config );
		$this->assertEquals( $medium_value, $result );
	}

	/**
	 * Tests the sanitize_custom_field_value method with URL fields.
	 *
	 * Verifies that URL field sanitization correctly validates
	 * URL format and handles required fields.
	 *
	 * @since 1.0.0
	 * @covers ::sanitize_custom_field_value
	 *
	 * @return void
	 */
	public function test_sanitize_custom_field_value_url(): void {
		$instance = Rsvp_Form::get_instance();

		// Test valid URL.
		$config = array(
			'type'     => 'url',
			'required' => false,
		);
		$result = $instance->sanitize_custom_field_value( 'https://example.com', $config );
		$this->assertEquals( 'https://example.com', $result );

		// Test invalid URL.
		$result = $instance->sanitize_custom_field_value( 'invalid://url with spaces', $config );
		$this->assertFalse( $result );

		// Test another invalid URL format.
		$result = $instance->sanitize_custom_field_value( 'just some text', $config );
		$this->assertFalse( $result );

		// Test required field with empty value.
		$config = array(
			'type'     => 'url',
			'required' => true,
		);
		$result = $instance->sanitize_custom_field_value( '', $config );
		$this->assertFalse( $result );
	}

	/**
	 * Tests the handle_form_visibility method with block attributes.
	 *
	 * Verifies that the method correctly shows/hides elements based on
	 * the success state parameter and gatherpressRsvpFormVisibility attributes.
	 *
	 * @since 1.0.0
	 * @covers ::handle_form_visibility
	 *
	 * @return void
	 */
	public function test_handle_form_visibility(): void {
		$instance = Rsvp_Form::get_instance();

		$html = '<form>
			<div class="wp-block-group" data-gatherpress-rsvp-form-visibility="{&quot;onSuccess&quot;:&quot;show&quot;}">Success message</div>
			<div class="wp-block-gatherpress-form-field" data-gatherpress-rsvp-form-visibility="{&quot;onSuccess&quot;:&quot;hide&quot;}">Name field</div>
			<div class="wp-block-buttons" data-gatherpress-rsvp-form-visibility="{&quot;onSuccess&quot;:&quot;hide&quot;}">Submit Button</div>
		</form>';

		// Test with success = false (default state).
		$result_false = Utility::invoke_hidden_method( $instance, 'handle_form_visibility', array( $html, false ) );
		$this->assertStringContainsString( 'wp-block-group', $result_false );
		$this->assertStringContainsString( 'display: none;', $result_false );
		$this->assertStringContainsString( 'aria-hidden="true"', $result_false );

		// Test with success = true (successful submission).
		$result_true = Utility::invoke_hidden_method( $instance, 'handle_form_visibility', array( $html, true ) );
		$this->assertStringContainsString( 'wp-block-group', $result_true );

		// Check that blocks with onSuccess: 'show' do NOT have display: none.
		preg_match( '/<div[^>]*class="wp-block-group"[^>]*>/', $result_true, $group_matches );
		if ( ! empty( $group_matches[0] ) ) {
			$this->assertStringNotContainsString( 'display: none;', $group_matches[0], 'Success message should be visible' );
		}

		// Check that blocks with onSuccess: 'hide' DO have display: none.
		$this->assertStringContainsString( 'style="display: none;" class="wp-block-gatherpress-form-field"', $result_true );
		$this->assertStringContainsString( 'aria-hidden="false"', $result_true );
	}

	/**
	 * Tests the handle_form_visibility method with no attributes.
	 *
	 * Verifies that forms without gatherpressRsvpFormVisibility attributes
	 * remain unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::handle_form_visibility
	 *
	 * @return void
	 */
	public function test_handle_form_visibility_no_attributes(): void {
		$instance = Rsvp_Form::get_instance();

		$html = '<form>
			<div class="wp-block-group">Success message</div>
			<div class="wp-block-gatherpress-form-field">Name field</div>
			<div class="wp-block-button">Submit Button</div>
		</form>';

		// Test that HTML is returned unchanged when no visibility data attributes.
		$result = Utility::invoke_hidden_method( $instance, 'handle_form_visibility', array( $html, true ) );
		$this->assertEquals( $html, $result );
	}

	/**
	 * Tests the apply_visibility_attribute method with no gatherpressRsvpFormVisibility attribute.
	 *
	 * Verifies that blocks without gatherpressRsvpFormVisibility attribute are unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::apply_visibility_attribute
	 *
	 * @return void
	 */
	public function test_apply_visibility_attribute_no_attribute(): void {
		$instance = Rsvp_Form::get_instance();

		$block_content = '<div class="wp-block-paragraph">Normal content</div>';
		$block         = array( 'attrs' => array() );

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertEquals( $block_content, $result );
		$this->assertStringNotContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
	}

	/**
	 * Tests the apply_visibility_attribute method with default gatherpressRsvpFormVisibility.
	 *
	 * Verifies that blocks with gatherpressRsvpFormVisibility set to 'default' are unchanged.
	 *
	 * @since 1.0.0
	 * @covers ::apply_visibility_attribute
	 *
	 * @return void
	 */
	public function test_apply_visibility_attribute_default_value(): void {
		$instance = Rsvp_Form::get_instance();

		$block_content = '<div class="wp-block-paragraph">Normal content</div>';
		$block         = array(
			'attrs' => array( 'gatherpressRsvpFormVisibility' => 'default' ),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertEquals( $block_content, $result );
		$this->assertStringNotContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
	}

	/**
	 * Tests apply_visibility_attribute with success state.
	 *
	 * Verifies that blocks with gatherpressRsvpFormVisibility attribute respond correctly to success state.
	 *
	 * @since 1.0.0
	 * @covers ::apply_visibility_attribute
	 *
	 * @return void
	 */
	public function test_apply_visibility_attribute_with_success(): void {
		$instance = Rsvp_Form::get_instance();

		// Mock GET parameter for success state using pre_ filter.
		add_filter(
			'gatherpress_pre_get_http_input',
			function ( $pre_value, $type, $var_name ) {
				if ( INPUT_GET === $type && 'gatherpress_rsvp_success' === $var_name ) {
					return 'true';
				}
				return null;
			},
			10,
			3
		);

		// Test block that shows on success.
		$block_content = '<div class="wp-block-group">Success message</div>';
		$block         = array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
		$this->assertStringContainsString( '&quot;onSuccess&quot;:&quot;show&quot;', $result );

		// Test block that hides on success.
		$block_content = '<div class="wp-block-gatherpress-form-field">Name field</div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'hide' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
		$this->assertStringContainsString( '&quot;onSuccess&quot;:&quot;hide&quot;', $result );

		// Clean up filters.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests apply_visibility_attribute without success state.
	 *
	 * Verifies that blocks with gatherpressRsvpFormVisibility attribute respond correctly to default state.
	 *
	 * @since 1.0.0
	 * @covers ::apply_visibility_attribute
	 *
	 * @return void
	 */
	public function test_apply_visibility_attribute_without_success(): void {
		$instance = Rsvp_Form::get_instance();

		// Mock no success parameter using pre_ filter.
		add_filter(
			'gatherpress_pre_get_http_input',
			static function () {
				return null;
			}
		);

		// Test block that shows on success (not in success state).
		$block_content = '<div class="wp-block-group">Success message</div>';
		$block         = array(
			'blockName' => 'core/group',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
		$this->assertStringContainsString( '&quot;onSuccess&quot;:&quot;show&quot;', $result );

		// Test block that hides on success (not in success state).
		$block_content = '<div class="wp-block-gatherpress-form-field">Name field</div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'hide' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );
		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
		$this->assertStringContainsString( '&quot;onSuccess&quot;:&quot;hide&quot;', $result );

		// Clean up filters.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests the conditionally_render_form_fields method.
	 *
	 * Verifies that form fields are conditionally rendered or removed based on event settings.
	 *
	 * @since 1.0.0
	 * @covers ::conditionally_render_form_fields
	 *
	 * @return void
	 */
	public function test_conditionally_render_form_fields(): void {
		$instance = Rsvp_Form::get_instance();

		// Create an event post.
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		// Test non-form-field block (should be unchanged).
		$block_content = '<div class="wp-block-paragraph">Normal paragraph</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(),
		);

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( $block_content, $result );

		// Test form field without conditional field name (should be unchanged).
		$block_content = '<div class="wp-block-gatherpress-form-field">Other field</div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'fieldName' => 'other_field',
			),
		);

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( $block_content, $result );
	}

	/**
	 * Tests conditionally_render_form_fields with guest count field.
	 *
	 * Verifies guest count field behavior based on max guest limit setting.
	 *
	 * @since 1.0.0
	 * @covers ::conditionally_render_form_fields
	 *
	 * @return void
	 */
	public function test_conditionally_render_form_fields_guest_count(): void {
		$instance = Rsvp_Form::get_instance();

		// Create event with no guest limit (0) - field should be removed.
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 0 );

		$this->go_to( get_permalink( $post_id ) );

		$block_content = '<div class="wp-block-gatherpress-form-field"><input type="number" name="gatherpress_rsvp_guests" /></div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'fieldName' => 'gatherpress_rsvp_guests',
			),
		);

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( '', $result, 'Guest field should be removed when max guest limit is 0' );

		// Create event with guest limit > 0 - field should have max attribute.
		$post_id_2 = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		add_post_meta( $post_id_2, 'gatherpress_max_guest_limit', 3 );

		$this->go_to( get_permalink( $post_id_2 ) );

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertStringContainsString( 'max="3"', $result, 'Guest field should have max attribute set' );
		$this->assertStringContainsString( 'min="0"', $result, 'Guest field should have min attribute set' );
		$this->assertNotEquals( '', $result, 'Guest field should not be removed when max guest limit > 0' );
	}

	/**
	 * Tests conditionally_render_form_fields with anonymous field.
	 *
	 * Verifies anonymous field behavior based on anonymous RSVP setting.
	 *
	 * @since 1.0.0
	 * @covers ::conditionally_render_form_fields
	 *
	 * @return void
	 */
	public function test_conditionally_render_form_fields_anonymous(): void {
		$instance = Rsvp_Form::get_instance();

		// Create event with anonymous RSVP disabled - field should be removed.
		$post_id = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', false );

		$this->go_to( get_permalink( $post_id ) );

		$block_content = '<div class="wp-block-gatherpress-form-field"><input type="checkbox" name="gatherpress_rsvp_anonymous" /></div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'fieldName' => 'gatherpress_rsvp_anonymous',
			),
		);

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( '', $result, 'Anonymous field should be removed when anonymous RSVP is disabled' );

		// Create event with anonymous RSVP enabled - field should remain.
		$post_id_2 = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		add_post_meta( $post_id_2, 'gatherpress_enable_anonymous_rsvp', true );

		$this->go_to( get_permalink( $post_id_2 ) );

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( $block_content, $result, 'Anonymous field should remain when anonymous RSVP is enabled' );
	}

	/**
	 * Tests conditionally_render_form_fields without valid post context.
	 *
	 * Verifies that the method handles cases where get_the_ID() returns 0 or false.
	 *
	 * @since 1.0.0
	 * @covers ::conditionally_render_form_fields
	 *
	 * @return void
	 */
	public function test_conditionally_render_form_fields_no_post_context(): void {
		$instance = Rsvp_Form::get_instance();

		// Ensure we're not in a post context.
		$this->go_to( home_url() );

		$block_content = '<div class="wp-block-gatherpress-form-field"><input type="number" name="gatherpress_rsvp_guests" /></div>';
		$block         = array(
			'blockName' => 'gatherpress/form-field',
			'attrs'     => array(
				'fieldName' => 'gatherpress_rsvp_guests',
			),
		);

		$result = $instance->conditionally_render_form_fields( $block_content, $block );
		$this->assertEquals( $block_content, $result, 'Block content should be unchanged when not in post context' );
	}
}
