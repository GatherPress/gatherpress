<?php
/**
 * Container for React blocks.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( empty( $gatherpress_block_name ) ) {
	return;
}

if ( ! isset( $gatherpress_block_attrs ) || ! is_array( $gatherpress_block_attrs ) ) {
	$gatherpress_block_attrs = array();
}
?>

<div data-gp_block_name="<?php echo esc_attr( $gatherpress_block_name ); ?>" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_block_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
