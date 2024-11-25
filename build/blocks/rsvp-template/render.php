<?php
function render_dynamic_rsvp_template_block($attributes, $content) {
	// Example dynamic RSVP data
	$rsvps = [
		['name' => 'John Doe', 'avatar' => 'https://example.com/avatar1.jpg'],
		['name' => 'Jane Smith', 'avatar' => 'https://example.com/avatar2.jpg'],
		['name' => 'Chris Lee', 'avatar' => 'https://example.com/avatar3.jpg'],
	];

	// Limit RSVPs based on block attributes
	$rsvps = array_slice($rsvps, 0, $attributes['queryLimit']);

	// Render RSVP entries by replacing placeholders
	$output = '<div class="rsvp-list">';
	foreach ($rsvps as $rsvp) {
		$entry = $content;
		$entry = str_replace('{{name}}', esc_html($rsvp['name']), $entry);
		$entry = str_replace('{{avatar}}', esc_url($rsvp['avatar']), $entry);
		$output .= $entry;
	}
	$output .= '</div>';

	return $output;
}

register_block_type(__DIR__, [
	'render_callback' => 'render_dynamic_rsvp_template_block',
]);
