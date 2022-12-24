<?php
if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	$attributes = array();
}
?>

<div data-gp_block_name="events-list" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
