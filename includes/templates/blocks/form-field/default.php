<?php
/**
 * Default Form Field Template (text, email, url, number).
 *
 * @package GatherPress\Core
 * @since 1.0.0
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
	$tooltip_allowed_html
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<div class="gatherpress-label-wrapper" <?php echo wp_kses_data( $label_wrapper_styles ); ?>>
		<label for="<?php echo esc_attr( $attributes['input_id'] ); ?>"<?php echo wp_kses_data( $label_styles ); ?>>
			<?php echo wp_kses( $attributes['label'], $tooltip_allowed_html ); ?>
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
	<input<?php echo wp_kses_data( $input_attributes . $input_styles ); ?> />
</div>
