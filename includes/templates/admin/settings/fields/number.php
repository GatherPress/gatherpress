<?php
/**
 * Render a template for a number input field in GatherPress settings.
 *
 * This template code is responsible for rendering an input field for numbers
 * in GatherPress settings. It includes labels, input attributes, and an example.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name        The name attribute for the input field.
 * @param string $label       The label for the input field.
 * @param string $option      The option name/id for the input field.
 * @param int    $value       The current value for the input field.
 * @param string $description An optional description for the input field.
 * @param string $size        The size class for styling (e.g., 'regular', 'large', or 'small').
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $description, $size, $min, $max ) ) {
	return;
}

?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="number" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $size . '-text' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo wp_kses_post( $description ); ?></p>
		<?php
	}
	?>
</div>
