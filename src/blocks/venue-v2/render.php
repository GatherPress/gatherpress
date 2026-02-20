<?php
/**
 * Render Venue v2 block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_display_condition = $attributes['displayCondition'] ?? 'any';

// Check display conditions for event post types.
if ( 'any' !== $gatherpress_display_condition ) {
	$gatherpress_post_id   = get_the_ID();
	$gatherpress_post_type = get_post_type( $gatherpress_post_id );

	// Only apply condition checks for event post types.
	if ( Event::POST_TYPE === $gatherpress_post_type ) {
		$gatherpress_venue_terms = get_the_terms( $gatherpress_post_id, '_gatherpress_venue' );

		// Determine what venue types exist.
		$gatherpress_has_physical_venue = false;
		$gatherpress_has_online_event   = false;

		if ( ! empty( $gatherpress_venue_terms ) && ! is_wp_error( $gatherpress_venue_terms ) ) {
			foreach ( $gatherpress_venue_terms as $gatherpress_term ) {
				if ( 'online-event' === $gatherpress_term->slug ) {
					$gatherpress_has_online_event = true;
				} else {
					$gatherpress_has_physical_venue = true;
				}
			}
		}

		// Apply display condition logic.
		if ( 'physical' === $gatherpress_display_condition && ! $gatherpress_has_physical_venue ) {
			return;
		}

		if ( 'online' === $gatherpress_display_condition && ! $gatherpress_has_online_event ) {
			return;
		}
	}
}

// Render the block with inner blocks content.
printf(
	'<div %s>%s</div>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
