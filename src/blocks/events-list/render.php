<?php
/**
 * Render has $attributus, $block, $content
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
		$output .= '<li><p><a href="' . get_the_permalink( $post->ID ) . '" target="_blank">' . $post->post_title . '</a></p><p>' . $post->post_content . '</p></li>';
	}
}

$wrapper_attributes = get_block_wrapper_attributes();

if ( $attributes['className'] ) {
	$wrapper_attributes = 'class="' . $attributes['className'] . '"';
}

// <pre>%s</pre>
printf(
	__( '<div %s><p>%s %s</p><p>Number of Events: %s</p><ul>%s</ul></div>', 'gatherpress' ),
	$wrapper_attributes,
	$block_name,
	esc_html( '– hello from a dynamic block!' ),
	count( $latest_events ),
	$output
);

