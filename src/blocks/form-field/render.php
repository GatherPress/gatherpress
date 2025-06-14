<?php
/**
 * Render Form Field block.
 *
 * Dynamically renders a form field with customizable styles and attributes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Utility;

// Get attributes with defaults.
$gatherpress_attrs = array(
	'field_type'          => ! empty( $attributes['fieldType'] ) ? $attributes['fieldType'] : 'text',
	'field_name'          => ! empty( $attributes['fieldName'] ) ? $attributes['fieldName'] : '',
	'field_value'         => isset( $attributes['fieldValue'] ) ? $attributes['fieldValue'] : '',
	'label'               => ! empty( $attributes['label'] ) ? $attributes['label'] : '',
	'placeholder'         => ! empty( $attributes['placeholder'] ) ? $attributes['placeholder'] : '',
	'required'            => isset( $attributes['required'] ) ? (bool) $attributes['required'] : false,
	'required_text'       => ! empty( $attributes['requiredText'] ) ? $attributes['requiredText'] : '(required)',
	'help_text'           => ! empty( $attributes['helpText'] ) ? $attributes['helpText'] : '',
	'min_value'           => isset( $attributes['minValue'] ) ? $attributes['minValue'] : null,
	'max_value'           => isset( $attributes['maxValue'] ) ? $attributes['maxValue'] : null,
	'radio_options'       => ! empty( $attributes['radioOptions'] ) ? $attributes['radioOptions'] : array(),
	'inline_layout'       => isset( $attributes['inlineLayout'] ) ? (bool) $attributes['inlineLayout'] : false,
	'field_width'         => isset( $attributes['fieldWidth'] ) ? $attributes['fieldWidth'] : 100,
	'label_text_color'    => ! empty( $attributes['labelTextColor'] ) ? $attributes['labelTextColor'] : null,
	'field_text_color'    => ! empty( $attributes['fieldTextColor'] ) ? $attributes['fieldTextColor'] : null,
	'field_bg_color'      => ! empty( $attributes['fieldBackgroundColor'] ) ? $attributes['fieldBackgroundColor'] : null,
	'border_color'        => ! empty( $attributes['borderColor'] ) ? $attributes['borderColor'] : null,
	'option_text_color'   => ! empty( $attributes['optionTextColor'] ) ? $attributes['optionTextColor'] : null,
	'label_font_size'     => isset( $attributes['labelFontSize'] ) ? $attributes['labelFontSize'] : null,
	'label_line_height'   => isset( $attributes['labelLineHeight'] ) && $attributes['labelLineHeight'] > 0 ? $attributes['labelLineHeight'] : 1.5,
	'option_font_size'    => isset( $attributes['optionFontSize'] ) ? $attributes['optionFontSize'] : null,
	'option_line_height'  => isset( $attributes['optionLineHeight'] ) && $attributes['optionLineHeight'] > 0 ? $attributes['optionLineHeight'] : 1.5,
	'input_font_size'     => isset( $attributes['inputFontSize'] ) ? $attributes['inputFontSize'] : null,
	'input_line_height'   => isset( $attributes['inputLineHeight'] ) && $attributes['inputLineHeight'] > 0 ? $attributes['inputLineHeight'] : 1.5,
	'input_padding'       => isset( $attributes['inputPadding'] ) ? $attributes['inputPadding'] : 16,
	'input_border_width'  => isset( $attributes['inputBorderWidth'] ) ? $attributes['inputBorderWidth'] : 1,
	'input_border_radius' => isset( $attributes['inputBorderRadius'] ) ? $attributes['inputBorderRadius'] : 0,
	'input_id'            => sprintf( 'gatherpress_%s', wp_rand() ),
);

// Build template path and render.
$template_file = $gatherpress_attrs['field_type'] . '.php';
$template_path = GATHERPRESS_CORE_PATH . '/includes/templates/blocks/form-field/' . $template_file;


// Use default.php if field-specific template doesn't exist.
if ( ! file_exists( $template_path ) ) {
	$template_path = GATHERPRESS_CORE_PATH . '/includes/templates/blocks/form-field/default.php';
}

Utility::render_template(
	$template_path,
	array(
		'gatherpress_attrs' => $gatherpress_attrs,
	),
	true
);
