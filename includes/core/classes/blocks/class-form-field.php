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
	private array $attributes;

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

	private function process_attributes( array $raw_attributes ): array {
		return array(
			'field_type'          => $raw_attributes['fieldType'] ?? 'text',
			'field_name'          => $raw_attributes['fieldName'] ?? '',
			'field_value'         => $raw_attributes['fieldValue'] ?? '',
			'label'               => $raw_attributes['label'] ?? '',
			'placeholder'         => $raw_attributes['placeholder'] ?? '',
			'required'            => (bool) ( $raw_attributes['required'] ?? false ),
			'required_text'       => $raw_attributes['requiredText'] ?? __( '(required)', 'gatherpress' ),
			'help_text'           => $raw_attributes['helpText'] ?? '',
			'min_value'           => $raw_attributes['minValue'] ?? null,
			'max_value'           => $raw_attributes['maxValue'] ?? null,
			'radio_options'       => $raw_attributes['radioOptions'] ?? array(),
			'inline_layout'       => (bool) ( $raw_attributes['inlineLayout'] ?? false ),
			'field_width'         => $raw_attributes['fieldWidth'] ?? 100,
			'label_text_color'    => $raw_attributes['labelTextColor'] ?? null,
			'field_text_color'    => $raw_attributes['fieldTextColor'] ?? null,
			'field_bg_color'      => $raw_attributes['fieldBackgroundColor'] ?? null,
			'border_color'        => $raw_attributes['borderColor'] ?? null,
			'option_text_color'   => $raw_attributes['optionTextColor'] ?? null,
			'required_text_color' => $raw_attributes['requiredTextColor'] ?? null,
			'label_font_size'     => $raw_attributes['labelFontSize'] ?? null,
			'label_line_height'   => $raw_attributes['labelLineHeight'] ?? 1.5,
			'option_font_size'    => $raw_attributes['optionFontSize'] ?? null,
			'option_line_height'  => $raw_attributes['optionLineHeight'] ?? 1.5,
			'input_font_size'     => $raw_attributes['inputFontSize'] ?? null,
			'input_line_height'   => $raw_attributes['inputLineHeight'] ?? 1.5,
			'input_padding'       => $raw_attributes['inputPadding'] ?? 16,
			'input_border_width'  => $raw_attributes['inputBorderWidth'] ?? 1,
			'input_border_radius' => $raw_attributes['inputBorderRadius'] ?? 0,
			'textarea_rows'       => $raw_attributes['textareaRows'] ?? 4,
			'input_id'            => $this->get_input_id(),
		);
	}

	private function get_input_id(): string {
		return sprintf( 'gatherpress_%s', wp_rand() );
	}

	private function add_style( array &$styles, string $attr_key, string $format ): void {
		if ( ! empty( $this->attributes[ $attr_key ] ) ) {
			$value = is_numeric( $this->attributes[ $attr_key ] )
				? intval( $this->attributes[ $attr_key ] )
				: $this->attributes[ $attr_key ];

			$styles[] = sprintf( $format, esc_attr( $value ) );
		}
	}

	private function compile_styles( array $styles ): string {
		return ! empty( $styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $styles ) ) ) : '';
	}

	public function get_field_type(): string {
		return $this->attributes['field_type'] ?? 'text';
	}

	public function get_input_styles(): string {
		$field_type = $this->get_field_type();
		$styles     = array();

		$text_based_fields = array( 'text', 'email', 'url', 'number', 'textarea' );
		$border_fields     = array( 'text', 'email', 'url', 'number', 'textarea', 'checkbox', 'radio' );

		// Text-based input styles.
		if ( in_array( $field_type, $text_based_fields, true ) ) {
			$this->add_style( $styles, 'input_font_size', 'font-size:%dpx' );
			$this->add_style( $styles, 'input_line_height', 'line-height:%s' );
			$this->add_style( $styles, 'input_padding', 'padding:%dpx' );
			$this->add_style( $styles, 'input_border_radius', 'border-radius:%dpx' );
			$this->add_style( $styles, 'field_text_color', 'color:%s' );
			$this->add_style( $styles, 'field_background_color', 'background-color:%s' );
			$this->add_style( $styles, 'field_width', 'width:%s%%' );
		}

		// Border styles for all input types.
		if ( in_array( $field_type, $border_fields, true ) ) {
			$this->add_style( $styles, 'input_border_width', 'border-width:%dpx' );
			$this->add_style( $styles, 'border_color', 'border-color:%s' );
		}

		return $this->compile_styles( $styles );
	}

	public function get_label_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'label_font_zize', 'font-size:%dpx' );
		$this->add_style( $styles, 'label_line_height', 'line-height:%s' );
		$this->add_style( $styles, 'label_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	public function get_required_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'required_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	public function get_option_styles(): string {
		$styles = array();

		$this->add_style( $styles, 'option_font_size', 'font-size:%dpx' );
		$this->add_style( $styles, 'option_line_height', 'line-height:%s' );
		$this->add_style( $styles, 'option_text_color', 'color:%s' );

		return $this->compile_styles( $styles );
	}

	public function get_wrapper_classes(): array {
		$field_type = $this->get_field_type();
		$classes    = array( sprintf( 'gatherpress-field-type-%s', esc_attr( $field_type ) ) );

		// Add inline layout class for text-based fields.
		if (
			! empty( $this->attributes['inline_layout'] ) &&
			in_array( $field_type, array( 'text', 'email', 'url', 'number' ), true )
		) {
			$classes[] = 'gatherpress-inline-layout';
		}

		return $classes;
	}

	public function get_wrapper_attributes(): string {
		$classes = $this->get_wrapper_classes();

		return get_block_wrapper_attributes( array( 'class' => implode( ' ', $classes ) ) );
	}

	public function get_input_attributes(): string {
		$field_type = $this->get_field_type();
		$attributes = array();

		switch ( $field_type ) {
			case 'checkbox':
				$attributes = array(
					'id'    => $this->attributes['input_id'],
					'type'  => 'checkbox',
					'name'  => $this->attributes['field_name'],
					'value' => '1',
				);

				if ( ! empty( $this->attributes['field_value'] ) ) {
					$attributes['checked'] = 'checked';
				}

				break;

			case 'textarea':
				$attributes = array(
					'id'          => $this->attributes['input_id'],
					'name'        => $this->attributes['field_name'],
					'placeholder' => $this->attributes['placeholder'],
					'rows'        => $this->attributes['textarea_rows'],
				);

				if (
					! empty( $this->attributes['min_value'] ) &&
					$this->attributes['min_value'] >= 0
				) {
					$attributes['minlength'] = $this->attributes['min_value'];
				}

				if (
					! empty( $this->attributes['max_value'] ) &&
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
					'id'          => $this->attributes['input_id'],
					'type'        => $field_type,
					'name'        => $this->attributes['field_name'],
					'placeholder' => $this->attributes['placeholder'],
					'value'       => $this->attributes['field_value'],
				);

				if ( ! empty( $this->attributes['min_value'] ) ) {
					$min_attr                = ( 'number' === $field_type ) ? 'min' : 'minlength';
					$attributes[ $min_attr ] = $this->attributes['min_value'];
				}

				if ( ! empty( $this->attributes['max_value'] ) ) {
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
				'gatherpress_attrs'                 => $this->attributes,
				'gatherpress_wrapper_attributes'    => $this->get_wrapper_attributes(),
				'gatherpress_input_style_string'    => $this->get_input_styles(),
				'gatherpress_label_style_string'    => $this->get_label_styles(),
				'gatherpress_required_style_string' => $this->get_required_styles(),
				'gatherpress_option_style_string'   => $this->get_option_styles(),
				'gatherpress_input_attributes'      => $this->get_input_attributes(),
			),
			true
		);
	}
}
