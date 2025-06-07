<?php
/**
 * Render Form Field block.
 *
 * Dynamically renders a form field with customizable styles and attributes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Get attributes with defaults.
$gatherpress_field_type          = ! empty( $attributes['fieldType'] ) ? $attributes['fieldType'] : 'text';
$gatherpress_field_name          = ! empty( $attributes['fieldName'] ) ? $attributes['fieldName'] : '';
$gatherpress_label               = ! empty( $attributes['label'] ) ? $attributes['label'] : '';
$gatherpress_placeholder         = ! empty( $attributes['placeholder'] ) ? $attributes['placeholder'] : '';
$gatherpress_required            = isset( $attributes['required'] ) ? (bool) $attributes['required'] : false;
$gatherpress_required_text       = ! empty( $attributes['requiredText'] ) ? $attributes['requiredText'] : '(required)';
$gatherpress_min_value           = isset( $attributes['minValue'] ) ? $attributes['minValue'] : null;
$gatherpress_max_value           = isset( $attributes['maxValue'] ) ? $attributes['maxValue'] : null;
$gatherpress_radio_options       = ! empty( $attributes['radioOptions'] ) ? $attributes['radioOptions'] : array();
$gatherpress_input_font_size     = isset( $attributes['inputFontSize'] ) ? $attributes['inputFontSize'] : null;
$gatherpress_input_line_height   = isset( $attributes['inputLineHeight'] ) && $attributes['inputLineHeight'] > 0 ? $attributes['inputLineHeight'] : 1.5;
$gatherpress_input_border_width  = isset( $attributes['inputBorderWidth'] ) ? $attributes['inputBorderWidth'] : 1;
$gatherpress_input_border_radius = isset( $attributes['inputBorderRadius'] ) ? $attributes['inputBorderRadius'] : 0;
$gatherpress_label_font_size     = isset( $attributes['labelFontSize'] ) ? $attributes['labelFontSize'] : null;
$gatherpress_label_line_height   = isset( $attributes['labelLineHeight'] ) && $attributes['labelLineHeight'] > 0 ? $attributes['labelLineHeight'] : 1.5;
$gatherpress_option_font_size    = isset( $attributes['optionFontSize'] ) ? $attributes['optionFontSize'] : null;
$gatherpress_option_line_height  = isset( $attributes['optionLineHeight'] ) && $attributes['optionLineHeight'] > 0 ? $attributes['optionLineHeight'] : 1.5;
$gatherpress_side_by_side_layout = isset( $attributes['sideBySideLayout'] ) ? (bool) $attributes['sideBySideLayout'] : false;
$gatherpress_field_width         = isset( $attributes['fieldWidth'] ) ? $attributes['fieldWidth'] : null;
$gatherpress_label_text_color    = ! empty( $attributes['labelTextColor'] ) ? $attributes['labelTextColor'] : null;
$gatherpress_field_text_color    = ! empty( $attributes['fieldTextColor'] ) ? $attributes['fieldTextColor'] : null;
$gatherpress_field_bg_color      = ! empty( $attributes['fieldBackgroundColor'] ) ? $attributes['fieldBackgroundColor'] : null;
$gatherpress_border_color        = ! empty( $attributes['borderColor'] ) ? $attributes['borderColor'] : null;
$gatherpress_option_text_color   = ! empty( $attributes['optionTextColor'] ) ? $attributes['optionTextColor'] : null;
$gatherpress_input_id            = sprintf( 'gatherpress_%s', wp_rand() );

// Build input styles based on field type.
$gatherpress_input_styles = array();

// Text-based inputs get font and border styles.
if ( in_array( $gatherpress_field_type, array( 'text', 'email', 'url', 'number', 'textarea' ), true ) ) {
	if ( null !== $gatherpress_input_font_size ) {
		$gatherpress_input_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_input_font_size ) );
	}
	if ( null !== $gatherpress_input_line_height ) {
		$gatherpress_input_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_input_line_height ) );
	}
	if ( null !== $gatherpress_input_border_width ) {
		$gatherpress_input_styles[] = sprintf( 'border-width:%dpx', intval( $gatherpress_input_border_width ) );
	}
	if ( null !== $gatherpress_input_border_radius ) {
		$gatherpress_input_styles[] = sprintf( 'border-radius:%dpx', intval( $gatherpress_input_border_radius ) );
	}
	if ( null !== $gatherpress_field_text_color ) {
		$gatherpress_input_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_field_text_color ) );
	}
	if ( null !== $gatherpress_field_bg_color ) {
		$gatherpress_input_styles[] = sprintf( 'background-color:%s', esc_attr( $gatherpress_field_bg_color ) );
	}
	if ( null !== $gatherpress_border_color ) {
		$gatherpress_input_styles[] = sprintf( 'border-color:%s', esc_attr( $gatherpress_border_color ) );
	}
}

// Checkbox and radio get border but not radius (preserves their expected shape).
if ( in_array( $gatherpress_field_type, array( 'checkbox', 'radio' ), true ) ) {
	if ( null !== $gatherpress_input_border_width ) {
		$gatherpress_input_styles[] = sprintf( 'border-width:%dpx', intval( $gatherpress_input_border_width ) );
	}
	if ( null !== $gatherpress_border_color ) {
		$gatherpress_input_styles[] = sprintf( 'border-color:%s', esc_attr( $gatherpress_border_color ) );
	}
}

$gatherpress_input_style_string = ! empty( $gatherpress_input_styles ) ? implode( ';', $gatherpress_input_styles ) : '';

// Build label styles.
$gatherpress_label_styles = array();
if ( null !== $gatherpress_label_font_size ) {
	$gatherpress_label_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_label_font_size ) );
}
if ( null !== $gatherpress_label_line_height ) {
	$gatherpress_label_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_label_line_height ) );
}
if ( null !== $gatherpress_label_text_color ) {
	$gatherpress_label_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_label_text_color ) );
}
$gatherpress_label_style_string = ! empty( $gatherpress_label_styles ) ? implode( ';', $gatherpress_label_styles ) : '';

// Build option styles (for radio buttons).
$gatherpress_option_styles = array();
if ( null !== $gatherpress_option_font_size ) {
	$gatherpress_option_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_option_font_size ) );
}
if ( null !== $gatherpress_option_line_height ) {
	$gatherpress_option_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_option_line_height ) );
}
if ( null !== $gatherpress_option_text_color ) {
	$gatherpress_option_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_option_text_color ) );
}
$gatherpress_option_style_string = ! empty( $gatherpress_option_styles ) ? implode( ';', $gatherpress_option_styles ) : '';

// Build input container styles for field width.
$gatherpress_input_container_styles = array();
if ( null !== $gatherpress_field_width && in_array( $gatherpress_field_type, array( 'text', 'email', 'url', 'number', 'textarea' ), true ) ) {
	$gatherpress_input_container_styles[] = sprintf( 'width:%d%%', intval( $gatherpress_field_width ) );
}
$gatherpress_input_container_style_string = ! empty( $gatherpress_input_container_styles ) ? implode( ';', $gatherpress_input_container_styles ) : '';

// Build wrapper classes.
$gatherpress_wrapper_classes     = array( sprintf( 'gatherpress-field-type-%s', esc_attr( $gatherpress_field_type ) ) );
$gatherpress_is_text_based_field = in_array( $gatherpress_field_type, array( 'text', 'email', 'url', 'number', 'textarea' ), true );
if ( $gatherpress_side_by_side_layout && $gatherpress_is_text_based_field ) {
	$gatherpress_wrapper_classes[] = 'gatherpress-side-by-side';
}

// Build block wrapper attributes with field type class.
$gatherpress_wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $gatherpress_wrapper_classes ),
	)
);

// Handle different field types.
if ( 'hidden' === $gatherpress_field_type ) {
	// Hidden field - just output the input, no label wrapper.
	printf(
		'<input type="hidden" name="%1$s" value="%2$s" />',
		esc_attr( $gatherpress_field_name ),
		esc_attr( $gatherpress_placeholder )
	);
} elseif ( 'radio' === $gatherpress_field_type ) {
	// Radio buttons - output legend and options.
	$gatherpress_radio_html = '';
	if ( ! empty( $gatherpress_radio_options ) ) {
		foreach ( $gatherpress_radio_options as $gatherpress_index => $gatherpress_option ) {
			if ( ! empty( $gatherpress_option['label'] ) ) {
				$gatherpress_option_id    = sprintf( '%s-%d', $gatherpress_input_id, $gatherpress_index );
				$gatherpress_option_value = ! empty( $gatherpress_option['value'] ) ? $gatherpress_option['value'] : $gatherpress_option['label'];
				$gatherpress_radio_html  .= sprintf(
					'<div class="gatherpress-radio-option">
						<input type="radio" name="%1$s" value="%2$s" id="%3$s"%4$s />
						<label for="%3$s"%5$s>%6$s</label>
					</div>',
					esc_attr( $gatherpress_field_name ),
					esc_attr( $gatherpress_option_value ),
					esc_attr( $gatherpress_option_id ),
					! empty( $gatherpress_input_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_input_style_string ) ) : '',
					! empty( $gatherpress_option_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_option_style_string ) ) : '',
					esc_html( $gatherpress_option['label'] )
				);
			}
		}
	}

	printf(
		'<div %1$s>
			<div class="gatherpress-label-wrapper"%2$s>
				<legend%3$s>%4$s</legend>
				%5$s
			</div>
			<div class="gatherpress-radio-group">
				%6$s
			</div>
		</div>',
		wp_kses_data( $gatherpress_wrapper_attributes ),
		! empty( $gatherpress_label_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_label_style_string ) ) : '',
		'',
		wp_kses_post( $gatherpress_label ),
		( $gatherpress_required && ! empty( $gatherpress_required_text ) ) ? sprintf( '<span class="gatherpress-label-required">%s</span>', esc_html( $gatherpress_required_text ) ) : '',
		wp_kses_data( $gatherpress_radio_html )
	);
} elseif ( 'textarea' === $gatherpress_field_type ) {
	// Textarea field.
	$gatherpress_textarea_attributes = array(
		'id'          => esc_attr( $gatherpress_input_id ),
		'name'        => esc_attr( $gatherpress_field_name ),
		'placeholder' => esc_attr( $gatherpress_placeholder ),
		'rows'        => '4',
	);

	if ( $gatherpress_required ) {
		$gatherpress_textarea_attributes['required'] = 'required';
	}

	if ( null !== $gatherpress_min_value && $gatherpress_min_value >= 0 ) {
		$gatherpress_textarea_attributes['minlength'] = esc_attr( $gatherpress_min_value );
	}

	if ( null !== $gatherpress_max_value && $gatherpress_max_value >= 0 ) {
		$gatherpress_textarea_attributes['maxlength'] = esc_attr( $gatherpress_max_value );
	}

	$gatherpress_textarea_attrs_string = '';
	foreach ( $gatherpress_textarea_attributes as $gatherpress_attr => $gatherpress_value ) {
		$gatherpress_textarea_attrs_string .= sprintf( ' %s="%s"', esc_attr( $gatherpress_attr ), esc_attr( $gatherpress_value ) );
	}

	if ( ! empty( $gatherpress_input_style_string ) ) {
		$gatherpress_textarea_attrs_string .= sprintf( ' style="%s"', esc_attr( $gatherpress_input_style_string ) );
	}

	printf(
		'<div %1$s>
			<div class="gatherpress-label-wrapper"%2$s>
				<label for="%3$s">%4$s</label>
				%5$s
			</div>
			<div%6$s>
				<textarea%7$s></textarea>
			</div>
		</div>',
		wp_kses_data( $gatherpress_wrapper_attributes ),
		! empty( $gatherpress_label_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_label_style_string ) ) : '',
		esc_attr( $gatherpress_input_id ),
		wp_kses_post( $gatherpress_label ),
		( $gatherpress_required && ! empty( $gatherpress_required_text ) ) ? sprintf( '<span class="gatherpress-label-required">%s</span>', esc_html( $gatherpress_required_text ) ) : '',
		! empty( $gatherpress_input_container_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_input_container_style_string ) ) : '',
		wp_kses_data( $gatherpress_textarea_attrs_string )
	);
} else {
	// Regular input fields (text, email, url, number, checkbox).
	$gatherpress_input_attributes = array(
		'id'          => esc_attr( $gatherpress_input_id ),
		'type'        => esc_attr( $gatherpress_field_type ),
		'name'        => esc_attr( $gatherpress_field_name ),
		'placeholder' => esc_attr( $gatherpress_placeholder ),
	);

	if ( $gatherpress_required ) {
		$gatherpress_input_attributes['required'] = 'required';
	}

	if ( null !== $gatherpress_min_value && in_array( $gatherpress_field_type, array( 'number' ), true ) ) {
		$gatherpress_input_attributes['min'] = esc_attr( $gatherpress_min_value );
	}

	if ( null !== $gatherpress_max_value && in_array( $gatherpress_field_type, array( 'number' ), true ) ) {
		$gatherpress_input_attributes['max'] = esc_attr( $gatherpress_max_value );
	}

	if ( null !== $gatherpress_min_value && $gatherpress_min_value >= 0 && in_array( $gatherpress_field_type, array( 'text', 'email', 'url' ), true ) ) {
		$gatherpress_input_attributes['minlength'] = esc_attr( $gatherpress_min_value );
	}

	if ( null !== $gatherpress_max_value && $gatherpress_max_value >= 0 && in_array( $gatherpress_field_type, array( 'text', 'email', 'url' ), true ) ) {
		$gatherpress_input_attributes['maxlength'] = esc_attr( $gatherpress_max_value );
	}

	$gatherpress_input_attrs_string = '';
	foreach ( $gatherpress_input_attributes as $gatherpress_attr => $gatherpress_value ) {
		$gatherpress_input_attrs_string .= sprintf( ' %s="%s"', esc_attr( $gatherpress_attr ), esc_attr( $gatherpress_value ) );
	}

	if ( ! empty( $gatherpress_input_style_string ) ) {
		$gatherpress_input_attrs_string .= sprintf( ' style="%s"', esc_attr( $gatherpress_input_style_string ) );
	}

	printf(
		'<div %1$s>
			<div class="gatherpress-label-wrapper"%2$s>
				<label for="%3$s">%4$s</label>
				%5$s
			</div>
			<div%6$s>
				<input%7$s />
			</div>
		</div>',
		wp_kses_data( $gatherpress_wrapper_attributes ),
		! empty( $gatherpress_label_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_label_style_string ) ) : '',
		esc_attr( $gatherpress_input_id ),
		wp_kses_post( $gatherpress_label ),
		( $gatherpress_required && ! empty( $gatherpress_required_text ) ) ? sprintf( '<span class="gatherpress-label-required">%s</span>', esc_html( $gatherpress_required_text ) ) : '',
		! empty( $gatherpress_input_container_style_string ) ? sprintf( ' style="%s"', esc_attr( $gatherpress_input_container_style_string ) ) : '',
		wp_kses_data( $gatherpress_input_attrs_string )
	);
}
