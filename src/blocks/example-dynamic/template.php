<?php
/**
 * Frontend Render template
 */

$wrapper_attributes = get_block_wrapper_attributes();

printf(
	__( '<div %s><p>The post type is: %s</p><p>%s</p></div>', 'gatherpress' ),
	$wrapper_attributes,
	get_post_type( get_the_ID() ),
	esc_html( 'Example Dynamic – hello from a dynamic block!' )
);
