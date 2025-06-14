<?php
/**
 * Textarea Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $gatherpress_attrs ) || ! is_array( $gatherpress_attrs ) ) {
	return;
}

// Build input styles
$gatherpress_input_styles = array();
if ( null !== $gatherpress_attrs['input_font_size'] ) {
	$gatherpress_input_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_attrs['input_font_size'] ) );
}
if ( null !== $gatherpress_attrs['input_line_height'] ) {
	$gatherpress_input_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_attrs['input_line_height'] ) );
}
if ( null !== $gatherpress_attrs['input_padding'] ) {
	$gatherpress_input_styles[] = sprintf( 'padding:%dpx', intval( $gatherpress_attrs['input_padding'] ) );
}
if ( null !== $gatherpress_attrs['input_border_width'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-width:%dpx', intval( $gatherpress_attrs['input_border_width'] ) );
}
if ( null !== $gatherpress_attrs['input_border_radius'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-radius:%dpx', intval( $gatherpress_attrs['input_border_radius'] ) );
}
if ( null !== $gatherpress_attrs['field_text_color'] ) {
	$gatherpress_input_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_attrs['field_text_color'] ) );
}
if ( null !== $gatherpress_attrs['field_bg_color'] ) {
	$gatherpress_input_styles[] = sprintf( 'background-color:%s', esc_attr( $gatherpress_attrs['field_bg_color'] ) );
}
if ( null !== $gatherpress_attrs['border_color'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-color:%s', esc_attr( $gatherpress_attrs['border_color'] ) );
}
if ( null !== $gatherpress_attrs['field_width'] && 100 !== $gatherpress_attrs['field_width'] ) {
	$gatherpress_input_styles[] = sprintf( 'width:%d%%', intval( $gatherpress_attrs['field_width'] ) );
}

// Build label styles
$gatherpress_label_styles = array();
if ( null !== $gatherpress_attrs['label_font_size'] ) {
	$gatherpress_label_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_attrs['label_font_size'] ) );
}
if ( null !== $gatherpress_attrs['label_line_height'] ) {
	$gatherpress_label_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_attrs['label_line_height'] ) );
}
if ( null !== $gatherpress_attrs['label_text_color'] ) {
	$gatherpress_label_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_attrs['label_text_color'] ) );
}

// Build wrapper classes
$gatherpress_wrapper_classes = array( sprintf( 'gatherpress-field-type-%s', esc_attr( $gatherpress_attrs['field_type'] ) ) );
if ( $gatherpress_attrs['inline_layout'] ) {
	$gatherpress_wrapper_classes[] = 'gatherpress-inline-layout';
}

$gatherpress_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $gatherpress_wrapper_classes ) ) );

// Build textarea attributes
$gatherpress_textarea_attributes = array(
	'id'          => $gatherpress_attrs['input_id'],
	'name'        => $gatherpress_attrs['field_name'],
	'placeholder' => $gatherpress_attrs['placeholder'],
	'rows'        => '4',
);

if ( $gatherpress_attrs['required'] ) {
	$gatherpress_textarea_attributes['required'] = 'required';
}

if ( null !== $gatherpress_attrs['min_value'] && $gatherpress_attrs['min_value'] >= 0 ) {
	$gatherpress_textarea_attributes['minlength'] = $gatherpress_attrs['min_value'];
}

if ( null !== $gatherpress_attrs['max_value'] && $gatherpress_attrs['max_value'] >= 0 ) {
	$gatherpress_textarea_attributes['maxlength'] = $gatherpress_attrs['max_value'];
}

$gatherpress_textarea_attrs_string = '';
foreach ( $gatherpress_textarea_attributes as $gatherpress_attr => $gatherpress_value ) {
	$gatherpress_textarea_attrs_string .= sprintf( ' %s="%s"', esc_attr( $gatherpress_attr ), esc_attr( $gatherpress_value ) );
}

$gatherpress_input_style_string = ! empty( $gatherpress_input_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_input_styles ) ) ) : '';
$gatherpress_label_style_string = ! empty( $gatherpress_label_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_label_styles ) ) ) : '';
?>

<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper">
		<label for="<?php echo esc_attr( $gatherpress_attrs['input_id'] ); ?>"<?php echo wp_kses_data( $gatherpress_label_style_string ); ?>>
		<?php echo wp_kses_post( $gatherpress_attrs['label'] ); ?>
		</label>
	<?php if ( $gatherpress_attrs['required'] && ! empty( $gatherpress_attrs['required_text'] ) ) : ?>
			<span class="gatherpress-label-required"><?php echo esc_html( $gatherpress_attrs['required_text'] ); ?></span>
	<?php endif; ?>
	</div>
	<textarea<?php echo wp_kses_data( $gatherpress_textarea_attrs_string . $gatherpress_input_style_string ); ?>><?php echo esc_html( $gatherpress_attrs['field_value'] ); ?></textarea>
</div>
