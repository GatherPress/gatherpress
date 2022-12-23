<?php
/**
 * 
 */

$wrapper_attributes = get_block_wrapper_attributes();
$block_name = $block->block_type->title;
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

// <pre>%s</pre>
printf(
	__( '<div %s><p>%s %s</p><p>Number of Events: %s</p><ul>%s</ul><pre>%s</pre><pre>%s</pre></div>', 'gatherpress' ),
	$attributes['className'],
	$block_name,
	esc_html( '– hello from a dynamic block!' ),
	count( $latest_events ),
	$output,
	print_r( $attributes, true ),
	print_r( $block, true )
);

