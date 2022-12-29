<?php
/**
 * Checkbox Field template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $name, $option, $value, $description ) ) {
	return;
}
?>
<label for="<?php echo esc_attr( $option ); ?>"></label>
<input id="<?php echo esc_attr( $option ); ?>" type="checkbox" name="<?php echo esc_attr( $name ); ?>"  value="1" <?php checked( 1, esc_attr( $value ), true ); ?> />
<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description">
		<?php echo esc_html( $description ); ?>
	</p>
	<?php
}
