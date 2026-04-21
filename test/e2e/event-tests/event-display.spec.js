const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

/**
 * Basic Event Display Tests
 *
 * Simple tests to verify events display correctly on the frontend.
 */
test.describe( 'Event Display', () => {
	let eventId;

	test.beforeAll( async () => {
		// Create a simple test event via wp-cli.
		const futureDate = new Date();
		futureDate.setDate( futureDate.getDate() + 7 );
		const dateString = futureDate.toISOString();

		// Generate an event post with publish status.
		execSync( 'npm run wp-env run cli -- wp post generate --post_type=gatherpress_event --post_status=publish --count=1 2>&1 | grep -v "Xdebug"' );

		// Get the latest event ID.
		const idResult = execSync(
			'npm run wp-env run cli -- wp post list --post_type=gatherpress_event --posts_per_page=1 --orderby=ID --order=DESC --field=ID 2>&1 | grep -v "Xdebug\\|Ran\\|Starting\\|gatherpress@\\|^>" | grep -v "^$" | tail -1',
			{ encoding: 'utf-8' }
		);
		eventId = idResult.trim();

		// Set event datetime meta.
		execSync( `npm run wp-env run cli -- wp post meta update ${ eventId } gatherpress_datetime_start '${ dateString }' 2>&1 | grep -v "Xdebug"` );
	} );

	test( 'should load event page and display content', async ( { page } ) => {
		// Visit the event page using query string format (works regardless of permalink settings).
		await page.goto( `http://localhost:8889/?p=${ eventId }` );
		await page.waitForLoadState( 'load' );

		// Check that page has a title.
		const title = await page.title();
		expect( title ).toBeTruthy();

		// Verify page has content (not just a blank page).
		const bodyText = await page.locator( 'body' ).textContent();
		expect( bodyText.length ).toBeGreaterThan( 0 );

		// Verify no PHP errors or warnings are visible.
		const hasError = await page.locator( 'body:has-text("Fatal error"), body:has-text("Warning:"), body:has-text("Notice:")' ).count();
		expect( hasError ).toBe( 0 );
	} );
} );
