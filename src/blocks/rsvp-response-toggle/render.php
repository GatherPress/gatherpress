<?php
/**
 * Render RSVP Toggle block.
 *
 * Dynamically renders a toggle link to show all or fewer RSVP responses.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

$gatherpress_show_all_text   = isset( $attributes['showAll'] ) ? $attributes['showAll'] : __( 'Show All', 'gatherpress' );
$gatherpress_show_fewer_text = isset( $attributes['showFewer'] ) ? $attributes['showFewer'] : __( 'Show Fewer', 'gatherpress' );

printf(
	'<div %1$s data-show-all="%2$s" data-show-fewer="%3$s">
		<a href="#" role="button" aria-label="%2$s">%2$s</a>
	</div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	esc_attr( $gatherpress_show_all_text ),
	esc_attr( $gatherpress_show_fewer_text ),
);
