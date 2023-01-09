<?php
/**
 * Render Venue block.
 *
 * @package    GatherPress
 * @subpackage Core
 * @since      1.0.0
 */

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue = get_post( intval( $attributes['venueId'] ?? 0 ) );

if ( Venue::POST_TYPE !== get_post_type( $gatherpress_venue ) ) {
	return;
}

// phpcs:ignore
$gp_venue_map = ( get_post( $gatherpress_venue->ID )->post_content ?: '' );

printf(
	'<div>%s</div>',
    // phpcs:disable
	// print_r( wp_kses_post( $gp_venue_map ), true )
	// wp_kses_post( $gp_venue_map )
	// wp_kses_data( $gp_venue_map )
	$gp_venue_map
    // phpcs:enable
);
