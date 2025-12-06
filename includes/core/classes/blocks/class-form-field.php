<?php
/**
 * The "FormField" class handles the functionality of the FormField block,
 * ensuring proper behavior and rendering of individual form fields.
 *
 * This class is responsible for processing block attributes and dynamically
 * rendering form fields with appropriate styles and validation attributes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

use GatherPress\Core\Utility;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class responsible for managing the "FormField" block and its functionality,
 * including dynamic rendering and attribute processing.
 *
 * @since 1.0.0
 */
class Form_Field {
	/**
	 * Processed form field attributes with defaults applied.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $attributes;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/form-field';

	/**
	 * FormField constructor.
	 *
	 * Initializes a FormField object with the provided block attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes The block attributes array.
	 */
	public function __construct( array $attributes ) {
		$this->attributes = $this->process_attributes( $attributes );
	}

	/**
	 * Process raw block attributes into a standardized format.
	 *
	 * Transforms the raw block attributes from the block editor into a
	 * consistent internal format with proper defaults, type casting,
	 * and naming conventions. Generates a unique input ID and applies
	 * fallback values for all attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_attributes Raw attributes from the block editor.
	 *
	 * @return array Processed attributes with defaults and proper formatting.
	 */
	private function process_attributes( array $raw_attributes ): array {
		return array(
			'field_type'             => $raw_attributes['fieldType'] ?? 'text',
			'field_name'             => $raw_attributes['fieldName'] ?? '',
			'field_value'            => $raw_attributes['fieldValue'] ?? '',
			'label'                  => $raw_attributes['label'] ?? '',
			'placeholder'            => $raw_attributes['placeholder'] ?? '',
			'required'               => (bool) ( $raw_attributes['required'] ?? false ),
			'required_text'          => $raw_attributes['requiredText'] ?? __( '(required)', 'gatherpress' ),
			'help_text'              => $raw_attributes['helpText'] ?? '',
			'min_value'              => $raw_attributes['minValue'] ?? null,
			'max_value'              => $raw_attributes['maxValue'] ?? null,
			'radio_options'          => $raw_attributes['radioOptions'] ?? array(),
			'inline_layout'          => (bool) ( $raw_attributes['inlineLayout'] ?? false ),
			'field_width'            => $raw_attributes['fieldWidth'] ?? 100,
			'label_text_color'       => $raw_attributes['labelTextColor'] ?? null,
			'field_text_color'       => $raw_attributes['fieldTextColor'] ?? null,
			'field_background_color' => $raw_attributes['fieldBackgroundColor'] ?? null,
			'border_color'           => $raw_attributes['borderColor'] ?? null,
			'option_text_color'      => $raw_attributes['optionTextColor'] ?? null,
			'required_text_color'    => $raw_attributes['requiredTextColor'] ?? null,
			'label_font_size'        => $raw_attributes['labelFontSize'] ?? null,
			'label_line_height'      => $raw_attributes['labelLineHeight'] ?? 1.5,
			'option_font_size'       => $raw_attributes['optionFontSize'] ?? null,
			'option_line_height'     => $raw_attributes['optionLineHeight'] ?? 1.5,
			'input_font_size'        => $raw_attributes['inputFontSize'] ?? null,
			'input_line_height'      => $raw_attributes['inputLineHeight'] ?? 1.5,
			'input_padding'          => $raw_attributes['inputPadding'] ?? 16,
			'input_border_width'     => $raw_attributes['inputBorderWidth'] ?? 1,
			'input_border_radius'    => $raw_attributes['inputBorderRadius'] ?? 0,
			'autocomplete'           => $raw_attributes['autocomplete'] ?? 'on',
			'textarea_rows'          => $raw_attributes['textareaRows'] ?? 4,
			'input_id'               => $this->get_input_id(),
		);
	}

	/**
	 * Generate a unique input ID for the form field.
	 *
	 * Creates a unique identifier for the input element using
	 * a random number to ensure uniqueness across multiple
	 * form fields on the same page.
	 *
	 * @since 1.0.0
	 *
	 * @return string Unique input ID (e.g., 'gatherpress_123456789').
	 */
	private function get_input_id(): string {
		return sprintf( 'gatherpress_%s', wp_rand() );
	}

	/**
	 * Add a CSS style to the styles array if the attribute exists.
	 *
	 * Checks if the specified attribute key exists and has a value,
	 * then formats it using the provided format string and adds it
	 * to the styles array. Handles both numeric and string values.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $styles   Reference to the styles array to modify.
	 * @param string $attr_key The attribute key to check in the attributes array.
	 * @param string $format   The sprintf format string for the CSS property (e.g., 'color:%s').
	 *
	 * @return void
	 */
	private function add_style( array &$styles, string $attr_key, string $format ): void {
		if ( ! empty( $this->attributes[ $attr_key ] ) ) {
			$styles[] = sprintf( $format, esc_attr( $this->attributes[ $attr_key ] ) );
		}
	}

