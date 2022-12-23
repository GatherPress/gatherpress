<?php
/**
 * $attributes, $block, maybe $content available
 */

$block_title = 'GatherPress Venue';

$gatherpress_venue = get_post( intval( $attributes['venueId'] ) )->post_content;

if ( !  $attributes['venueId'] ) {
	$gatherpress_venue = 'Online only';
}

$wrapper_attributes = get_block_wrapper_attributes();

printf(
	__( '<div %s>The %s is: %s</div>', 'gatherpress' ),
	$wrapper_attributes,
	$block_title,
	$gatherpress_venue
);
