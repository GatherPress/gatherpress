<?php

$wrapper_attributes = get_block_wrapper_attributes();
$latest_events = get_posts(
	array(
		'post_type'  => 'gp_event',
		'posts_per_page' => $attributes['maxNumberOfEvents'],
	)
);
if ( $latest_events ) {
	$output = '';
	foreach ( $latest_events as $post ) {
		$output .= '<li><a href="' . get_the_permalink( $post->ID ) . '" target="_blank">' . $post->post_title . '</a></li>';
	}
}

printf(
	__( '<div %s><p>%s</p><p>Number of Events: %s</p><ul>%s</ul></div>', 'gatherpress' ),
	$attributes['className'],
	esc_html( 'Example Dynamic – hello from a dynamic block!' ),
	count( $latest_events ),
	$output
	// print_r( $attributes, true )
);

