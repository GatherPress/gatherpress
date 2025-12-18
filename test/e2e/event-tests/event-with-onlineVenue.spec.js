const { test, expect } = require( '@playwright/test' );
const { createVenue } = require( '../helpers/createOnlineVenue' );
const { createEventWithVenue } = require( '../helpers/createEventWithVenue' );

test( 'Event displays venue on frontend', async ( { page } ) => {
	// Create Venue
	const { venueTitle } = await createVenue( page );

	//Create Event and attach Venue
	const eventUrl = await createEventWithVenue( page, venueTitle );

	//Visit frontend
	await page.goto( eventUrl );
	await page.waitForLoadState( 'load' );
	await page.waitForTimeout( 1000 );

	//Verify Venue title
	await expect(
		page.locator( `text=${ venueTitle }` )
	).toBeVisible();
	await page.waitForTimeout( 1000 );

	//Verify Venue location
	await expect(
		page.getByRole( 'link', { name: 'https://meet.google.com/' } )
	).toBeVisible();

	await page.waitForTimeout( 1000 );

	//Verify no PHP errors
	const hasError = await page.locator(
		'body:has-text("Fatal error"), body:has-text("Warning:"), body:has-text("Notice:")'
	).count();

	expect( hasError ).toBe( 0 );
} );
