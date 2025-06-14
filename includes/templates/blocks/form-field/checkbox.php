<?php
/**
 * Checkbox Form Field Template.
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
if ( null !== $gatherpress_attrs['input_border_width'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-width:%dpx', intval( $gatherpress_attrs['input_border_width'] ) );
}
if ( null !== $gatherpress_attrs['border_color'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-color:%s', esc_attr( $gatherpress_attrs['border_color'] ) );
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

$gatherpress_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $gatherpress_wrapper_classes ) ) );

// Build input attributes
$gatherpress_input_attributes = array(
	'id'    => $gatherpress_attrs['input_id'],
	'type'  => 'checkbox',
	'name'  => $gatherpress_attrs['field_name'],
	'value' => '1',
);

if ( $gatherpress_attrs['required'] ) {
	$gatherpress_input_attributes['required'] = 'required';
}

if ( ! empty( $gatherpress_attrs['field_value'] ) ) {
	$gatherpress_input_attributes['checked'] = 'checked';
}

$gatherpress_input_attrs_string = '';
foreach ( $gatherpress_input_attributes as $gatherpress_attr => $gatherpress_value ) {
	$gatherpress_input_attrs_string .= sprintf( ' %s="%s"', esc_attr( $gatherpress_attr ), esc_attr( $gatherpress_value ) );
}

$gatherpress_input_style_string = ! empty( $gatherpress_input_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_input_styles ) ) ) : '';
$gatherpress_label_style_string = ! empty( $gatherpress_label_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_label_styles ) ) ) : '';
?>

<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<input<?php echo wp_kses_data( $gatherpress_input_attrs_string . $gatherpress_input_style_string ); ?> />
	<label for="<?php echo esc_attr( $gatherpress_attrs['input_id'] ); ?>"<?php echo wp_kses_data( $gatherpress_label_style_string ); ?>>
	<?php echo wp_kses_post( $gatherpress_attrs['label'] ); ?>
	</label>
<?php if ( $gatherpress_attrs['required'] && ! empty( $gatherpress_attrs['required_text'] ) ) : ?>
		<span class="gatherpress-label-required"><?php echo esc_html( $gatherpress_attrs['required_text'] ); ?></span>
<?php endif; ?>
</div>
