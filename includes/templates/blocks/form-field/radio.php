<?php
/**
 * Radio Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset(
	$gatherpress_wrapper_attributes,
	$gatherpress_attrs,
	$gatherpress_input_style_string,
	$gatherpress_label_style_string,
	$gatherpress_required_style_string,
	$gatherpress_option_style_string
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper">
		<legend<?php echo wp_kses_data( $gatherpress_label_style_string ); ?>>
		<?php echo wp_kses_post( $gatherpress_attrs['label'] ); ?>
		</legend>
	<?php if ( $gatherpress_attrs['required'] && ! empty( $gatherpress_attrs['required_text'] ) ) : ?>
			<span class="gatherpress-label-required"<?php echo wp_kses_data( $gatherpress_required_style_string ); ?>>
			<?php echo esc_html( $gatherpress_attrs['required_text'] ); ?>
			</span>
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