	/**
	 * Compile an array of CSS styles into a formatted style attribute string.
	 *
	 * Takes an array of CSS style declarations and formats them into
	 * a properly escaped HTML style attribute string.
	 *
	 * @since 1.0.0
	 *
	 * @param array $styles Array of CSS style declarations (e.g., ['color:red', 'font-size:16px']).
	 *
	 * @return string Formatted style attribute string or empty string if no styles.
	 */
	private function compile_styles( array $styles ): string {
		return ! empty( $styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $styles ) ) ) : '';
	}

	/**
	 * Get the field type for the current form field.
	 *
	 * Returns the field type from the processed attributes with
	 * a fallback to 'text' if not specified.
	 *
	 * @since 1.0.0
	 *
	 * @return string The field type (e.g., 'text', 'email', 'checkbox', 'radio').
	 */
	public function get_field_type(): string {
		return $this->attributes['field_type'] ?? 'text';
	}

	/**
	 * Get the CSS styles for input elements.
	 *
	 * Builds CSS styles for form input elements based on field type.
	 * Text-based fields (text, email, tel, url, number, textarea) receive
	 * full styling including fonts, padding, and colors. All input
	 * types receive border styling.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted CSS style attribute string or empty string if no styles.
	 */
	public function get_input_styles(): string {
		$field_type = $this->get_field_type();
		$styles     = array();

		$non_text_based_fields = array( 'checkbox', 'radio', 'hidden' );

		// Text-based input styles.
		if ( ! in_array( $field_type, $non_text_based_fields, true ) ) {
			$this->add_style( $styles, 'input_font_size', 'font-size:%s' );
			$this->add_style( $styles, 'input_line_height', 'line-height:%s' );
			$this->add_style( $styles, 'input_padding', 'padding:%dpx' );
			$this->add_style( $styles, 'input_border_radius', 'border-radius:%dpx' );
			$this->add_style( $styles, 'field_text_color', 'color:%s' );
			$this->add_style( $styles, 'field_background_color', 'background-color:%s' );
			$this->add_style( $styles, 'field_width', 'width:%s%%' );
			$this->add_style( $styles, 'input_border_width', 'border-width:%dpx' );
			$this->add_style( $styles, 'border_color', 'border-color:%s' );
		}

		return $this->compile_styles( $styles );
	}

	/**
	 * Get the CSS styles for field labels.
	 *
	 * Builds CSS styles for field labels including font size,
	 * line height, and text color. Applied to all field types
	 * that display labels.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted CSS style attribute string or empty string if no styles.
	 */
	public function get_label_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'label_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	/**
	 * Get the CSS styles for label wrapper elements.
	 *
	 * Builds CSS styles for label wrapper containers including font size
	 * and line height. Applied to wrapper elements that contain both
	 * the label and required text.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted CSS style attribute string or empty string if no styles.
	 */
	public function get_label_wrapper_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'label_font_size', 'font-size:%s' );
		$this->add_style( $styles, 'label_line_height', 'line-height:%s' );

		return $this->compile_styles( $styles );
	}

	/**
	 * Get the CSS styles for required field indicator text.
	 *
	 * Builds CSS styles for the required field indicator text,
	 * typically displayed next to the field label.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted CSS style attribute string or empty string if no styles.
	 */
	public function get_required_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'required_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	/**
	 * Get the CSS styles for radio button option labels.
	 *
	 * Builds CSS styles for radio button option labels including
	 * font size, line height, and text color. Only used for radio field types.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted CSS style attribute string or empty string if no styles.
	 */
	public function get_option_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'option_font_size', 'font-size:%s' );
		$this->add_style( $styles, 'option_line_height', 'line-height:%s' );
		$this->add_style( $styles, 'option_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	/**
	 * Get the CSS classes for the block wrapper element.
	 *
	 * Builds an array of CSS classes including the field type class and
	 * conditional layout classes. Inline layout is only applied to
	 * text-based field types.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of CSS class names for the wrapper element.
	 */
	public function get_wrapper_classes(): array {
		$field_type = $this->get_field_type();
		$classes    = array( sprintf( 'gatherpress-form-field--%s', esc_attr( $field_type ) ) );

		// Add inline layout class for text-based fields.
		if (
			! empty( $this->attributes['inline_layout'] ) &&
			! in_array( $field_type, array( 'checkbox', 'radio', 'hidden', 'textarea' ), true )
		) {
			$classes[] = 'gatherpress-inline-layout';
		}

		// Add custom className from block attributes.
		if ( ! empty( $this->attributes['className'] ) ) {
			$custom_classes = explode( ' ', $this->attributes['className'] );
			$classes        = array_merge( $classes, $custom_classes );
		}

		return $classes;
	}

	/**
	 * Get the wrapper attributes for the block container.
	 *
	 * Generates the HTML attributes for the main block wrapper element
	 * using WordPress's get_block_wrapper_attributes() function with
	 * field-specific CSS classes.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted HTML attributes string for the wrapper element.
	 */
	public function get_wrapper_attributes(): string {
		$classes = $this->get_wrapper_classes();

		// Build wrapper arguments.
		$wrapper_args = array( 'class' => implode( ' ', $classes ) );

		// If there's a className in attributes, pass it to WordPress directly too.
		if ( ! empty( $this->attributes['className'] ) ) {
			$wrapper_args['className'] = $this->attributes['className'];
		}

		// Add any data attributes from block attributes.
		foreach ( $this->attributes as $key => $value ) {
			if ( 0 === strpos( $key, 'data-' ) ) {
				$wrapper_args[ $key ] = $value;
			}
		}

		return get_block_wrapper_attributes( $wrapper_args );
	}

	/**
	 * Get the input attributes as a formatted string.
	 *
	 * Builds the appropriate HTML attributes for the input element based on
	 * the field type. Includes common attributes like id, name, type, and
	 * field-specific attributes like min/max values, placeholder, etc.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted HTML attributes string (e.g., ' id="field_123" type="text" name="email"').
	 */
	public function get_input_attributes(): string {
		$field_type = $this->get_field_type();
		$attributes = array();

		switch ( $field_type ) {
			case 'checkbox':
				$attributes = array(
					'id'           => $this->attributes['input_id'],
					'type'         => 'checkbox',
					'name'         => $this->attributes['field_name'],
					'value'        => '1',
					'autocomplete' => $this->attributes['autocomplete'],
				);
				break;

			case 'radio':
				$attributes = array(
					'type'         => 'radio',
					'name'         => $this->attributes['field_name'],
					'autocomplete' => $this->attributes['autocomplete'],
				);

				break;

			case 'textarea':
				$attributes = array(
					'id'           => $this->attributes['input_id'],
					'name'         => $this->attributes['field_name'],
					'placeholder'  => $this->attributes['placeholder'],
					'rows'         => $this->attributes['textarea_rows'],
					'autocomplete' => $this->attributes['autocomplete'],
				);

				if (
					isset( $this->attributes['min_value'] ) &&
					$this->attributes['min_value'] >= 0
				) {
					$attributes['minlength'] = $this->attributes['min_value'];
				}

				if (
					isset( $this->attributes['max_value'] ) &&
					$this->attributes['max_value'] >= 0
				) {
					$attributes['maxlength'] = $this->attributes['max_value'];
				}
				break;

			case 'hidden':
				$attributes = array(
					'type'  => 'hidden',
					'name'  => $this->attributes['field_name'],
					'value' => $this->attributes['field_value'],
				);
				break;

			default:
				$attributes = array(
					'id'           => $this->attributes['input_id'],
					'type'         => $field_type,
					'name'         => $this->attributes['field_name'],
					'placeholder'  => $this->attributes['placeholder'],
					'value'        => $this->attributes['field_value'],
					'autocomplete' => $this->attributes['autocomplete'],
				);

				if ( isset( $this->attributes['min_value'] ) ) {
					$min_attr                = ( 'number' === $field_type ) ? 'min' : 'minlength';
					$attributes[ $min_attr ] = $this->attributes['min_value'];
				}

				if ( isset( $this->attributes['max_value'] ) ) {
					$max_attr                = ( 'number' === $field_type ) ? 'max' : 'maxlength';
					$attributes[ $max_attr ] = $this->attributes['max_value'];
				}
		}

		// Add required attribute for all non-hidden fields.
		if ( 'hidden' !== $field_type && ! empty( $this->attributes['required'] ) ) {
			$attributes['required'] = 'required';
		}

		// Convert array to string.
		$attrs_string = '';

		foreach ( $attributes as $attr => $value ) {
			$attrs_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
		}

		return $attrs_string;
	}

	/**
	 * Get the template path for the current field type.
	 *
	 * Determines the appropriate template file based on the field type.
	 * Falls back to default.php if a field-specific template doesn't exist.
	 *
	 * @since 1.0.0
	 *
	 * @return string The full path to the template file.
	 */
	public function get_template_path(): string {
		$field_type    = $this->get_field_type();
		$template_file = $field_type . '.php';
		$template_path = GATHERPRESS_CORE_PATH . '/includes/templates/blocks/form-field/' . $template_file;

		// Use default.php if field-specific template doesn't exist.
		if ( ! file_exists( $template_path ) ) {
			$template_path = GATHERPRESS_CORE_PATH . '/includes/templates/blocks/form-field/default.php';
		}

		return $template_path;
	}

	/**
	 * Renders the form field based on its type and attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		$template_path = $this->get_template_path();

		Utility::render_template(
			$template_path,
			array(
				'attributes'           => $this->attributes,
				'wrapper_attributes'   => $this->get_wrapper_attributes(),
				'input_styles'         => $this->get_input_styles(),
				'label_styles'         => $this->get_label_styles(),
				'label_wrapper_styles' => $this->get_label_wrapper_styles(),
				'required_styles'      => $this->get_required_styles(),
				'option_styles'        => $this->get_option_styles(),
				'input_attributes'     => $this->get_input_attributes(),
			),
			true
		);
	}
}
