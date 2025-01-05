<?php
/**
 * Render Guest Count Input block.
 *
 * Dynamically renders a disabled input for specifying guest count with a customizable label.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Rsvp;

$gatherpress_enable_anonymous_rsvp = get_post_meta( get_the_ID(), 'gatherpress_enable_anonymous_rsvp', true );
$gatherpress_input_id              = $block->attributes['inputId'] ?? null;

// This should never be empty.
if ( empty( $gatherpress_input_id ) ) {
	return;
}

// If enable anonymous rsvp is set to 0, displaying as anonymous are not permitted. Do not render the block.
if ( empty( $gatherpress_enable_anonymous_rsvp ) ) {
	return;
}

$gatherpress_rsvp         = new Rsvp( get_the_ID() );
$gatherpress_label        = ! empty( $attributes['label'] ) ? $attributes['label'] : __( 'List me as anonymous', 'gatherpress' );
$gatherpress_current_user = array();

if ( $gatherpress_rsvp ) {
	$gatherpress_current_user = $gatherpress_rsvp->get( get_current_user_id() );
}

printf(
	'<p %1$s>
		<input
			id="%2$s"
			data-wp-interactive="gatherpress"
			data-wp-on--change="actions.updateAnonymous"
			type="checkbox"
			aria-label="%3$s"
			value="1"
			%4$s
		/>
		<label for="%2$s">%5$s</label>
	</p>',
	wp_kses_data( get_block_wrapper_attributes() ),
	esc_attr( $gatherpress_input_id ),
	esc_html__( 'List as anonymous', 'gatherpress' ),
	checked( 1, intval( $gatherpress_current_user['anonymous'] ?? 0 ), false ),
	wp_kses_post( $gatherpress_label )
);
