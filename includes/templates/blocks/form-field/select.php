<?php
/**
 * Select Form Field Template.
 *
 * @package GatherPress\Core
 * @since 0.36.0
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
		// Emit a disabled placeholder option for required selects so the control
		// can be empty. Without it the browser auto-selects the first real option
		// and `required` never fires, silently submitting a default choice. The
		// empty-value fallback rule below also matches Rsvp_Form::get_field_options()
		// so the rendered option list agrees with the schema's allowed values.
		if ( ! empty( $attributes['required'] ) && '' === ( $attributes['field_value'] ?? '' ) ) {
			?>
			<option value="" disabled selected>
				<?php esc_html_e( 'Select an option', 'gatherpress' ); ?>
			</option>
			<?php
		}

		foreach ( $attributes['radio_options'] ?? array() as $gatherpress_option ) {
			if ( empty( $gatherpress_option['label'] ) ) {
				continue;
			}

			$gatherpress_value = $gatherpress_option['value'] ?? '';
			$gatherpress_value = '' === $gatherpress_value ?
				$gatherpress_option['label'] :
				$gatherpress_value;
			?>
			<option value="<?php echo esc_attr( $gatherpress_value ); ?>" <?php selected( $attributes['field_value'], $gatherpress_value ); ?>>
				<?php echo esc_html( $gatherpress_option['label'] ); ?>
			</option>
			<?php
		}
		?>
	</select>
</div>
