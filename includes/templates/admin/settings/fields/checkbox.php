<?php
/**
 * Checkbox Field template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $name, $label, $option, $value, $description ) ) {
	return;
}
?>
<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
<input id="<?php echo esc_attr( $option ); ?>"
	type="checkbox"
	name="<?php echo esc_attr( $name ); ?>"
	value="1"
	<?php checked( 1, rest_sanitize_boolean( $value ), true ); ?> />
<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>

<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description">
		<?php echo esc_html( $description ); ?>
	</p>
	<?php
}
