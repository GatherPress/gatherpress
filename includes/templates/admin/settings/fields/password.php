<?php
/**
 * Template for displaying a password input field in GatherPress settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string       $name        The name attribute for the input field.
 * @param string       $label       The label text for the input field.
 * @param string       $option      The option name for retrieving the field's value.
 * @param string       $value       The current value of the input field.
 * @param string       $description (Optional) Additional information or instructions for the field.
 * @param string       $size        The size class for styling (e.g., 'regular', 'large', or 'small').
 */

use GatherPress\Core\Utility;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $description, $size, $preview ) ) {
	return;
}

// Use `readonly` rather than `disabled` so the field still submits its
// value; `disabled` inputs are omitted from the POST payload, which would
// drop inherited values out of the blog option on save.
$gatherpress_readonly = ! empty( $disabled ) ? ' readonly' : '';
?>
<div class="form-wrap">
	<label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label>
	<input id="<?php echo esc_attr( $option ); ?>" type="password" name="<?php echo esc_attr( $name ); ?>" class="<?php echo esc_attr( $size . '-text' ); ?>" value="<?php echo esc_attr( $value ); ?>" autocomplete="off" spellcheck="false"<?php echo $gatherpress_readonly; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?> />
	<?php
	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo wp_kses_post( $description ); ?></p>
		<?php
	}

	if ( ! empty( $preview['template'] ) ) {
		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/partials/%s.php', GATHERPRESS_CORE_PATH, $preview['template'] ),
			array_merge(
				array(
					'name'  => $name,
					'value' => $value,
				),
				$preview
			),
			true
		);
	}
	?>
</div>
