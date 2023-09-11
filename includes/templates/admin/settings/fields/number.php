<?php
/**
 * Render a template for a number input field in GatherPress settings.
 *
 * This template code is responsible for rendering an input field for numbers
 * in GatherPress settings. It includes labels, input attributes, and an example.
 *
 * @package GatherPress\Core\Templates
 * @param string $name      The name attribute for the input field.
 * @param string $label     The label for the input field.
 * @param int    $value     The current value for the input field.
 * @param string $example   An example value or description.
 * @since 1.0.0
 */

if ( ! isset( $name, $label, $option, $value, $description ) ) {
	return;
}
?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="number" name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_attr( $value ); ?>" />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}
	?>
</div>
