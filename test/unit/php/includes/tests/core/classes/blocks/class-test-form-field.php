<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Form_Field.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Form_Field;
use GatherPress\Tests\Base;

/**
 * Class Test_Form_Field.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Form_Field
 */
class Test_Form_Field extends Base {
	/**
	 * Tests the constructor and attribute processing.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::process_attributes
	 * @covers ::get_input_id
	 *
	 * @return void
	 */
	public function test_constructor_with_defaults(): void {
		$form_field = new Form_Field( array() );

		$this->assertSame(
			'text',
			$form_field->get_field_type(),
			'Failed to assert default field type is text.'
		);
	}

	/**
	 * Tests the constructor with custom attributes.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::process_attributes
	 * @covers ::get_input_id
	 *
	 * @return void
	 */
	public function test_constructor_with_custom_attributes(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'   => 'email',
				'fieldName'   => 'user_email',
				'placeholder' => 'Enter your email',
				'required'    => true,
			)
		);

		$this->assertSame(
			'email',
			$form_field->get_field_type(),
			'Failed to assert field type is email.'
		);
	}

	/**
	 * Tests get_field_type method.
	 *
	 * @since 1.0.0
	 * @covers ::get_field_type
	 *
	 * @return void
	 */
	public function test_get_field_type(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'number',
			)
		);

		$this->assertSame(
			'number',
			$form_field->get_field_type(),
			'Failed to assert field type is number.'
		);
	}

	/**
	 * Tests get_input_styles for text-based fields.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_input_styles_text_field(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'            => 'text',
				'inputFontSize'        => '16px',
				'fieldTextColor'       => '#333',
				'fieldBackgroundColor' => '#fff',
			)
		);

		$styles = $form_field->get_input_styles();

		$this->assertStringContainsString(
			'font-size:16px',
			$styles,
			'Failed to assert input styles contain font-size.'
		);
		$this->assertStringContainsString(
			'color:#333',
			$styles,
			'Failed to assert input styles contain color.'
		);
		$this->assertStringContainsString(
			'background-color:#fff',
			$styles,
			'Failed to assert input styles contain background-color.'
		);
	}

	/**
	 * Tests get_input_styles for checkbox field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_input_styles_checkbox_field(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'      => 'checkbox',
				'inputFontSize'  => '16px',
				'fieldTextColor' => '#333',
			)
		);

		$styles = $form_field->get_input_styles();

		$this->assertEmpty(
			$styles,
			'Failed to assert checkbox field has no input styles.'
		);
	}

	/**
	 * Tests get_input_styles for radio field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_input_styles_radio_field(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'      => 'radio',
				'inputFontSize'  => '16px',
				'fieldTextColor' => '#333',
			)
		);

		$styles = $form_field->get_input_styles();

		$this->assertEmpty(
			$styles,
			'Failed to assert radio field has no input styles.'
		);
	}

	/**
	 * Tests get_input_styles for hidden field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_input_styles_hidden_field(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'hidden',
			)
		);

		$styles = $form_field->get_input_styles();

		$this->assertEmpty(
			$styles,
			'Failed to assert hidden field has no input styles.'
		);
	}

	/**
	 * Tests get_label_styles method.
	 *
	 * @since 1.0.0
	 * @covers ::get_label_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_label_styles(): void {
		$form_field = new Form_Field(
			array(
				'labelTextColor' => '#000',
			)
		);

		$styles = $form_field->get_label_styles();

		$this->assertStringContainsString(
			'color:#000',
			$styles,
			'Failed to assert label styles contain color.'
		);
	}

	/**
	 * Tests get_label_wrapper_styles method.
	 *
	 * @since 1.0.0
	 * @covers ::get_label_wrapper_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_label_wrapper_styles(): void {
		$form_field = new Form_Field(
			array(
				'labelFontSize'   => '14px',
				'labelLineHeight' => 1.5,
			)
		);

		$styles = $form_field->get_label_wrapper_styles();

		$this->assertStringContainsString(
			'font-size:14px',
			$styles,
			'Failed to assert label wrapper styles contain font-size.'
		);
		$this->assertStringContainsString(
			'line-height:1.5',
			$styles,
			'Failed to assert label wrapper styles contain line-height.'
		);
	}

	/**
	 * Tests get_required_styles method.
	 *
	 * @since 1.0.0
	 * @covers ::get_required_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_required_styles(): void {
		$form_field = new Form_Field(
			array(
				'requiredTextColor' => '#f00',
			)
		);

		$styles = $form_field->get_required_styles();

		$this->assertStringContainsString(
			'color:#f00',
			$styles,
			'Failed to assert required styles contain color.'
		);
	}

	/**
	 * Tests get_option_styles method.
	 *
	 * @since 1.0.0
	 * @covers ::get_option_styles
	 * @covers ::add_style
	 * @covers ::compile_styles
	 *
	 * @return void
	 */
	public function test_get_option_styles(): void {
		$form_field = new Form_Field(
			array(
				'optionFontSize'   => '12px',
				'optionLineHeight' => 1.4,
				'optionTextColor'  => '#555',
			)
		);

		$styles = $form_field->get_option_styles();

		$this->assertStringContainsString(
			'font-size:12px',
			$styles,
			'Failed to assert option styles contain font-size.'
		);
		$this->assertStringContainsString(
			'line-height:1.4',
			$styles,
			'Failed to assert option styles contain line-height.'
		);
		$this->assertStringContainsString(
			'color:#555',
			$styles,
			'Failed to assert option styles contain color.'
		);
	}

	/**
	 * Tests get_wrapper_classes for text field.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_classes
	 *
	 * @return void
	 */
	public function test_get_wrapper_classes_text_field(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'text',
			)
		);

		$classes = $form_field->get_wrapper_classes();

		$this->assertContains(
			'gatherpress-form-field--text',
			$classes,
			'Failed to assert wrapper classes contain field type class.'
		);
	}

	/**
	 * Tests get_wrapper_classes with inline layout.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_classes
	 *
	 * @return void
	 */
	public function test_get_wrapper_classes_inline_layout(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'    => 'text',
				'inlineLayout' => true,
			)
		);

		$classes = $form_field->get_wrapper_classes();

		$this->assertContains(
			'gatherpress-inline-layout',
			$classes,
			'Failed to assert wrapper classes contain inline layout class.'
		);
	}

	/**
	 * Tests get_wrapper_classes without custom className.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_classes
	 *
	 * @return void
	 */
	public function test_get_wrapper_classes_without_custom_class(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'text',
			)
		);

		$classes = $form_field->get_wrapper_classes();

		$this->assertContains(
			'gatherpress-form-field--text',
			$classes,
			'Failed to assert wrapper classes contain field type class.'
		);
		$this->assertCount(
			1,
			$classes,
			'Failed to assert wrapper classes only contain field type class.'
		);
	}

	/**
	 * Tests get_wrapper_classes for checkbox field without inline layout.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_classes
	 *
	 * @return void
	 */
	public function test_get_wrapper_classes_checkbox_no_inline(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'    => 'checkbox',
				'inlineLayout' => true,
			)
		);

		$classes = $form_field->get_wrapper_classes();

		$this->assertNotContains(
			'gatherpress-inline-layout',
			$classes,
			'Failed to assert checkbox field does not have inline layout class.'
		);
	}

	/**
	 * Tests get_wrapper_classes for textarea field without inline layout.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_classes
	 *
	 * @return void
	 */
	public function test_get_wrapper_classes_textarea_no_inline(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'    => 'textarea',
				'inlineLayout' => true,
			)
		);

		$classes = $form_field->get_wrapper_classes();

		$this->assertNotContains(
			'gatherpress-inline-layout',
			$classes,
			'Failed to assert textarea field does not have inline layout class.'
		);
	}

	/**
	 * Tests get_wrapper_attributes method.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_attributes
	 *
	 * @return void
	 */
	public function test_get_wrapper_attributes(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'email',
			)
		);

		$attributes = $form_field->get_wrapper_attributes();

		$this->assertStringContainsString(
			'gatherpress-form-field--email',
			$attributes,
			'Failed to assert wrapper attributes contain field type class.'
		);
	}

	/**
	 * Tests get_wrapper_attributes contains basic structure.
	 *
	 * @since 1.0.0
	 * @covers ::get_wrapper_attributes
	 *
	 * @return void
	 */
	public function test_get_wrapper_attributes_basic_structure(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'text',
			)
		);

		$attributes = $form_field->get_wrapper_attributes();

		$this->assertIsString(
			$attributes,
			'Failed to assert wrapper attributes is a string.'
		);
		$this->assertNotEmpty(
			$attributes,
			'Failed to assert wrapper attributes is not empty.'
		);
	}

	/**
	 * Tests get_input_attributes for checkbox field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_checkbox(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'checkbox',
				'fieldName' => 'accept_terms',
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'type="checkbox"',
			$attributes,
			'Failed to assert checkbox attributes contain type.'
		);
		$this->assertStringContainsString(
			'name="accept_terms"',
			$attributes,
			'Failed to assert checkbox attributes contain name.'
		);
		$this->assertStringContainsString(
			'value="1"',
			$attributes,
			'Failed to assert checkbox attributes contain value.'
		);
	}

	/**
	 * Tests get_input_attributes for radio field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_radio(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'radio',
				'fieldName' => 'choice',
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'type="radio"',
			$attributes,
			'Failed to assert radio attributes contain type.'
		);
		$this->assertStringContainsString(
			'name="choice"',
			$attributes,
			'Failed to assert radio attributes contain name.'
		);
	}

	/**
	 * Tests get_input_attributes for textarea field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_textarea(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'    => 'textarea',
				'fieldName'    => 'message',
				'placeholder'  => 'Enter message',
				'textareaRows' => 5,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'name="message"',
			$attributes,
			'Failed to assert textarea attributes contain name.'
		);
		$this->assertStringContainsString(
			'placeholder="Enter message"',
			$attributes,
			'Failed to assert textarea attributes contain placeholder.'
		);
		$this->assertStringContainsString(
			'rows="5"',
			$attributes,
			'Failed to assert textarea attributes contain rows.'
		);
	}

	/**
	 * Tests get_input_attributes for textarea with min and max length.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_textarea_min_max(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'textarea',
				'fieldName' => 'message',
				'minValue'  => 10,
				'maxValue'  => 500,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'minlength="10"',
			$attributes,
			'Failed to assert textarea attributes contain minlength.'
		);
		$this->assertStringContainsString(
			'maxlength="500"',
			$attributes,
			'Failed to assert textarea attributes contain maxlength.'
		);
	}

	/**
	 * Tests get_input_attributes for hidden field.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_hidden(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'  => 'hidden',
				'fieldName'  => 'token',
				'fieldValue' => 'abc123',
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'type="hidden"',
			$attributes,
			'Failed to assert hidden attributes contain type.'
		);
		$this->assertStringContainsString(
			'name="token"',
			$attributes,
			'Failed to assert hidden attributes contain name.'
		);
		$this->assertStringContainsString(
			'value="abc123"',
			$attributes,
			'Failed to assert hidden attributes contain value.'
		);
	}

	/**
	 * Tests get_input_attributes for number field with min and max.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_number_min_max(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'number',
				'fieldName' => 'quantity',
				'minValue'  => 1,
				'maxValue'  => 100,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'type="number"',
			$attributes,
			'Failed to assert number attributes contain type.'
		);
		$this->assertStringContainsString(
			'min="1"',
			$attributes,
			'Failed to assert number attributes contain min.'
		);
		$this->assertStringContainsString(
			'max="100"',
			$attributes,
			'Failed to assert number attributes contain max.'
		);
	}

	/**
	 * Tests get_input_attributes for text field with minlength and maxlength.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_text_min_max(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'text',
				'fieldName' => 'username',
				'minValue'  => 3,
				'maxValue'  => 20,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'minlength="3"',
			$attributes,
			'Failed to assert text attributes contain minlength.'
		);
		$this->assertStringContainsString(
			'maxlength="20"',
			$attributes,
			'Failed to assert text attributes contain maxlength.'
		);
	}

	/**
	 * Tests get_input_attributes with required attribute.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_required(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'text',
				'fieldName' => 'name',
				'required'  => true,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringContainsString(
			'required="required"',
			$attributes,
			'Failed to assert attributes contain required.'
		);
	}

	/**
	 * Tests get_input_attributes for hidden field without required.
	 *
	 * @since 1.0.0
	 * @covers ::get_input_attributes
	 *
	 * @return void
	 */
	public function test_get_input_attributes_hidden_no_required(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'  => 'hidden',
				'fieldName'  => 'token',
				'fieldValue' => 'abc123',
				'required'   => true,
			)
		);

		$attributes = $form_field->get_input_attributes();

		$this->assertStringNotContainsString(
			'required',
			$attributes,
			'Failed to assert hidden field does not have required attribute.'
		);
	}

	/**
	 * Tests get_template_path for existing field type.
	 *
	 * @since 1.0.0
	 * @covers ::get_template_path
	 *
	 * @return void
	 */
	public function test_get_template_path_existing(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'radio',
			)
		);

		$template_path = $form_field->get_template_path();

		$this->assertStringContainsString(
			'radio.php',
			$template_path,
			'Failed to assert template path contains radio.php.'
		);
		$this->assertFileExists(
			$template_path,
			'Failed to assert radio template file exists.'
		);
	}

	/**
	 * Tests get_template_path falls back to default for non-existing field type.
	 *
	 * @since 1.0.0
	 * @covers ::get_template_path
	 *
	 * @return void
	 */
	public function test_get_template_path_default_fallback(): void {
		$form_field = new Form_Field(
			array(
				'fieldType' => 'email',
			)
		);

		$template_path = $form_field->get_template_path();

		$this->assertStringContainsString(
			'default.php',
			$template_path,
			'Failed to assert template path falls back to default.php.'
		);
		$this->assertFileExists(
			$template_path,
			'Failed to assert default template file exists.'
		);
	}

	/**
	 * Tests render method.
	 *
	 * @since 1.0.0
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render(): void {
		$form_field = new Form_Field(
			array(
				'fieldType'   => 'text',
				'fieldName'   => 'test_field',
				'label'       => 'Test Label',
				'placeholder' => 'Enter text',
			)
		);

		ob_start();
		$form_field->render();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'name="test_field"',
			$output,
			'Failed to assert render output contains field name.'
		);
		$this->assertStringContainsString(
			'Test Label',
			$output,
			'Failed to assert render output contains label.'
		);
	}

	/**
	 * Tests that className is NOT preserved through process_attributes.
	 *
	 * This test proves that the className checking code at lines 310-312 and 338-339
	 * is dead code because process_attributes() does not preserve className.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::process_attributes
	 * @covers ::get_wrapper_classes
	 * @covers ::get_wrapper_attributes
	 *
	 * @return void
	 */
	public function test_className_not_preserved_in_attributes(): void {
		// Create form field with className in raw attributes.
		$form_field = new Form_Field(
			array(
				'fieldName' => 'test_field',
				'className' => 'my-custom-class another-class',
			)
		);

		// Use reflection to access processed attributes.
		$reflection = new \ReflectionClass( $form_field );
		$property   = $reflection->getProperty( 'attributes' );
		$property->setAccessible( true );
		$processed_attrs = $property->getValue( $form_field );

		// Verify className is NOT in processed attributes.
		$this->assertArrayNotHasKey(
			'className',
			$processed_attrs,
			'className should NOT be preserved in processed attributes'
		);

		// Verify wrapper classes do NOT include custom className.
		$method = $reflection->getMethod( 'get_wrapper_classes' );
		$method->setAccessible( true );
		$wrapper_classes = $method->invoke( $form_field );

		$this->assertNotContains(
			'my-custom-class',
			$wrapper_classes,
			'Custom className should NOT be in wrapper classes'
		);
		$this->assertNotContains(
			'another-class',
			$wrapper_classes,
			'Custom className should NOT be in wrapper classes'
		);
	}

	/**
	 * Tests that data-* attributes are NOT preserved through process_attributes.
	 *
	 * This test proves that the data-* attribute checking code at lines 345-348
	 * is dead code because process_attributes() does not preserve data-* attributes.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::process_attributes
	 * @covers ::get_wrapper_attributes
	 *
	 * @return void
	 */
	public function test_data_attributes_not_preserved(): void {
		// Create form field with data-* attributes.
		$form_field = new Form_Field(
			array(
				'fieldName'      => 'test_field',
				'data-custom'    => 'custom-value',
				'data-test-attr' => 'test-value',
			)
		);

		// Use reflection to access processed attributes.
		$reflection = new \ReflectionClass( $form_field );
		$property   = $reflection->getProperty( 'attributes' );
		$property->setAccessible( true );
		$processed_attrs = $property->getValue( $form_field );

		// Verify data-* attributes are NOT in processed attributes.
		$this->assertArrayNotHasKey(
			'data-custom',
			$processed_attrs,
			'data-custom should NOT be preserved in processed attributes'
		);
		$this->assertArrayNotHasKey(
			'data-test-attr',
			$processed_attrs,
			'data-test-attr should NOT be preserved in processed attributes'
		);

		// Verify wrapper attributes do NOT include data-* attributes.
		$wrapper_attrs = $form_field->get_wrapper_attributes();

		$this->assertStringNotContainsString(
			'data-custom',
			$wrapper_attrs,
			'data-custom should NOT be in wrapper attributes'
		);
		$this->assertStringNotContainsString(
			'data-test-attr',
			$wrapper_attrs,
			'data-test-attr should NOT be in wrapper attributes'
		);
	}

	/**
	 * Coverage for wp_kses_post allowing standard post and tooltip HTML.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_wp_kses_post_allows_post_and_tooltip_tags(): void {
		$allowed_html = wp_kses_allowed_html( 'post' );

		// Verify standard post HTML tags are allowed.
		$this->assertArrayHasKey(
			'strong',
			$allowed_html,
			'Should allow strong tag from post HTML.'
		);
		$this->assertArrayHasKey(
			'em',
			$allowed_html,
			'Should allow em tag from post HTML.'
		);
		$this->assertArrayHasKey(
			'a',
			$allowed_html,
			'Should allow anchor tag from post HTML.'
		);

		// Verify span with data-* attributes is allowed (for tooltips).
		$this->assertArrayHasKey(
			'span',
			$allowed_html,
			'Should allow span tag.'
		);
		$this->assertArrayHasKey(
			'data-*',
			$allowed_html['span'],
			'Should allow data-* attributes on span for tooltips.'
		);
	}

	/**
	 * Coverage for labels with standard HTML being properly escaped.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_label_allows_standard_html_formatting(): void {
		// Test that standard formatting HTML is preserved.
		$label_with_formatting = '<strong>Required</strong> field with <em>emphasis</em>';
		$escaped_label         = wp_kses_post( $label_with_formatting );

		$this->assertStringContainsString(
			'<strong>Required</strong>',
			$escaped_label,
			'Strong tags should be preserved in label.'
		);
		$this->assertStringContainsString(
			'<em>emphasis</em>',
			$escaped_label,
			'Em tags should be preserved in label.'
		);
	}

	/**
	 * Coverage for labels with tooltip markup being properly escaped.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_label_allows_tooltip_markup(): void {
		// Test that tooltip markup is preserved.
		$label_with_tooltip = 'Field <span class="gatherpress-tooltip" '
			. 'data-gatherpress-tooltip="Help text">with tooltip</span>';
		$escaped_label      = wp_kses_post( $label_with_tooltip );

		$this->assertStringContainsString(
			'gatherpress-tooltip',
			$escaped_label,
			'Tooltip class should be preserved in label.'
		);
		$this->assertStringContainsString(
			'data-gatherpress-tooltip="Help text"',
			$escaped_label,
			'Tooltip data attribute should be preserved in label.'
		);
	}

	/**
	 * Coverage for labels with dangerous HTML being stripped.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_label_strips_dangerous_html(): void {
		// Test that dangerous HTML tags are stripped.
		$label_with_script = 'Field <script>alert("xss")</script>';
		$escaped_label     = wp_kses_post( $label_with_script );

		$this->assertStringNotContainsString(
			'<script>',
			$escaped_label,
			'Script tags should be stripped from label.'
		);

		// Test that onclick handlers are stripped from allowed tags.
		$label_with_onclick = 'Field <span onclick="alert(1)">text</span>';
		$escaped_onclick    = wp_kses_post( $label_with_onclick );

		$this->assertStringNotContainsString(
			'onclick',
			$escaped_onclick,
			'Onclick handlers should be stripped from span tags.'
		);
	}
}
