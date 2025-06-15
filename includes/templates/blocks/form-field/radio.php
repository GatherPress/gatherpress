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
	$wrapper_attributes,
	$attributes,
	$input_style_string,
	$label_style_string,
	$required_style_string,
	$option_style_string
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper">
		<legend<?php echo wp_kses_data( $label_style_string ); ?>>
			<?php echo wp_kses_post( $attributes['label'] ); ?>
		</legend>
		<?php
		if ( $attributes['required'] && ! empty( $attributes['required_text'] ) ) {
			?>
			<span class="gatherpress-label-required"<?php echo wp_kses_data( $required_style_string ); ?>>
				<?php echo esc_html( $attributes['required_text'] ); ?>
			</span>
			<?php
		}
		?>
	</div>
	<div class="gatherpress-radio-group">
		<?php
		if ( ! empty( $attributes['radio_options'] ) ) {
			foreach ( $attributes['radio_options'] as $gatherpress_index => $gatherpress_option ) {
				if ( ! empty( $gatherpress_option['label'] ) ) {
					$gatherpress_option_id    = sprintf( '%s-%d', $attributes['input_id'], $gatherpress_index );
					$gatherpress_option_value = ! empty( $gatherpress_option['value'] ) ? $gatherpress_option['value'] : $gatherpress_option['label'];
					?>
					<div class="gatherpress-radio-option">
						<input type="radio" name="<?php echo esc_attr( $attributes['field_name'] ); ?>" value="<?php echo esc_attr( $gatherpress_option_value ); ?>" id="<?php echo esc_attr( $gatherpress_option_id ); ?>"<?php echo wp_kses_data( $input_style_string ); ?> <?php checked( $attributes['field_value'], $gatherpress_option_value ); ?> />
						<label for="<?php echo esc_attr( $gatherpress_option_id ); ?>"<?php echo wp_kses_data( $option_style_string ); ?>>
							<?php echo esc_html( $gatherpress_option['label'] ); ?>
						</label>
					</div>
					<?php
				}
			}
		}
		?>
	</div>
</div>
