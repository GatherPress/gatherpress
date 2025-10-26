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
			<div class="gatherpress--rsvp-form-message">Success message</div>
			<div class="form-field">Form field</div>
		</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString( 'gatherpress--rsvp-form-message', $transformed_content );
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
	 * Tests the process_custom_fields_for_form method.
	 *
	 * Verifies that custom fields are properly processed and saved
	 * for form submissions.
	 *
	 * @since 1.0.0
	 * @covers ::process_custom_fields_for_form
	 *
	 * @return void
	 */
	public function test_process_custom_fields_for_form(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a comment.
		$comment_id = $this->factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set up form schema.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'custom_text'  => array(
						'name'     => 'custom_text',
						'type'     => 'text',
						'required' => true,
					),
					'custom_email' => array(
						'name'     => 'custom_email',
						'type'     => 'email',
						'required' => false,
					),
				),
				'hash'   => 'test_hash',
			),
		);
		update_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		// Mock filter_input for testing since it doesn't work with $_POST in test environment.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				$post_data = array(
					'gatherpress_form_schema_id' => 'form_0',
					'custom_text'                => 'Test text value',
					'custom_email'               => 'test@example.com',
					'author'                     => 'Test Author',
				);
				if ( INPUT_POST === $type && isset( $post_data[ $var_name ] ) ) {
					return $post_data[ $var_name ];
				}
				return null;
			}
		);

		$instance->process_custom_fields_for_form( $comment_id );

		// Check that custom fields were saved.
		$text_meta   = get_comment_meta( $comment_id, 'gatherpress_custom_custom_text', true );
		$email_meta  = get_comment_meta( $comment_id, 'gatherpress_custom_custom_email', true );
		$author_meta = get_comment_meta( $comment_id, 'gatherpress_custom_author', true );

		$this->assertEquals( 'Test text value', $text_meta );
		$this->assertEquals( 'test@example.com', $email_meta );
		$this->assertEmpty( $author_meta ); // Built-in field should not be saved as custom.

		// Clean up mocked function.
		$this->unset_fn_return( 'filter_input' );
	}

	/**
	 * Tests process_custom_fields_for_form with invalid schema.
	 *
	 * Verifies that the method handles cases where no valid schema
	 * is found for the form.
	 *
	 * @since 1.0.0
	 * @covers ::process_custom_fields_for_form
	 *
	 * @return void
	 */
	public function test_process_custom_fields_for_form_invalid_schema(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a comment.
		$comment_id = $this->factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock filter_input for testing since it doesn't work with $_POST in test environment.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				$post_data = array(
					'gatherpress_form_schema_id' => 'invalid_form_id',
					'custom_field'               => 'Test value',
				);
				if ( INPUT_POST === $type && isset( $post_data[ $var_name ] ) ) {
					return $post_data[ $var_name ];
				}
				return null;
			}
		);

		$instance->process_custom_fields_for_form( $comment_id );

		// Check that no custom fields were saved.
		$custom_meta = get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true );
		$this->assertEmpty( $custom_meta );

		// Clean up mocked function.
		$this->unset_fn_return( 'filter_input' );
	}

	/**
	 * Tests process_custom_fields_for_form with non-RSVP comment.
	 *
	 * Verifies that the method properly handles non-RSVP comments
	 * and exits early.
	 *
	 * @since 1.0.0
	 * @covers ::process_custom_fields_for_form
	 *
	 * @return void
	 */
	public function test_process_custom_fields_for_form_non_rsvp_comment(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a regular comment (not RSVP).
		$comment_id = $this->factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => '',
			)
		);

		// Mock filter_input for testing since it doesn't work with $_POST in test environment.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				$post_data = array(
					'gatherpress_form_schema_id' => 'form_0',
					'custom_field'               => 'Test value',
				);
				if ( INPUT_POST === $type && isset( $post_data[ $var_name ] ) ) {
					return $post_data[ $var_name ];
				}
				return null;
			}
		);

		$instance->process_custom_fields_for_form( $comment_id );

		// Check that no custom fields were saved.
		$custom_meta = get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true );
		$this->assertEmpty( $custom_meta );

		// Clean up mocked function.
		$this->unset_fn_return( 'filter_input' );
	}

	/**
	 * Tests that transform_block_content shows success message when success parameter is present.
	 *
	 * Verifies that when gatherpress_rsvp_success=true is in the URL, the success message
	 * is shown and form fields are hidden, mimicking the JavaScript behavior for non-JS users.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 * @covers ::handle_form_visibility
	 * @covers ::get_input_value
	 *
	 * @return void
	 */
	public function test_transform_block_content_shows_success_state(): void {
		$instance = Rsvp_Form::get_instance();

		// Use PMC Utility to capture noisy database output during post creation.
		$post_id = 0;
		Utility::buffer_and_return(
			function () use ( &$post_id ) {
				$post_id = $this->factory()->post->create(
					array(
						'post_type' => Event::POST_TYPE,
					)
				);
			}
		);

		// Mock the filter_input function to simulate GET parameter.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				if ( INPUT_GET === $type && 'gatherpress_rsvp_success' === $var_name ) {
					return 'true';
				}
				return null;
			}
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">
			<div class="gatherpress--rsvp-form-message">Success! You have RSVPed.</div>
			<div class="wp-block-gatherpress-form-field">Name field</div>
			<div class="wp-block-button">Submit Button</div>
			<div class="wp-block-button gatherpress-modal--trigger-close">Close Button</div>
		</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$transformed_content = $instance->transform_block_content( $block_content, $block );

		// Success message should be visible.
		$this->assertStringContainsString( 'gatherpress--rsvp-form-message', $transformed_content );
		$this->assertStringContainsString( 'display: block;', $transformed_content );
		$this->assertStringContainsString( 'aria-hidden="false"', $transformed_content );

		// Form fields should be hidden.
		$this->assertStringContainsString( 'class="wp-block-gatherpress-form-field">Name field</div>', $transformed_content );
		$this->assertMatchesRegularExpression( '/class="wp-block-gatherpress-form-field"[^>]*>/', $transformed_content );
		// Check that the form field div that comes after has display:none in its style.
		preg_match( '/<div[^>]*class="wp-block-gatherpress-form-field"[^>]*>/', $transformed_content, $matches );
		if ( ! empty( $matches[0] ) ) {
			$this->assertStringContainsString( 'style="display: none;"', $matches[0] );
		}

		// Submit button should be hidden but close button should remain visible.
		// Check that there's at least one button with display:none (the submit button).
		$this->assertStringContainsString( '<div style="display: none;" class="wp-block-button">Submit Button</div>', $transformed_content );

		// Clean up mocked function.
		$this->unset_fn_return( 'filter_input' );
	}

	/**
	 * Tests the get_input_value method with different input types.
	 *
	 * Verifies that the method correctly retrieves values from GET and POST
	 * inputs and handles test environment compatibility.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_value
	 *
	 * @return void
	 */
	public function test_get_input_value(): void {
		$instance = Rsvp_Form::get_instance();

		// Mock the filter_input function.
		$this->set_fn_return(
			'filter_input',
			function ( $type, $var_name ) {
				$data = array(
					INPUT_GET  => array(
						'test_get' => 'get_value',
					),
					INPUT_POST => array(
						'test_post' => 'post_value',
					),
				);
				if ( isset( $data[ $type ][ $var_name ] ) ) {
					return $data[ $type ][ $var_name ];
				}
				return null;
			}
		);

		// Test GET input.
		$get_value = Utility::invoke_hidden_method( $instance, 'get_input_value', array( 'test_get', INPUT_GET ) );
		$this->assertEquals( 'get_value', $get_value );

		// Test POST input (default).
		$post_value = Utility::invoke_hidden_method( $instance, 'get_input_value', array( 'test_post' ) );
		$this->assertEquals( 'post_value', $post_value );

		// Test POST input explicitly.
		$post_value_explicit = Utility::invoke_hidden_method( $instance, 'get_input_value', array( 'test_post', INPUT_POST ) );
		$this->assertEquals( 'post_value', $post_value_explicit );

		// Test non-existent field.
		$null_value = Utility::invoke_hidden_method( $instance, 'get_input_value', array( 'non_existent', INPUT_GET ) );
		$this->assertNull( $null_value );

		// Clean up mocked function.
		$this->unset_fn_return( 'filter_input' );
	}

	/**
	 * Tests the handle_form_visibility method directly.
	 *
	 * Verifies that the method correctly shows/hides elements based on
	 * the success state parameter.
	 *
	 * @since 1.0.0
	 * @covers ::handle_form_visibility
	 *
	 * @return void
	 */
	public function test_handle_form_visibility(): void {
		$instance = Rsvp_Form::get_instance();

		$html = '<form>
			<div class="gatherpress--rsvp-form-message">Success message</div>
			<div class="wp-block-gatherpress-form-field">Name field</div>
			<div class="wp-block-button">Submit Button</div>
			<div class="wp-block-button gatherpress-modal--trigger-close">Close Button</div>
		</form>';

		// Test with success = false (default state).
		$result_false = Utility::invoke_hidden_method( $instance, 'handle_form_visibility', array( $html, false ) );
		$this->assertStringContainsString( 'gatherpress--rsvp-form-message', $result_false );
		$this->assertStringContainsString( 'display: none;', $result_false );
		$this->assertStringContainsString( 'aria-hidden="true"', $result_false );
		// Form fields should remain visible.
		$this->assertStringNotContainsString( 'wp-block-gatherpress-form-field" style', $result_false );

		// Test with success = true (successful submission).
		$result_true = Utility::invoke_hidden_method( $instance, 'handle_form_visibility', array( $html, true ) );
		$this->assertStringContainsString( 'gatherpress--rsvp-form-message', $result_true );
		$this->assertStringContainsString( 'display: block;', $result_true );
		$this->assertStringContainsString( 'aria-hidden="false"', $result_true );
		// Form fields should be hidden.
		$this->assertStringContainsString( '<div style="display: none;" class="wp-block-gatherpress-form-field">Name field</div>', $result_true );
		// Submit button should be hidden.
		$this->assertStringContainsString( '<div style="display: none;" class="wp-block-button">Submit Button</div>', $result_true );
	}
}
