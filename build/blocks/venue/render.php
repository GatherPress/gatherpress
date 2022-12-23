<?php
/**
 *
 */

$block_title = 'GatherPress Venue';

$gatherpress_venue = get_post( intval( $attributes['venueId'] ) )->post_content;

// $blocks = parse_blocks( $gatherpress_venue );
	
// foreach ( $blocks as $block ) {
// 	if ( 'gatherpress/venue-information' === $block['blockName'] ) {
// 		$gatherpress_venue = '<pre>' . print_r( $block, true ) . '</pre>';
// 		$gatherpress_venue = $block['innerHTML'];
// 		break;
// 	}
// }
	
$wrapper_attributes = get_block_wrapper_attributes();

printf(
	__( '<div %s>The %s is: %s</div>', 'gatherpress' ),
	$wrapper_attributes,
	$block_title,
	$gatherpress_venue
);
