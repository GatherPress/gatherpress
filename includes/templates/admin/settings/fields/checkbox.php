<?php
/**
 * Template for rendering a checkbox input field.
 *
 * This template is used to display a checkbox input field in GatherPress settings pages.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name        The name attribute for the input field.
 * @param string $label       The label text displayed next to the checkbox.
 * @param string $option      The option name in which the field value is stored.
 * @param mixed  $value       The current value of the checkbox (boolean or equivalent).
 * @param string $description Optional. The description or tooltip text for the field.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $description ) ) {
	return;
}
?>
<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
<input id="<?php echo esc_attr( $option ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, rest_sanitize_boolean( $value ), true ); ?> />
<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>

<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description"><?php echo wp_kses_post( $description ); ?></p>
	<?php
}
