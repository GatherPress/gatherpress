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

$args = array(
	'iframe' => array(
		'src' => array(),
		'width' => array(),
		'height' => array(),
		'title' => array(),
		'allow' => array(),
		'allowfullscreen' => array(),
		'frameborder' => array(),
	),
);

// phpcs:ignore
$gp_venue_map = ( get_post( $gatherpress_venue->ID )->post_content ?: '' );

printf(
	'<div>%s</div>',
	// 	phpcs:ignore (WordPress.Security.EscapeOutput.OutputNotEscaped)
	wp_kses( $gp_venue_map, $args )
);
