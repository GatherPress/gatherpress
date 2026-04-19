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

$gatherpress_disabled = ! empty( $disabled ) ? ' disabled' : '';
// Checkboxes can't use `readonly`. When disabled the field is omitted
// from the POST, so the trailing hidden input's value is what lands in
// `$_POST[$name]` — carry the current (possibly inherited) boolean so
// the saved value matches what the UI displayed.
$gatherpress_fallback = ! empty( $disabled ) && rest_sanitize_boolean( $value ) ? '1' : '0';

// IMPORTANT: keep the hidden input BEFORE the checkbox. PHP takes the
// last value for a repeated name, so a checked (enabled) checkbox wins
// over the hidden fallback. If you reorder these two, the hidden
// overrides the checkbox and the saved value is always the fallback.
?>
<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $gatherpress_fallback ); ?>" />
<input id="<?php echo esc_attr( $option ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, rest_sanitize_boolean( $value ), true ); ?><?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?> />
<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>

<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description"><?php echo wp_kses_post( $description ); ?></p>
	<?php
}
