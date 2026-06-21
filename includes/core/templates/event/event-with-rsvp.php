<?php
/**
 * "Event with RSVP" starter pattern.
 *
 * Seeds the canonical event layout when chosen from the block editor's
 * starter pattern modal: event-date + add-to-calendar + venue +
 * online-event + rsvp + description paragraph + rsvp-response.
 *
 * Wrapper-`<div>` blocks (add-to-calendar, online-event, rsvp,
 * rsvp-response) include the empty wrapper in their serialized markup
 * so the parser's view matches the empty-state output of each block's
 * `save()` — without it, the editor flags "unexpected or invalid
 * content" on insert. Each block's edit component then seeds its own
 * inner-block template at runtime.
 *
 * Venue / RSVP / RSVP Response carry `patternPicked: true` so their
 * in-block pattern pickers stay suppressed — those layouts are seeded
 * here as the canonical default, not a fresh manual insert.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_paragraph_placeholder = wp_json_encode(
	__(
		// phpcs:ignore Generic.Files.LineLength.TooLong -- Single translator-facing sentence; keep on one line for the .pot extractor.
		'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.',
		'gatherpress'
	)
);

return array(
	'name'        => 'gatherpress/event-with-rsvp',
	'title'       => __( 'Event with RSVP', 'gatherpress' ),
	'description' => __(
		'Date, calendar link, venue, online link, RSVP, description, and attendee list.',
		'gatherpress'
	),
	'content'     => <<<HTML
<!-- wp:gatherpress/event-date /-->
<!-- wp:gatherpress/add-to-calendar -->
<div class="wp-block-gatherpress-add-to-calendar"></div>
<!-- /wp:gatherpress/add-to-calendar -->
<!-- wp:gatherpress/venue {"patternPicked":true} /-->
<!-- wp:gatherpress/online-event -->
<div class="wp-block-gatherpress-online-event"></div>
<!-- /wp:gatherpress/online-event -->
<!-- wp:gatherpress/rsvp {"patternPicked":true} -->
<div class="wp-block-gatherpress-rsvp"></div>
<!-- /wp:gatherpress/rsvp -->
<!-- wp:paragraph {"placeholder":$gatherpress_paragraph_placeholder} -->
<p></p>
<!-- /wp:paragraph -->
<!-- wp:gatherpress/rsvp-response {"patternPicked":true} -->
<div class="wp-block-gatherpress-rsvp-response"></div>
<!-- /wp:gatherpress/rsvp-response -->
HTML
	,
);
