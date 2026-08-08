<?php
/**
 * Select Form Field Template.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset(
	$wrapper_attributes,
	$attributes,
	$input_attributes,
	$input_styles,
	$label_styles,
	$label_wrapper_styles,
	$required_styles,
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper" <?php echo wp_kses_data( $label_wrapper_styles ); ?>>
		<label for="<?php echo esc_attr( $attributes['input_id'] ); ?>"<?php echo wp_kses_data( $label_styles ); ?>>
			<?php echo wp_kses_post( $attributes['label'] ); ?>
		</label>
		<?php
		if ( $attributes['required'] && ! empty( $attributes['required_text'] ) ) {
			?>
			<span class="gatherpress-label-required"<?php echo wp_kses_data( $required_styles ); ?>>
				<?php echo esc_html( $attributes['required_text'] ); ?>
			</span>
			<?php
		}
		?>
	</div>
	<select<?php echo wp_kses_data( $input_attributes . $input_styles ); ?>>
		<?php
		foreach ( $attributes['radio_options'] ?? array() as $gatherpress_option ) {
			if ( empty( $gatherpress_option['label'] ) ) {
				continue;
			}

			$gatherpress_value = ! empty( $gatherpress_option['value'] ) ? $gatherpress_option['value'] : $gatherpress_option['label'];
			?>
			<option value="<?php echo esc_attr( $gatherpress_value ); ?>" <?php selected( $attributes['field_value'], $gatherpress_value ); ?>>
				<?php echo esc_html( $gatherpress_option['label'] ); ?>
			</option>
			<?php
		}
		?>
	</select>
</div>
