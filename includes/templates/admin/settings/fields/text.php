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
 * @param string $option      The option name for retrieving the field's value.
 * @param string $value       The current value of the text input field.
 * @param string $description (Optional) Additional information or instructions for the field.
 */

if ( ! isset( $name, $label, $option, $value, $description ) ) {
	return;
}
?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="text" name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_attr( $value ); ?>" />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php
	}
	?>
</div>
