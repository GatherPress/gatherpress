<?php
/**
 * Frontend Render template
 */

printf(
	__( '<div %s><p>The post type is: %s</p><p>%s</p></div>', 'gatherpress' ),
	$attributes['className'],
	get_post_type( get_the_ID() ),
	esc_html( 'Example Dynamic – hello from a dynamic block!' )
);
