<?php
/**
 * Text Field template.
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
<input id="<?php echo esc_attr( $option ); ?>" type='text' name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_html( $value ); ?>" />
<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description">
		<?php echo esc_html( $description ); ?>
	</p>
	<?php
}
