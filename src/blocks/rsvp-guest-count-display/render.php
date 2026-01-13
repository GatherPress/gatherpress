<?php
/**
 * Render RSVP Guest Count Display block.
 *
 * Dynamically displays the number of guests a member is bringing in a formatted string.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;

$gatherpress_rsvp = new Rsvp( get_the_ID() );

if ( ! $gatherpress_rsvp ) {
	return;
}

// Retrieve the current user's guest count.
$gatherpress_current_user = $gatherpress_rsvp->get( get_current_user_id() );
$gatherpress_guest_count  = intval( $gatherpress_current_user['guests'] ?? 0 );

if ( ! empty( $block ) && isset( $block->context['commentId'] ) ) {
	$gatherpress_guest_count = intval( get_comment_meta( intval( $block->context['commentId'] ), 'gatherpress_rsvp_guests', true ) );

	if ( empty( $gatherpress_guest_count ) ) {
		return;
	}
}

/* Translators: %d is the number of guests for the singular case. */
$gatherpress_singular_label = __( '+%d guest', 'gatherpress' );

/* Translators: %d is the number of guests for the plural case. */
$gatherpress_plural_label = __( '+%d guests', 'gatherpress' );

$gatherpress_guest_text = sprintf(
	/* Translators: %d is the number of guests. */
	_n( '+%d guest', '+%d guests', $gatherpress_guest_count, 'gatherpress' ),
	$gatherpress_guest_count
);

// Render the block content.
printf(
	'<div %1$s data-guest-singular="%2$s" data-guest-plural="%3$s">%4$s</div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	esc_attr( $gatherpress_singular_label ),
	esc_attr( $gatherpress_plural_label ),
	esc_html( $gatherpress_guest_text )
);
