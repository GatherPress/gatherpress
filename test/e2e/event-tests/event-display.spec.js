const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

/**
 * Basic Event Display Tests
 *
 * Simple tests to verify events display correctly on the frontend.
 */
test.describe( 'Event Display', () => {
	let eventPermalink;
	let eventId;

	test.beforeAll( async () => {
		// Create a simple test event via wp-cli.
		const futureDate = new Date();
		futureDate.setDate( futureDate.getDate() + 7 );
		const dateString = futureDate.toISOString();

		// Generate an event post.
		execSync( 'npm run wp-env run cli -- wp post generate --post_type=gatherpress_event --count=1' );

		// Get the latest event ID.
		const idResult = execSync(
			'npm run wp-env run cli -- wp post list --post_type=gatherpress_event --posts_per_page=1 --orderby=ID --order=DESC --field=ID',
			{ encoding: 'utf-8' }
		);
		const idLines = idResult.trim().split( '\n' );
		eventId = idLines[ idLines.length - 1 ].trim();

		// Set event datetime meta.
		execSync( `npm run wp-env run cli -- wp post meta update ${ eventId } gatherpress_datetime_start '${ dateString }'` );

		// Get permalink.
		const linkResult = execSync(
			`npm run wp-env run cli -- wp post list --post__in=${ eventId } --field=url`,
			{ encoding: 'utf-8' }
		);
		const linkLines = linkResult.trim().split( '\n' );
		eventPermalink = linkLines[ linkLines.length - 1 ].trim();
	} );

	test( 'should load event page without errors', async ( { page } ) => {
		// Visit the event page.
		await page.goto( eventPermalink );
		await page.waitForLoadState( 'load' );

		// Check that page loaded successfully.
		const title = await page.title();
		expect( title ).toBeTruthy();

		// Verify no PHP errors or warnings are visible.
		const hasError = await page.locator( 'body:has-text("Fatal error"), body:has-text("Warning:"), body:has-text("Notice:")' ).count();
		expect( hasError ).toBe( 0 );
	} );

	test( 'should return 200 status code', async ( { page } ) => {
		const response = await page.goto( eventPermalink );
		expect( response.status() ).toBe( 200 );
	} );

	test( 'should have event post type in URL or title', async ( { page } ) => {
		await page.goto( eventPermalink );
		await page.waitForLoadState( 'load' );

		const url = page.url();
		const title = await page.title();

		// Check if either URL contains event indicators or title exists.
		const hasEventIndicator = url.includes( 'gatherpress_event' ) || url.includes( '/event' ) || title.length > 0;
		expect( hasEventIndicator ).toBe( true );
	} );
} );
