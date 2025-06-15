<?php
/**
 * Checkbox Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset(
	$gatherpress_wrapper_attributes,
	$gatherpress_attrs,
	$gatherpress_input_attributes,
	$gatherpress_input_style_string,
	$gatherpress_label_style_string,
	$gatherpress_required_style_string
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $gatherpress_wrapper_attributes ); ?>>
	<input<?php echo wp_kses_data( $gatherpress_input_attributes . $gatherpress_input_style_string ); ?> />
	<label for="<?php echo esc_attr( $gatherpress_attrs['input_id'] ); ?>"<?php echo wp_kses_data( $gatherpress_label_style_string ); ?>>
		<?php echo wp_kses_post( $gatherpress_attrs['label'] ); ?>
	</label>
	<?php if ( $gatherpress_attrs['required'] && ! empty( $gatherpress_attrs['required_text'] ) ) : ?>
		<span class="gatherpress-label-required"<?php echo wp_kses_data( $gatherpress_required_style_string ); ?>>
			<?php echo esc_html( $gatherpress_attrs['required_text'] ); ?>
		</span>
	<?php endif; ?>
</div>
