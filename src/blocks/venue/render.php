<?php
/**
 *
 */

$block_title = 'GatherPress Venue';

$gatherpress_venue = get_post( intval( $attributes['venueId'] ) );

$wrapper_attributes = get_block_wrapper_attributes();

printf(
	__( '<div %s>The %s is: %s</div>', 'gatherpress' ),
	$wrapper_attributes,
	$block_title,
	$gatherpress_venue->post_content
);
