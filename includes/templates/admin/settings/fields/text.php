<?php
/**
 * Template for displaying a text input field in GatherPress settings.
 *
 * This template is used to display a text input field with an associated label and optional description
 * in GatherPress settings pages.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name        The name attribute for the input field.
 * @param string $label       The label text for the input field.
 * @param string $option      The option name for retrieving the field's value.
 * @param string $value       The current value of the text input field.
 * @param string $description (Optional) Additional information or instructions for the field.
 * @param string $size        The size class for styling (e.g., 'regular', 'large', or 'small').
 * @param string $type         The input type (e.g., 'text', 'password').
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $description, $size ) ) {
	return;
}

$gatherpress_input_type = $type ?? 'text';

?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="<?php echo esc_attr( $gatherpress_input_type ); ?>" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $size . '-text' ); ?>" value="<?php echo esc_attr( $value ); ?>" />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo wp_kses_post( $description ); ?></p>
		<?php
	}

	do_action( 'gatherpress_text_after', $name, $value );
	?>
</div>
