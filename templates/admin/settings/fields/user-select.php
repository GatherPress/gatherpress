<?php
/**
 * User Select Field template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $name, $option, $value, $description ) ) {
	return;
}

$gatherpress_component_attrs = array(
	'name'   => $name,
	'option' => $option,
	'value'  => ! empty( $value ) ? $value : '[]',
);
?>
<div
	class="regular-text"
	data-gp_component_name="user-select"
	data-gp_component_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_component_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"
></div>
<?php
if ( ! empty( $description ) ) {
	?>
	<p class="description">
		<?php echo esc_html( $description ); ?>
	</p>
	<?php
}
