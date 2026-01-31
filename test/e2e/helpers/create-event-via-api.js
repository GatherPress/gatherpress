/**
 * Helper to create a GatherPress event with RSVP block via WordPress REST API.
 *
 * Uses the WordPress REST API directly.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @return {Promise<string>} The created event URL
 */
async function createEventWithAPI( request ) {
	const futureDate = new Date();
	futureDate.setDate( futureDate.getDate() + 7 );

	const endDate = new Date( futureDate );
	endDate.setHours( endDate.getHours() + 2 );

	const rsvpBlockContent = `<!-- wp:gatherpress/rsvp -->
<div class="wp-block-gatherpress-rsvp"><!-- wp:gatherpress/rsvp-response /--></div>
<!-- /wp:gatherpress/rsvp -->`;

	const response = await request.post(
		'http://localhost:8889/index.php?rest_route=/wp/v2/gatherpress_events',
		{
			data: {
				title: 'E2E Test Event with RSVP',
				content: rsvpBlockContent,
				status: 'publish',
				meta: {
					gatherpress_datetime_start: futureDate.toISOString(),
					gatherpress_datetime_end: endDate.toISOString(),
					gatherpress_timezone: 'America/New_York',
				},
			},
		}
	);

	if ( ! response.ok() ) {
		throw new Error(
			`Failed to create GatherPress event (${ response.status() }): ${ await response.text() }`
		);
	}

	const data = await response.json();
	return data.link;
}

module.exports = { createEventWithAPI };

