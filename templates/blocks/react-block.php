<?php
/**
 * Container for React blocks.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */
if ( empty( $name ) ) {
	return;
}

if ( ! isset( $attrs ) || ! is_array( $attrs ) ) {
	$attrs = array();
}
?>

<div data-gp_block_name="<?php echo esc_attr( $name ); ?>" data-gp_block_attrs="<?php echo htmlspecialchars( json_encode( $attrs ), ENT_QUOTES, 'UTF-8' ); ?>"></div>
