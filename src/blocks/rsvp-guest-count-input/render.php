<?php
/**
 * Render RSVP Guest Count Input block.
 *
 * Dynamically renders a disabled input for specifying guest count with a customizable label.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Rsvp;

$gatherpress_max_guest_limit = get_post_meta( get_the_ID(), 'gatherpress_max_guest_limit', true );
$gatherpress_input_id        = $block->attributes['inputId'] ?? null;

// This should never be empty.
if ( empty( $gatherpress_input_id ) ) {
	return;
}

// If the maximum guest limit is set to 0, guests are not permitted. Do not render the block.
if ( empty( $gatherpress_max_guest_limit ) ) {
	return;
}

$gatherpress_rsvp         = new Rsvp( get_the_ID() );
$gatherpress_label        = ! empty( $attributes['label'] ) ? $attributes['label'] : __( 'Number of guests?', 'gatherpress' );
$gatherpress_current_user = array();

if ( $gatherpress_rsvp ) {
	$gatherpress_current_user = $gatherpress_rsvp->get( get_current_user_id() );
}

printf(
	'<p %1$s>
		<label for="%2$s">%3$s</label>
		<input
			id="%2$s"
			data-wp-interactive="gatherpress"
			data-wp-watch="callbacks.setGuestCount"
			data-wp-on--change="actions.updateGuestCount"
			type="number"
			placeholder="0"
			aria-label="%4$s"
			min="0"
			max="%5$d"
			value="%6$d"
		/>
	</p>',
	wp_kses_data( get_block_wrapper_attributes() ),
	esc_attr( $gatherpress_input_id ),
	wp_kses_post( $gatherpress_label ),
	esc_html__( 'Enter the number of guests', 'gatherpress' ),
	intval( $gatherpress_max_guest_limit ),
	intval( $gatherpress_current_user['guests'] ?? 0 )
);
