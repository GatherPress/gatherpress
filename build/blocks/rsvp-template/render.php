<?php

/**
 * Server-side rendering for the RSVP Template block.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block content.
 * @param WP_Block $block Block instance.
 *
 * @return string Rendered block content.
 */
function render_rsvp_template_block( $attributes, $content, $block ) {
	global $rsvp_data;

	// Ensure we have RSVP data to work with.
	if ( empty( $rsvp_data ) ) {
		return '';
	}

	// Provide context for child blocks, e.g., commentId/rsvpId.
	$block->context['commentId'] = $rsvp_data->id;

	// Return the rendered block content wrapped in a custom container.
	return sprintf(
		'<div class="rsvp-template" data-rsvp-id="%s">%s</div>',
		esc_attr( $rsvp_data->id ),
		$content
	);
}

register_block_type( 'gatherpress/rsvp-response', [
    'render_callback' => 'render_rsvp_response_block',
] );
