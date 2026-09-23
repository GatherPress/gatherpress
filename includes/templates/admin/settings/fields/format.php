<?php
/**
 * Template for choosing a date or time format from rendered examples.
 *
 * The same shape as the Date Format control on Settings > General: a list of
 * formats shown as the date each one produces, plus a Custom field holding a
 * raw PHP format for anything the list does not cover.
 *
 * @package GatherPress\Core
 * @since TBD
 *
 * @param string $name         The name attribute for the input field.
 * @param string $label        The label text for the field.
 * @param string $option       The option name in which the field value is stored.
 * @param string $value        The current format.
 * @param array  $choices      The offered formats, each with a `format` and an `example`.
 * @param string $custom       The sentinel value marking the Custom radio.
 * @param string $custom_name  The name attribute carrying the Custom field's format.
 * @param array  $preview      (Optional) A partial rendering a live preview of the Custom field.
 * @param string $description  (Optional) Additional information or instructions for the field.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $label, $option, $value, $choices, $custom, $custom_name, $preview, $description ) ) {
	return;
}

$gatherpress_value    = (string) $value;
$gatherpress_disabled = ! empty( $disabled ) ? ' disabled' : '';
$gatherpress_formats  = array_column( $choices, 'format' );

// A saved format the list does not offer is a custom one, which is also how
// an empty list behaves: everything lands in the Custom field.
$gatherpress_is_custom = ! in_array( $gatherpress_value, $gatherpress_formats, true );

// Radios can't use `readonly`, and a disabled input is left out of the POST
// entirely, so the hidden input below carries the current value when the
// field is inherited from the network. It has to come BEFORE the radios:
// PHP keeps the last value for a repeated name, so an enabled radio's
// submission wins over the fallback. Reorder these and the fallback always
// wins instead. Same contract as `fields/select.php`.
$gatherpress_fallback = ! empty( $disabled ) ? $gatherpress_value : '';
?>
<fieldset class="gatherpress-format-field">
	<legend><?php echo esc_html( $label ); ?></legend>
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $gatherpress_fallback ); ?>" />
	<?php
	foreach ( $choices as $gatherpress_index => $gatherpress_choice ) :
		$gatherpress_id = sprintf( '%s_%d', $option, (int) $gatherpress_index );
		?>
		<p>
			<label for="<?php echo esc_attr( $gatherpress_id ); ?>">
				<input
					id="<?php echo esc_attr( $gatherpress_id ); ?>"
					type="radio"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $gatherpress_choice['format'] ); ?>"
					<?php checked( $gatherpress_choice['format'], $gatherpress_value ); ?>
					<?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?>
				/>
				<span class="gatherpress-format-field__example"><?php echo esc_html( $gatherpress_choice['example'] ); ?></span>
				<code class="gatherpress-format-field__code"><?php echo esc_html( $gatherpress_choice['format'] ); ?></code>
			</label>
		</p>
		<?php
	endforeach;

	$gatherpress_custom_radio_id = sprintf( '%s_custom_radio', $option );
	$gatherpress_custom_input_id = sprintf( '%s_custom', $option );
	?>
	<p>
		<label for="<?php echo esc_attr( $gatherpress_custom_radio_id ); ?>">
			<input
				id="<?php echo esc_attr( $gatherpress_custom_radio_id ); ?>"
				type="radio"
				name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( $custom ); ?>"
				<?php checked( $gatherpress_is_custom ); ?>
				<?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?>
			/>
			<?php esc_html_e( 'Custom:', 'gatherpress' ); ?>
		</label>
		<input
			id="<?php echo esc_attr( $gatherpress_custom_input_id ); ?>"
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( $custom_name ); ?>"
			value="<?php echo esc_attr( $gatherpress_is_custom ? $gatherpress_value : '' ); ?>"
			aria-label="<?php esc_attr_e( 'Custom format', 'gatherpress' ); ?>"
			<?php echo $gatherpress_disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static value. ?>
		/>
	</p>
	<?php
	// The list shows each format as the date it produces, so only the Custom
	// field is left needing a preview — and it is the one place someone is
	// still typing format codes.
	if ( ! empty( $preview['template'] ) ) {
		\GatherPress\Core\Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/partials/%s.php', GATHERPRESS_CORE_PATH, $preview['template'] ),
			array_merge(
				array(
					'name'  => $custom_name,
					'value' => $gatherpress_is_custom ? $gatherpress_value : '',
				),
				$preview
			),
			true
		);
	}

	if ( ! empty( $description ) ) {
		?>
		<p class="description"><?php echo wp_kses_post( $description ); ?></p>
		<?php
	}
	?>
</fieldset>
