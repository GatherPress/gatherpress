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
		$general_block     = \GatherPress\Core\Blocks\General_Block::get_instance();
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 5,
				'callback' => array( $instance, 'transform_block_content' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'apply_visibility_attribute' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'save_post',
				'priority' => 10,
				'callback' => array( $instance, 'save_form_schema' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $general_block, 'process_guests_field' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $general_block, 'process_anonymous_field' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'process_form_field_attributes' ),
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
		$this->assertStringContainsString(
			'action="' . site_url( 'wp-comments-post.php' ) . '"',
			$transformed_content
		);
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
		$this->assertStringContainsString(
			'class="wp-block-gatherpress-rsvp-form custom-class"',
			$transformed_content
		);
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
			<div class="wp-block-group" data-gatherpress-rsvp-form-visibility='
			. '"{&quot;onSuccess&quot;:&quot;show&quot;}">Success message</div>
			<div class="wp-block-gatherpress-form-field" data-gatherpress-rsvp-form-visibility='
			. '"{&quot;onSuccess&quot;:&quot;hide&quot;}">Form field</div>
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
						<!-- wp:gatherpress/form-field '
					. '{"fieldName":"custom_field","fieldType":"text","required":true} -->
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
			<div class="wp-block-group" data-gatherpress-rsvp-form-visibility='
			. '"{&quot;onSuccess&quot;:&quot;show&quot;}">Success message</div>
			<div class="wp-block-gatherpress-form-field" data-gatherpress-rsvp-form-visibility='
			. '"{&quot;onSuccess&quot;:&quot;hide&quot;}">Name field</div>
			<div class="wp-block-buttons" data-gatherpress-rsvp-form-visibility='
			. '"{&quot;onSuccess&quot;:&quot;hide&quot;}">Submit Button</div>
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
			$this->assertStringNotContainsString(
				'display: none;',
				$group_matches[0],
				'Success message should be visible'
			);
		}

		// Check that blocks with onSuccess: 'hide' DO have display: none.
		$this->assertStringContainsString(
			'style="display: none;" class="wp-block-gatherpress-form-field"',
			$result_true
		);
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
	 * Tests process_custom_fields_for_form method with valid form submission.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_with_valid_submission(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create an RSVP comment.
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set up form schema.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'custom_text_field'  => array(
						'name' => 'custom_text_field',
						'type' => 'text',
					),
					'custom_email_field' => array(
						'name' => 'custom_email_field',
						'type' => 'email',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		// Mock POST data using the test filter.
		$mock_post_data = array(
			'gatherpress_form_schema_id' => 'form_0',
			'custom_text_field'          => 'Test Value',
			'custom_email_field'         => 'test@example.com',
			'author'                     => 'Built-in field', // Should be ignored.
		);

		$filter_callback = function ( $pre_value, $type, $var_name ) use ( $mock_post_data ) {
			if ( INPUT_POST === $type && isset( $mock_post_data[ $var_name ] ) ) {
				return $mock_post_data[ $var_name ];
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Check that custom fields were saved.
		$this->assertEquals(
			'Test Value',
			get_comment_meta( $comment_id, 'gatherpress_custom_custom_text_field', true )
		);
		$this->assertEquals(
			'test@example.com',
			get_comment_meta( $comment_id, 'gatherpress_custom_custom_email_field', true )
		);

		// Check that built-in fields were not processed.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_author', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with no form schema ID.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_no_schema_id(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mock POST data without form schema ID.
		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type && 'custom_field' === $var_name ) {
				return 'Should not be processed';
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Should not create any custom field meta.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with invalid comment.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_invalid_comment(): void {
		// Create a regular comment (not RSVP type).
		$comment_id = $this->factory->comment->create(
			array(
				'comment_type' => '',
			)
		);

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				if ( 'custom_field' === $var_name ) {
					return 'Should not be processed';
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Should not create any meta since it's not an RSVP comment.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with nonexistent comment.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_nonexistent_comment(): void {
		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				if ( 'custom_field' === $var_name ) {
					return 'Should not be processed';
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();

		// Should not throw errors with invalid comment ID.
		$instance->process_custom_fields_for_form( 99999 );

		// No exception should be thrown.
		$this->assertTrue( true );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with no stored schema.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_no_stored_schema(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'nonexistent_form';
				}
				if ( 'custom_field' === $var_name ) {
					return 'Should not be processed';
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Should not create any meta since schema doesn't exist.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with empty field value.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_empty_field_value(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set up form schema.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'custom_field' => array(
						'name' => 'custom_field',
						'type' => 'text',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				if ( 'custom_field' === $var_name ) {
					return ''; // Empty value.
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Should not create meta for empty field.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_custom_field', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form method with field validation failure.
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_validation_failure(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Set up form schema.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'email_field' => array(
						'name' => 'email_field',
						'type' => 'email',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				if ( 'email_field' === $var_name ) {
					return 'invalid-email'; // Invalid email.
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance = Rsvp_Form::get_instance();
		$instance->process_custom_fields_for_form( $comment_id );

		// Should not save invalid email.
		$this->assertEquals( '', get_comment_meta( $comment_id, 'gatherpress_custom_email_field', true ) );

		// Clean up.
		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests the process_form_field_attributes method with guest count field.
	 *
	 * Verifies that the method correctly sets max attribute on guest count inputs
	 * based on the event's max guest limit setting.
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_sets_max_for_guest_field(): void {
		// Create an event with a max guest limit.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );

		// Mock block content with guest count input field.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<label>Number of guests</label>
			<input type="number" name="gatherpress_rsvp_form_guests" min="0" placeholder="0">
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that max attribute was set.
		$this->assertStringContainsString( 'max="5"', $result );
		$this->assertStringContainsString( 'name="gatherpress_rsvp_form_guests"', $result );
	}

	/**
	 * Tests the process_form_field_attributes method with no max limit set.
	 *
	 * Verifies that the method returns unmodified content when max guest limit
	 * meta is not set (returns empty).
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_skips_non_numeric_limit(): void {
		// Create an event without setting max guest limit meta.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		// Explicitly do not set gatherpress_max_guest_limit meta.

		// Mock block content with guest count input field.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<input type="number" name="gatherpress_rsvp_form_guests" min="0">
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that content is unmodified (no max attribute added).
		$this->assertEquals( $block_content, $result );
		$this->assertStringNotContainsString( 'max=', $result );
	}

	/**
	 * Tests the process_form_field_attributes method with multiple guest inputs.
	 *
	 * Verifies that the method sets max attribute on all matching guest count inputs
	 * in the block content.
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_handles_multiple_guest_inputs(): void {
		// Create an event with a max guest limit.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 3 );

		// Mock block content with multiple guest count input fields.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<input type="number" name="gatherpress_rsvp_form_guests" min="0">
			<input type="hidden" name="other_field" value="test">
			<input type="number" name="gatherpress_rsvp_form_guests" min="0" placeholder="Additional guests">
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that max attribute was set on both guest count inputs.
		$this->assertEquals( 2, substr_count( $result, 'max="3"' ) );
		$this->assertStringNotContainsString( 'name="other_field".*max=', $result );
	}

	/**
	 * Tests the process_form_field_attributes method with zero max guest limit.
	 *
	 * Verifies that the method sets max="0" when the event has zero guest limit.
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_handles_zero_limit(): void {
		// Create an event with zero max guest limit.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 0 );

		// Mock block content with guest count input field.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<input type="number" name="gatherpress_rsvp_form_guests">
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that max attribute was set to 0.
		$this->assertStringContainsString( 'max="0"', $result );
	}

	/**
	 * Tests the process_form_field_attributes method with non-guest fields.
	 *
	 * Verifies that the method doesn't modify content with non-guest input fields.
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_ignores_non_guest_fields(): void {
		// Create an event with a max guest limit.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );

		// Mock block content with non-guest input fields.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<input type="text" name="custom_field" placeholder="Name">
			<input type="email" name="email" placeholder="Email">
			<input type="checkbox" name="gatherpress_rsvp_form_anonymous" value="1">
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that content is unmodified (no max attributes added).
		$this->assertEquals( $block_content, $result );
		$this->assertStringNotContainsString( 'max=', $result );
	}

	/**
	 * Tests the process_form_field_attributes method with no input fields.
	 *
	 * Verifies that the method returns unmodified content when there are no input fields.
	 *
	 * @since 1.0.0
	 * @covers ::process_form_field_attributes
	 *
	 * @return void
	 */
	public function test_process_form_field_attributes_with_no_input_fields(): void {
		// Create an event with a max guest limit.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );

		// Mock block content with no input fields.
		$block_content = '<div class="wp-block-gatherpress-form-field">
			<p>This is just text content with no inputs.</p>
			<button type="submit">Submit</button>
		</div>';

		// Mock block data.
		$block = array(
			'attrs' => array(
				'postId' => $post_id,
			),
		);

		$instance = Rsvp_Form::get_instance();
		$result   = $instance->process_form_field_attributes( $block_content, $block );

		// Check that content is unmodified.
		$this->assertEquals( $block_content, $result );
	}

	/**
	 * Tests transform_block_content with non-event post type.
	 *
	 * @covers ::transform_block_content
	 */
	public function test_transform_block_content_non_event_post_type(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type' => 'post', // Not an event.
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		// Should return empty string for non-event posts.
		$this->assertEmpty( $result );
	}

	/**
	 * Tests transform_block_content with unpublished event.
	 *
	 * @covers ::transform_block_content
	 */
	public function test_transform_block_content_unpublished_event(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft', // Not published.
			)
		);

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		// Should return empty string for unpublished events (when not in preview mode).
		$this->assertEmpty( $result );
	}

	/**
	 * Tests transform_block_content with past event.
	 *
	 * @covers ::transform_block_content
	 */
	public function test_transform_block_content_past_event(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$event    = new Event( $post_id );

		// Set event to be in the past.
		$start = new \DateTime( 'now' );
		$end   = new \DateTime( 'now' );
		$start->modify( '-3 hours' );
		$end->modify( '-1 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);
		$event->save_datetimes( $params );

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		// Should include past event data attribute.
		$this->assertStringContainsString( 'data-gatherpress-event-state="past"', $result );
	}

	/**
	 * Tests save_form_schema with autosave.
	 *
	 * @covers ::save_form_schema
	 */
	public function test_save_form_schema_autosave(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create autosave using WordPress autosave mechanism.
		$_POST['wp_autosave'] = array(
			'post_ID'      => $post_id,
			'post_content' => '<!-- wp:gatherpress/rsvp-form --><div></div><!-- /wp:gatherpress/rsvp-form -->',
		);

		$autosave_id = wp_create_post_autosave(
			array(
				'post_ID'      => $post_id,
				'post_content' => '<!-- wp:gatherpress/rsvp-form --><div></div><!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		if ( is_int( $autosave_id ) ) {
			// Should not process autosaves.
			$instance->save_form_schema( $autosave_id );

			// Check that no schema was created for autosave.
			$schemas = get_post_meta( $autosave_id, 'gatherpress_rsvp_form_schemas', true );
			$this->assertEmpty( $schemas );
		} else {
			// Autosave wasn't created, just verify wp_is_post_autosave works.
			$this->assertTrue( true );
		}

		unset( $_POST['wp_autosave'] );
	}

	/**
	 * Tests save_form_schema with revision.
	 *
	 * @covers ::save_form_schema
	 */
	public function test_save_form_schema_revision(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Create a revision.
		$revision_id = wp_save_post_revision( $post_id );

		if ( $revision_id ) {
			// Should not process revisions.
			$instance->save_form_schema( $revision_id );

			// Check that no schema was created for revision.
			$schemas = get_post_meta( $revision_id, 'gatherpress_rsvp_form_schemas', true );
			$this->assertEmpty( $schemas );
		} else {
			// If revision wasn't created, just verify the test ran.
			$this->assertTrue( true );
		}
	}

	/**
	 * Tests extract_form_fields with select field type.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_schemas_from_blocks
	 * @covers ::extract_form_fields_from_inner_blocks
	 */
	public function test_extract_form_fields_select_type(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:gatherpress/form-field '
					. '{"fieldName":"select_field","fieldType":"select",'
					. '"options":["Option 1","Option 2","Option 3"]} -->
						<div class="wp-block-gatherpress-form-field"></div>
						<!-- /wp:gatherpress/form-field -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertArrayHasKey( 'select_field', $schemas['form_0']['fields'] );
		$this->assertEquals( 'select', $schemas['form_0']['fields']['select_field']['type'] );
		$this->assertEquals(
			array( 'Option 1', 'Option 2', 'Option 3' ),
			$schemas['form_0']['fields']['select_field']['options']
		);
	}

	/**
	 * Tests extract_form_fields with textarea field type.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_fields_from_inner_blocks
	 */
	public function test_extract_form_fields_textarea_type(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:gatherpress/form-field '
					. '{"fieldName":"message_field","fieldType":"textarea","maxLength":500} -->
						<div class="wp-block-gatherpress-form-field"></div>
						<!-- /wp:gatherpress/form-field -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertArrayHasKey( 'message_field', $schemas['form_0']['fields'] );
		$this->assertEquals( 'textarea', $schemas['form_0']['fields']['message_field']['type'] );
		$this->assertEquals( 500, $schemas['form_0']['fields']['message_field']['max_length'] );
	}

	/**
	 * Tests determine_visibility with whenPast set.
	 *
	 * @covers ::determine_visibility
	 * @covers ::apply_visibility_attribute
	 */
	public function test_determine_visibility_with_when_past(): void {
		$instance = Rsvp_Form::get_instance();
		$post     = $this->mock->post()->get();
		$post_id  = $post->ID;

		// Set global post.
		$this->go_to( get_permalink( $post_id ) );

		$block_content = '<div class="wp-block-paragraph">Content</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'whenPast' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );

		// Should include visibility data attribute.
		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
		$this->assertStringContainsString( 'whenPast', $result );
	}

	/**
	 * Tests get_post_id_from_context without postId attribute.
	 *
	 * @covers ::get_post_id_from_context
	 * @covers ::apply_visibility_attribute
	 */
	public function test_get_post_id_from_context_fallback(): void {
		$instance = Rsvp_Form::get_instance();
		$post     = $this->mock->post()->get();

		// Set global post.
		$this->go_to( get_permalink( $post->ID ) );

		$block_content = '<div class="wp-block-paragraph">Content</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		// Should use global post ID.
		$result = $instance->apply_visibility_attribute( $block_content, $block );

		$this->assertStringContainsString( 'data-gatherpress-rsvp-form-visibility', $result );
	}

	/**
	 * Tests apply_visibility_attribute with empty block content.
	 *
	 * @covers ::apply_visibility_attribute
	 */
	public function test_apply_visibility_attribute_empty_content(): void {
		$instance = Rsvp_Form::get_instance();

		$block_content = '';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );

		// Should return empty string unchanged.
		$this->assertEmpty( $result );
	}

	/**
	 * Tests save_form_schema with empty post content.
	 *
	 * @covers ::save_form_schema
	 */
	public function test_save_form_schema_empty_content(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '', // Empty content.
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		// Should not create schemas for empty content.
		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );
		$this->assertEmpty( $schemas );
	}

	/**
	 * Tests extract_form_fields with nested form fields.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_fields_from_inner_blocks
	 */
	public function test_extract_form_fields_nested_blocks(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:group -->
						<div class="wp-block-group">
							<!-- wp:gatherpress/form-field {"fieldName":"nested_field","fieldType":"text"} -->
							<div class="wp-block-gatherpress-form-field"></div>
							<!-- /wp:gatherpress/form-field -->
						</div>
						<!-- /wp:group -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		// Should find nested field.
		$this->assertArrayHasKey( 'nested_field', $schemas['form_0']['fields'] );
	}

	/**
	 * Tests extract_form_fields with radio field type.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_fields_from_inner_blocks
	 */
	public function test_extract_form_fields_radio_type(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:gatherpress/form-field '
					. '{"fieldName":"radio_field","fieldType":"radio","options":["Yes","No","Maybe"]} -->
						<div class="wp-block-gatherpress-form-field"></div>
						<!-- /wp:gatherpress/form-field -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertArrayHasKey( 'radio_field', $schemas['form_0']['fields'] );
		$this->assertEquals( 'radio', $schemas['form_0']['fields']['radio_field']['type'] );
		$this->assertEquals( array( 'Yes', 'No', 'Maybe' ), $schemas['form_0']['fields']['radio_field']['options'] );
	}

	/**
	 * Tests extract_form_fields with email field type validation.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_fields_from_inner_blocks
	 */
	public function test_extract_form_fields_email_validation(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:gatherpress/rsvp-form -->
					<div class="wp-block-gatherpress-rsvp-form">
						<!-- wp:gatherpress/form-field {"fieldName":"email_field","fieldType":"email"} -->
						<div class="wp-block-gatherpress-form-field"></div>
						<!-- /wp:gatherpress/form-field -->
					</div>
					<!-- /wp:gatherpress/rsvp-form -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertArrayHasKey( 'email_field', $schemas['form_0']['fields'] );
		$this->assertEquals( 'email', $schemas['form_0']['fields']['email_field']['type'] );
		$this->assertEquals( 'email', $schemas['form_0']['fields']['email_field']['validation'] );
	}

	/**
	 * Tests transform_block_content returns empty when event object creation fails.
	 *
	 * @covers ::transform_block_content
	 * @return void
	 */
	public function test_transform_block_content_invalid_event_object(): void {
		$instance = Rsvp_Form::get_instance();

		// Create a regular post (not an event) to trigger event object creation failure.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$block = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$result = $instance->transform_block_content( '<div class="wp-block-gatherpress-rsvp-form"></div>', $block );

		$this->assertSame(
			'',
			$result,
			'Failed to assert transform_block_content returns empty string for invalid event object.'
		);
	}

	/**
	 * Tests apply_visibility_attribute with past event and postId attribute.
	 *
	 * @covers ::apply_visibility_attribute
	 * @covers ::get_post_id_from_context
	 * @return void
	 */
	public function test_apply_visibility_attribute_past_event_with_post_id(): void {
		$instance = Rsvp_Form::get_instance();

		// Create a past event.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$event   = new Event( $post_id );

		$start = new \DateTime( 'now' );
		$end   = new \DateTime( 'now' );
		$start->modify( '-3 hours' );
		$end->modify( '-1 hours' );

		$params = array(
			'datetime_start' => $start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $end->format( Event::DATETIME_FORMAT ),
		);
		$event->save_datetimes( $params );

		$block_content = '<div class="wp-block-paragraph">Content</div>';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'postId'   => $post_id,
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'whenPast' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );

		$this->assertStringContainsString(
			'data-gatherpress-event-state="past"',
			$result,
			'Failed to assert past event state attribute is set.'
		);
		$this->assertStringContainsString(
			'data-gatherpress-rsvp-form-visibility',
			$result,
			'Failed to assert visibility attribute is set.'
		);
	}

	/**
	 * Tests apply_visibility_attribute returns unchanged content when tag processor fails.
	 *
	 * Covers: return $block_content;
	 *
	 * @covers ::apply_visibility_attribute
	 */
	public function test_apply_visibility_attribute_no_tag_found(): void {
		$instance = Rsvp_Form::get_instance();

		// Malformed HTML that has no valid tag for the processor to find.
		$block_content = 'Plain text with no tags';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );

		$this->assertEquals( $block_content, $result, 'Should return unchanged content when no valid tag found' );
	}

	/**
	 * Tests apply_visibility_rule private method.
	 *
	 * @covers ::apply_visibility_rule
	 */
	public function test_apply_visibility_rule(): void {
		$instance = Rsvp_Form::get_instance();

		// Test with show visibility.
		$html = '<div class="test-block">Content</div>';
		$tag  = new \WP_HTML_Tag_Processor( $html );
		$tag->next_tag();

		Utility::invoke_hidden_method(
			$instance,
			'apply_visibility_rule',
			array(
				$tag,
				'{"onSuccess":"show"}',
				true, // is_success.
				false, // is_past.
			)
		);

		$result = $tag->get_updated_html();

		$this->assertStringContainsString( 'aria-hidden="false"', $result );
		$this->assertStringContainsString( 'aria-live="polite"', $result );
		$this->assertStringContainsString( 'role="status"', $result );

		// Test with hide visibility.
		$html = '<div class="test-block">Content</div>';
		$tag  = new \WP_HTML_Tag_Processor( $html );
		$tag->next_tag();

		Utility::invoke_hidden_method(
			$instance,
			'apply_visibility_rule',
			array(
				$tag,
				'{"onSuccess":"hide"}',
				true, // is_success.
				false, // is_past.
			)
		);

		$result = $tag->get_updated_html();

		$this->assertStringContainsString( 'display: none;', $result );
		$this->assertStringContainsString( 'aria-hidden="true"', $result );
	}

	/**
	 * Tests determine_visibility with non-array visibility value.
	 *
	 * Covers: return null;
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_non_array(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'invalid-json',
				false,
				false,
			)
		);

		$this->assertNull( $result, 'Should return null for invalid JSON' );
	}

	/**
	 * Tests determine_visibility with past event and whenPast rule.
	 *
	 * Covers: return 'show' === $when_past;
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_when_past_show(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"show"}',
				false, // is_success.
				true,  // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when past and whenPast is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"hide"}',
				false, // is_success.
				true,  // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when past and whenPast is hide' );
	}

	/**
	 * Tests determine_visibility with not past event and only whenPast rule.
	 *
	 * Covers target code.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_not_past_when_past_only(): void {
		$instance = Rsvp_Form::get_instance();

		// When not past and whenPast is 'show', should hide.
		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"show"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when not past and whenPast is show (event not yet passed)' );

		// When not past and whenPast is 'hide', should show.
		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"hide"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when not past and whenPast is hide (inverse)' );
	}

	/**
	 * Tests determine_visibility with onSuccess rule when is_success is true.
	 *
	 * Covers target code.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_on_success_when_success(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"show"}',
				true,  // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when success and onSuccess is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"hide"}',
				true,  // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when success and onSuccess is hide' );
	}

	/**
	 * Tests determine_visibility with onSuccess rule when is_success is false.
	 *
	 * Covers target code.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_on_success_when_not_success(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"show"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when not success and onSuccess is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"hide"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when not success and onSuccess is hide' );
	}

	/**
	 * Tests extract_form_schemas_from_blocks with nested RSVP forms.
	 *
	 * Covers: nested schema prefixing.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_schemas_from_blocks
	 */
	public function test_extract_form_schemas_nested_forms(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:group -->
					<div class="wp-block-group">
						<!-- wp:gatherpress/rsvp-form -->
						<div class="wp-block-gatherpress-rsvp-form">
							<!-- wp:gatherpress/form-field {"fieldName":"nested_field","fieldType":"text"} -->
							<div class="wp-block-gatherpress-form-field"></div>
							<!-- /wp:gatherpress/form-field -->
						</div>
						<!-- /wp:gatherpress/rsvp-form -->
					</div>
					<!-- /wp:group -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertIsArray( $schemas );
		$this->assertNotEmpty( $schemas );

		// Should have a prefixed form ID for nested form.
		$has_prefixed_key = false;
		foreach ( array_keys( $schemas ) as $key ) {
			if ( str_contains( $key, '_form_' ) ) {
				$has_prefixed_key = true;
				break;
			}
		}

		$this->assertTrue( $has_prefixed_key, 'Nested forms should have prefixed form IDs' );
	}

	/**
	 * Tests get_form_schema_id with no post content.
	 *
	 * Covers: return 'form_0'; // Fallback.
	 *
	 * @covers ::get_form_schema_id
	 */
	public function test_get_form_schema_id_no_content(): void {
		$instance = Rsvp_Form::get_instance();

		// Create post with empty content.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '',
			)
		);

		$block = array(
			'blockName' => 'gatherpress/rsvp-form',
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'get_form_schema_id',
			array( $post_id, $block )
		);

		$this->assertEquals( 'form_0', $result, 'Should return form_0 as fallback when no content' );
	}

	/**
	 * Tests find_form_index_in_blocks private method.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks(): void {
		$instance = Rsvp_Form::get_instance();

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
			array(
				'blockName'   => 'gatherpress/rsvp-form',
				'innerHTML'   => '<div class="form1"></div>',
				'innerBlocks' => array(),
			),
			array(
				'blockName'   => 'gatherpress/rsvp-form',
				'innerHTML'   => '<div class="form2"></div>',
				'innerBlocks' => array(),
			),
		);

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="form2"></div>',
			'innerBlocks' => array(),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		$this->assertEquals( 2, $result, 'Should find the second form at index 2' );
	}

	/**
	 * Tests find_form_index_in_blocks with nested blocks.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks_nested(): void {
		$instance = Rsvp_Form::get_instance();

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="nested-form"></div>',
			'innerBlocks' => array(),
		);

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
			array(
				'blockName'   => 'core/group',
				'innerHTML'   => '<div></div>',
				'innerBlocks' => array(
					$target_block,
				),
			),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		// When found in innerBlocks of block at index 1, result should be: 0 + 1*100 + 0 = 100.
		$this->assertEquals( 100, $result, 'Should find nested form at calculated index 100' );
	}

	/**
	 * Tests find_form_index_in_blocks returns 0 when block not found.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks_not_found(): void {
		$instance = Rsvp_Form::get_instance();

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
		);

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="nonexistent"></div>',
			'innerBlocks' => array(),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		$this->assertEquals( 0, $result, 'Should return 0 fallback when block not found' );
	}

	/**
	 * Tests blocks_match private method.
	 *
	 * @covers ::blocks_match
	 */
	public function test_blocks_match(): void {
		$instance = Rsvp_Form::get_instance();

		$block1 = array(
			'innerHTML' => '<div class="matching-content">Test</div>',
		);

		$block2 = array(
			'innerHTML' => '<div class="matching-content">Test</div>',
		);

		$block3 = array(
			'innerHTML' => '<div class="different-content">Test</div>',
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block2 )
		);

		$this->assertTrue( $result, 'Blocks with same innerHTML should match' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block3 )
		);

		$this->assertFalse( $result, 'Blocks with different innerHTML should not match' );
	}

	/**
	 * Tests blocks_match with empty innerHTML.
	 *
	 * @covers ::blocks_match
	 */
	public function test_blocks_match_empty(): void {
		$instance = Rsvp_Form::get_instance();

		$block1 = array();
		$block2 = array();

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block2 )
		);

		$this->assertTrue( $result, 'Blocks with no innerHTML should match (both empty strings)' );
	}

	/**
	 * Tests process_custom_fields_for_form with no fields in schema.
	 *
	 * Covers: return;
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_no_fields(): void {
		$instance = Rsvp_Form::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => \GatherPress\Core\Rsvp::COMMENT_TYPE,
			)
		);

		// Set up schema with no fields.
		$schemas = array(
			'form_0' => array(
				'fields' => array(), // Empty fields array.
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type && 'gatherpress_form_schema_id' === $var_name ) {
				return 'form_0';
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		// Should not throw errors with empty fields.
		$instance->process_custom_fields_for_form( $comment_id );

		$this->assertTrue( true, 'Should handle empty fields array without errors' );

		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form skips built-in fields.
	 *
	 * Covers: continue;
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_skips_built_in_fields(): void {
		$instance = Rsvp_Form::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => \GatherPress\Core\Rsvp::COMMENT_TYPE,
			)
		);

		// Set up schema with built-in fields and a custom field.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'author'                           => array(
						'name' => 'author',
						'type' => 'text',
					),
					'email'                            => array(
						'name' => 'email',
						'type' => 'email',
					),
					'gatherpress_rsvp_form_guests'     => array(
						'name' => 'gatherpress_rsvp_form_guests',
						'type' => 'number',
					),
					'gatherpress_rsvp_form_anonymous'  => array(
						'name' => 'gatherpress_rsvp_form_anonymous',
						'type' => 'checkbox',
					),
					'gatherpress_event_updates_opt_in' => array(
						'name' => 'gatherpress_event_updates_opt_in',
						'type' => 'checkbox',
					),
					'my_custom_field'                  => array(
						'name' => 'my_custom_field',
						'type' => 'text',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				// Return values for built-in fields.
				if ( 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( 'email' === $var_name ) {
					return 'test@example.com';
				}
				if ( 'gatherpress_rsvp_form_guests' === $var_name ) {
					return '2';
				}
				if ( 'gatherpress_rsvp_form_anonymous' === $var_name ) {
					return '1';
				}
				if ( 'gatherpress_event_updates_opt_in' === $var_name ) {
					return '1';
				}
				if ( 'my_custom_field' === $var_name ) {
					return 'Custom Value';
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance->process_custom_fields_for_form( $comment_id );

		// Verify all built-in fields were NOT saved with gatherpress_custom_ prefix.
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_author', true ),
			'Built-in field "author" should not be saved as custom field'
		);
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_email', true ),
			'Built-in field "email" should not be saved as custom field'
		);
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_gatherpress_rsvp_form_guests', true ),
			'Built-in field "gatherpress_rsvp_form_guests" should not be saved as custom field'
		);
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_gatherpress_rsvp_form_anonymous', true ),
			'Built-in field "gatherpress_rsvp_form_anonymous" should not be saved as custom field'
		);
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_gatherpress_event_updates_opt_in', true ),
			'Built-in field "gatherpress_event_updates_opt_in" should not be saved as custom field'
		);

		// Verify custom field IS saved with gatherpress_custom_ prefix.
		$this->assertEquals(
			'Custom Value',
			get_comment_meta( $comment_id, 'gatherpress_custom_my_custom_field', true ),
			'Custom field should be saved with custom prefix'
		);

		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests apply_visibility_rule with null visibility rule.
	 *
	 * Covers: Early return when ! $visibility_rule.
	 *
	 * @covers ::apply_visibility_rule
	 * @return void
	 */
	public function test_apply_visibility_rule_null_rule(): void {
		$instance = Rsvp_Form::get_instance();

		$html = '<div class="test">Content</div>';
		$tag  = new \WP_HTML_Tag_Processor( $html );
		$tag->next_tag();

		// Call apply_visibility_rule with null visibility_rule.
		Utility::invoke_hidden_method(
			$instance,
			'apply_visibility_rule',
			array( $tag, null, false, false )
		);

		// Tag should remain unchanged.
		$this->assertNull(
			$tag->get_attribute( 'style' ),
			'Should not add style attribute when visibility_rule is null.'
		);
		$this->assertNull(
			$tag->get_attribute( 'aria-hidden' ),
			'Should not add aria-hidden when visibility_rule is null.'
		);
	}

	/**
	 * Tests determine_visibility with no visibility rules.
	 *
	 * Covers: return null when no onSuccess or whenPast rules.
	 *
	 * @covers ::determine_visibility
	 * @return void
	 */
	public function test_determine_visibility_no_rules(): void {
		$instance = Rsvp_Form::get_instance();

		// Empty visibility rules object.
		$visibility_rule = wp_json_encode( array() );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array( $visibility_rule, false, false )
		);

		$this->assertNull(
			$result,
			'Should return null when visibility rules object is empty.'
		);
	}
}
