<?php
/**
 * Radio Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $gatherpress_attrs ) || ! is_array( $gatherpress_attrs ) ) {
	return;
}

// Build input styles.
$gatherpress_input_styles = array();
if ( null !== $gatherpress_attrs['input_border_width'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-width:%dpx', intval( $gatherpress_attrs['input_border_width'] ) );
}
if ( null !== $gatherpress_attrs['border_color'] ) {
	$gatherpress_input_styles[] = sprintf( 'border-color:%s', esc_attr( $gatherpress_attrs['border_color'] ) );
}

// Build label styles.
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

// Build option styles.
$gatherpress_option_styles = array();
if ( null !== $gatherpress_attrs['option_font_size'] ) {
	$gatherpress_option_styles[] = sprintf( 'font-size:%dpx', intval( $gatherpress_attrs['option_font_size'] ) );
}
if ( null !== $gatherpress_attrs['option_line_height'] ) {
	$gatherpress_option_styles[] = sprintf( 'line-height:%s', esc_attr( $gatherpress_attrs['option_line_height'] ) );
}
if ( null !== $gatherpress_attrs['option_text_color'] ) {
	$gatherpress_option_styles[] = sprintf( 'color:%s', esc_attr( $gatherpress_attrs['option_text_color'] ) );
}

// Build wrapper classes.
$gatherpress_wrapper_classes    = array( sprintf( 'gatherpress-field-type-%s', esc_attr( $gatherpress_attrs['field_type'] ) ) );
$gatherpress_wrapper_attributes = get_block_wrapper_attributes( array( 'class' => implode( ' ', $gatherpress_wrapper_classes ) ) );

$gatherpress_input_style_string  = ! empty( $gatherpress_input_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_input_styles ) ) ) : '';
$gatherpress_label_style_string  = ! empty( $gatherpress_label_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_label_styles ) ) ) : '';
$gatherpress_option_style_string = ! empty( $gatherpress_option_styles ) ? sprintf( ' style="%s"', esc_attr( implode( ';', $gatherpress_option_styles ) ) ) : '';
?>

<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper">
		<legend<?php echo wp_kses_data( $gatherpress_label_style_string ); ?>>
		<?php echo wp_kses_post( $gatherpress_attrs['label'] ); ?>
		</legend>
	<?php if ( $gatherpress_attrs['required'] && ! empty( $gatherpress_attrs['required_text'] ) ) : ?>
			<span class="gatherpress-label-required"><?php echo esc_html( $gatherpress_attrs['required_text'] ); ?></span>
	<?php endif; ?>
	</div>
	<div class="gatherpress-radio-group">
	<?php if ( ! empty( $gatherpress_attrs['radio_options'] ) ) : ?>
		<?php foreach ( $gatherpress_attrs['radio_options'] as $gatherpress_index => $gatherpress_option ) : ?>
			<?php if ( ! empty( $gatherpress_option['label'] ) ) : ?>
				<?php
				$gatherpress_option_id    = sprintf( '%s-%d', $gatherpress_attrs['input_id'], $gatherpress_index );
				$gatherpress_option_value = ! empty( $gatherpress_option['value'] ) ? $gatherpress_option['value'] : $gatherpress_option['label'];
				?>
					<div class="gatherpress-radio-option">
						<input type="radio" name="<?php echo esc_attr( $gatherpress_attrs['field_name'] ); ?>" value="<?php echo esc_attr( $gatherpress_option_value ); ?>" id="<?php echo esc_attr( $gatherpress_option_id ); ?>"<?php echo wp_kses_data( $gatherpress_input_style_string ); ?><?php echo ( $gatherpress_attrs['field_value'] === $gatherpress_option_value ) ? ' checked="checked"' : ''; ?> />
						<label for="<?php echo esc_attr( $gatherpress_option_id ); ?>"<?php echo wp_kses_data( $gatherpress_option_style_string ); ?>>
						<?php echo esc_html( $gatherpress_option['label'] ); ?>
						</label>
					</div>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endif; ?>
	</div>
</div>
