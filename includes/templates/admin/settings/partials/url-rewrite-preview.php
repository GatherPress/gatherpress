<?php
/**
 * Render a preview of the given urlrewrite value.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name  The name parameter.
 * @param string $value The value parameter representing a urlrewrite.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $name, $value, $suffix ) ) {
	return;
}

$gatherpress_component_attrs = array(
	'name'   => $name,
	'value'  => ! empty( $value ) ? $value : '',
	'suffix' => $suffix,
);
?>
<p>
	<strong><?php esc_html_e( 'Preview:', 'gatherpress' ); ?></strong>
	<span data-gatherpress_component_name="urlrewrite-preview" data-gatherpress_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_component_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></span>
</p>
