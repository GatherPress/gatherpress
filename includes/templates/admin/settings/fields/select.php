<?php
/**
 * Template for rendering a select input field.
 *
 * This template is used to display a select input field in GatherPress settings pages.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name        The name attribute for the input field.
 * @param string $label       The label text displayed next to the checkbox.
 * @param string $option      The option name in which the field value is stored.
 * @param string $options     The options for the select field.
 * @param mixed  $value       The current value of the checkbox (boolean or equivalent).
 * @param string $description Optional. The description or tooltip text for the field.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $options, $options['items'], $value, $description ) ) {
	return;
}

$gatherpress_disabled = ! empty( $disabled ) ? ' disabled' : '';
// Selects can't use `readonly`. When disabled the field is omitted from
// the POST, so the trailing hidden input's value is what lands in
// `$_POST[$name]` — carry the current (possibly inherited) value so the
// saved value matches what the UI displayed.
$gatherpress_fallback = ! empty( $disabled ) ? (string) $value : '0';

// IMPORTANT: keep the hidden input BEFORE the select. PHP takes the
// last value for a repeated name, so an enabled select's submission
// wins over the hidden fallback. If you reorder these two, the hidden
// overrides the select and the saved value is always the fallback.
?>
<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $gatherpress_fallback ); ?>" />
<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label><br/>
<select id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?>>
	<?php
	foreach ( $options['items'] as $gatherpress_key => $gatherpress_label ) :
		// phpcs:ignore Generic.Files.LineLength.TooLong -- Template output formatting.
		?>
		<option value="<?php echo esc_attr( $gatherpress_key ); ?>" <?php selected( $gatherpress_key, $value, true ); ?>>
			<?php echo esc_html( $gatherpress_label ); ?>
		</option>
		<?php
	endforeach;
	?>
</select>

<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description"><?php echo wp_kses_post( $description ); ?></p>
	<?php
}
