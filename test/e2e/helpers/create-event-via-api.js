/**
 * Helper to create a GatherPress event with RSVP block via WordPress REST API.
 *
 * This uses the WordPress REST API to create events programmatically,
 * avoiding issues with wp-cli HTTP accessibility and admin UI modals.
 *
 * @param {import('@playwright/test').APIRequestContext} request - Playwright request context.
 * @param {string} baseURL - WordPress base URL.
 * @return {Promise<string>} The event URL.
 */
async function createEventWithRSVP( request, baseURL = 'http://localhost:8889' ) {
	// Set event datetime (7 days in the future).
	const futureDate = new Date();
	futureDate.setDate( futureDate.getDate() + 7 );
	const startDateTime = futureDate.toISOString();

	const endDate = new Date( futureDate );
	endDate.setHours( endDate.getHours() + 2 );
	const endDateTime = endDate.toISOString();

	// RSVP block content matching real GatherPress events.
	const rsvpBlockContent = `<!-- wp:gatherpress/rsvp -->
<div class="wp-block-gatherpress-rsvp"><!-- wp:gatherpress/rsvp-response /--></div>
<!-- /wp:gatherpress/rsvp -->`;

	// Create event post via REST API.
	const response = await request.post( `${ baseURL }/wp-json/wp/v2/gatherpress_event`, {
		data: {
			title: 'E2E Test Event with RSVP',
			content: rsvpBlockContent,
			status: 'publish',
			meta: {
				gatherpress_datetime_start: startDateTime,
				gatherpress_datetime_end: endDateTime,
				gatherpress_timezone: 'America/New_York',
			},
		},
	} );

	if ( ! response.ok() ) {
		const errorText = await response.text();
		throw new Error( `Failed to create event via REST API: ${ response.status() } ${ errorText }` );
	}

	const data = await response.json();
	const eventUrl = data.link;

	// eslint-disable-next-line no-console
	console.log( `Created event via REST API: ${ eventUrl }` );

	return eventUrl;
}

module.exports = { createEventWithRSVP };
