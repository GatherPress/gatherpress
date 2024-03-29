<?php
/**
 * Template for rendering an autocomplete input field.
 *
 * This template is used to display an autocomplete input field in GatherPress settings pages.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name           The name attribute for the input field.
 * @param string $option         The option name in which the field value is stored.
 * @param string $value          The current value of the input field.
 * @param string $description    The description or tooltip text for the field.
 * @param array  $field_options  Additional options for customizing the field behavior.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $option, $value, $description, $field_options ) ) {
	return;
}

$gatherpress_component_attrs = array(
	'name'         => $name,
	'option'       => $option,
	'value'        => ! empty( $value ) ? $value : '[]',
	'fieldOptions' => $field_options,
);
?>
<div class="regular-text" data-gp_component_name="autocomplete" data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_component_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description"><?php echo wp_kses_post( $description ); ?></p>
	<?php
}
