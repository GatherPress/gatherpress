<?php
/**
 * Radio Form Field Template.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset(
	$wrapper_attributes,
	$attributes,
	$input_attributes,
	$input_styles,
	$label_styles,
	$label_wrapper_styles,
	$required_styles,
	$option_styles,
) ) {
	return;
}
?>

<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
	<?php
	// A real <fieldset> with the <legend> as its first child gives the
	// radio options their programmatic group name (WCAG 1.3.1) — a legend
	// outside a fieldset has no semantics. The label wrapper moves inside
	// the legend (a legend permits flow content) so the label/required
	// layout styling is preserved.
	?>
	<?php
	// The fieldset carries a returned error for the group, as in form-errors.js.
	$gatherpress_has_error    = '' !== (string) $attributes['error_message'];
	$gatherpress_described_by = trim(
		( '' !== (string) $attributes['help_text'] ? $attributes['input_id'] . '-help' : '' )
		. ( $gatherpress_has_error ? ' ' . $attributes['input_id'] . '-error' : '' )
	);
	$gatherpress_autofocus    = ! empty( $attributes['autofocus'] );
	?>
	<fieldset class="gatherpress-fieldset"
		<?php if ( '' !== $gatherpress_described_by ) : ?>
		aria-describedby="<?php echo esc_attr( $gatherpress_described_by ); ?>"
		<?php endif; ?>
		<?php if ( $gatherpress_has_error ) : ?>
		aria-invalid="true"
		<?php endif; ?>
	>
		<legend<?php echo wp_kses_data( $label_styles ); ?>>
			<span class="gatherpress-label-wrapper" <?php echo wp_kses_data( $label_wrapper_styles ); ?>>
				<?php echo wp_kses_post( $attributes['label'] ); ?>
				<?php
				if ( $attributes['required'] && ! empty( $attributes['required_text'] ) ) {
					?>
					<span class="gatherpress-label-required"<?php echo wp_kses_data( $required_styles ); ?>>
						<?php echo esc_html( $attributes['required_text'] ); ?>
					</span>
					<?php
				}
				?>
			</span>
		</legend>
		<div class="gatherpress-radio-group">
		<?php
		if ( ! empty( $attributes['radio_options'] ) ) {
			foreach ( $attributes['radio_options'] as $gatherpress_index => $gatherpress_option ) {
				if ( ! empty( $gatherpress_option['label'] ) ) {
					$gatherpress_option_id    = sprintf( '%s-%d', $attributes['input_id'], $gatherpress_index );
					$gatherpress_option_value = $gatherpress_option['value'] ?? '';
					$gatherpress_option_value = '' === $gatherpress_option_value ?
						$gatherpress_option['label'] :
						$gatherpress_option_value;
					?>
					<div class="gatherpress-radio-option" <?php echo wp_kses_data( $label_wrapper_styles ); ?>>
						<input<?php echo wp_kses_data( $input_attributes . $input_styles ); ?>
							id="<?php echo esc_attr( $gatherpress_option_id ); ?>"
							value="<?php echo esc_attr( $gatherpress_option_value ); ?>"
							<?php checked( $attributes['field_value'], $gatherpress_option_value ); ?>
							<?php echo $gatherpress_autofocus ? 'autofocus' : ''; ?>
						/>
						<?php $gatherpress_autofocus = false; ?>
						<label for="<?php echo esc_attr( $gatherpress_option_id ); ?>"
							<?php echo wp_kses_data( $option_styles ); ?>>
							<?php echo esc_html( $gatherpress_option['label'] ); ?>
						</label>
					</div>
					<?php
				}
			}
		}
		?>
		</div>
		<?php if ( '' !== (string) $attributes['help_text'] ) : ?>
			<p class="gatherpress-help-text" id="<?php echo esc_attr( $attributes['input_id'] . '-help' ); ?>">
				<?php echo wp_kses_post( $attributes['help_text'] ); ?>
			</p>
		<?php endif; ?>
	</fieldset>
	<?php if ( $gatherpress_has_error ) : ?>
		<p class="gatherpress-field-error" id="<?php echo esc_attr( $attributes['input_id'] . '-error' ); ?>">
			<?php echo esc_html( $attributes['error_message'] ); ?>
		</p>
	<?php endif; ?>
</div>
