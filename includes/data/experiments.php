<?php
/**
 * Static list of GatherPress experiments.
 *
 * Each entry represents an experimental feature tracked via a GitHub Discussion.
 * Keys per item:
 *   - title        (string)  Human-readable experiment name.
 *   - description  (string)  Short description shown on the card.
 *   - discussion   (string)  Full URL of the GitHub Discussion.
 *   - blueprint    (string)  Raw URL to an external blueprint.json file
 *                            (e.g. https://raw.githubusercontent.com/…/blueprint.json).
 *                            The Experiments class builds the full Playground launch URL from this.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

return array(
	// array(
	// 	'title'       => 'Tickets & Paid RSVP',
	// 	'description' => 'Explore a first draft of ticketing support with paid and free tiers for GatherPress events.',
	// 	'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/1',
	// 	'blueprint'   => 'https://playground.wordpress.net/#%7B%22steps%22:%5B%7B%22step%22:%22installPlugin%22,%22pluginData%22:%7B%22resource%22:%22wordpress.org/plugins%22,%22slug%22:%22gatherpress%22%7D%7D%5D%7D',
	// ),
	// array(
	// 	'title'       => 'Venue Hierarchy',
	// 	'description' => 'Nest venues inside parent venues to model multi-room or multi-floor event spaces.',
	// 	'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/2',
	// 	'blueprint'   => 'https://playground.wordpress.net/#%7B%22steps%22:%5B%7B%22step%22:%22installPlugin%22,%22pluginData%22:%7B%22resource%22:%22wordpress.org/plugins%22,%22slug%22:%22gatherpress%22%7D%7D%5D%7D',
	// ),
	// array(
	// 	'title'       => 'Event Series / Seasons',
	// 	'description' => 'Group recurring events into a named series and let attendees subscribe to the whole run.',
	// 	'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/3',
	// 	'blueprint'   => 'https://playground.wordpress.net/#%7B%22steps%22:%5B%7B%22step%22:%22installPlugin%22,%22pluginData%22:%7B%22resource%22:%22wordpress.org/plugins%22,%22slug%22:%22gatherpress%22%7D%7D%5D%7D',
	// ),
	// array(
	// 	'title'       => 'Add-to-Calendar Buttons',
	// 	'description' => 'One-click buttons that let attendees export events to Google, Apple, and Outlook calendars.',
	// 	'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/4',
	// 	'blueprint'   => 'https://playground.wordpress.net/#%7B%22steps%22:%5B%7B%22step%22:%22installPlugin%22,%22pluginData%22:%7B%22resource%22:%22wordpress.org/plugins%22,%22slug%22:%22gatherpress%22%7D%7D%5D%7D',
	// ),
	// array(
	// 	'title'       => 'Attendee Statistics Dashboard',
	// 	'description' => 'A per-event and site-wide dashboard showing RSVP trends, no-show rates, and capacity charts.',
	// 	'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/5',
	// 	'blueprint'   => 'https://playground.wordpress.net/#%7B%22steps%22:%5B%7B%22step%22:%22installPlugin%22,%22pluginData%22:%7B%22resource%22:%22wordpress.org/plugins%22,%22slug%22:%22gatherpress%22%7D%7D%5D%7D',
	// ),
	array(
		'title'       => 'Planning: Venue Block Architecture - Inline Creation, Query Loop Support, & Hybrid Events',
		'description' => '...',
		'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/1349',
		'blueprint'   => 'https://raw.githubusercontent.com/carstingaxion/gatherpress-demos/refs/heads/main/happenings-at-spots/blueprint.json',
	),
	array(
		'title'       => 'Organizers: Use Custom Post Type to be future proof.',
		'description' => '...',
		'discussion'  => 'https://github.com/GatherPress/gatherpress/discussions/1638',
		'blueprint'   => 'https://raw.githubusercontent.com/carstingaxion/gatherpress-demos/refs/heads/main/happenings-at-spots/blueprint.json',
	),
);
