<?php
/**
 * Render Events List block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}
?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> data-gatherpress_block_name="events-list" data-gatherpress_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
