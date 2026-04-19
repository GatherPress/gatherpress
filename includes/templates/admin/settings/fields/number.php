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
 * @param string $placeholder Optional placeholder shown when the input is empty.
 * @param bool   $allow_empty When true, accept empty as a valid submitted value alongside numeric input.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $description, $size, $min, $max ) ) {
	return;
}

// When allow_empty is on, the field accepts both empty and 0 as saveable
// values — both render literally. The flag is preserved so downstream
// code can distinguish "numeric-only" inputs from "optional numeric" ones.
$gatherpress_placeholder = $placeholder ?? '';
$gatherpress_disabled    = ! empty( $disabled ) ? ' disabled' : '';

?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="number" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $size . '-text' ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>"<?php echo '' !== $gatherpress_placeholder ? ' placeholder="' . esc_attr( $gatherpress_placeholder ) . '"' : ''; ?><?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?> />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo wp_kses_post( $description ); ?></p>
		<?php
	}
	?>
</div>
