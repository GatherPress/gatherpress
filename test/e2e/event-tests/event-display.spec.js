const { test, expect } = require( '@playwright/test' );

/**
 * Basic Event Display Tests
 *
 * Simple tests to verify events display correctly on the frontend.
 *
 * Why we seed via `wp.apiFetch` from inside the page rather than wp-cli:
 * `npm run wp-env run` spins up a separate Docker compose run that
 * conflicts on port 8888 with the test environment that `pretest:e2e`
 * already started. Going through `wp.apiFetch` reuses the existing
 * authenticated browser context (cookies + WordPress nonce) and writes
 * to the same DB Playwright connects to.
 */
test.describe( 'Event Display', () => {
	test( 'should load event page and display content', async ( { page } ) => {
		// Land on a wp-admin page first so wp.apiFetch is available with
		// the right nonce for authenticated REST writes.
		await page.goto( '/wp-admin/' );
		await page.waitForLoadState( 'load' );

		// Seed an event via the REST API. 7 days in the future so it's
		// upcoming when the frontend renders it. The `gatherpress_datetime`
		// meta is the authoritative writable field — the individual
		// `_start`, `_end`, and `_timezone` keys are derived from it on
		// save.
		const futureDate = new Date();
		futureDate.setDate( futureDate.getDate() + 7 );
		const startIso = futureDate.toISOString().slice( 0, 19 ).replace( 'T', ' ' );
		const endDate = new Date( futureDate.getTime() + ( 2 * 60 * 60 * 1000 ) );
		const endIso = endDate.toISOString().slice( 0, 19 ).replace( 'T', ' ' );

		const eventTitle = 'E2E Event Display Test';

		const eventId = await page.evaluate(
			async ( { title, dateTimeStart, dateTimeEnd } ) => {
				const res = await window.wp.apiFetch( {
					path: '/wp/v2/gatherpress_events',
					method: 'POST',
					data: {
						title,
						status: 'publish',
						meta: {
							gatherpress_datetime: JSON.stringify( {
								dateTimeStart,
								dateTimeEnd,
								timezone: 'America/New_York',
							} ),
						},
					},
				} );
				return res.id;
			},
			{
				title: eventTitle,
				dateTimeStart: startIso,
				dateTimeEnd: endIso,
			}
		);

		expect(
			eventId,
			'event creation via REST returned an id'
		).toBeTruthy();

		try {
			// Visit the event page using query-string format (works
			// regardless of permalink settings).
			await page.goto( `/?p=${ eventId }` );
			await page.waitForLoadState( 'load' );

			// Check that page has a title.
			const title = await page.title();
			expect( title ).toBeTruthy();

			// Verify the seeded event actually rendered on the frontend
			// (not just any non-empty page like a 404).
			await expect(
				page.locator( 'body' )
			).toContainText( eventTitle );

			// Verify no PHP errors or warnings are visible.
			const hasError = await page
				.locator(
					'body:has-text("Fatal error"), body:has-text("Warning:"), body:has-text("Notice:")'
				)
				.count();
			expect( hasError ).toBe( 0 );
		} finally {
			// Always clean up the seed event, even if assertions fail, so
			// repeated local runs don't accumulate orphans in the test DB.
			await page.goto( '/wp-admin/' );
			await page.evaluate( async ( id ) => {
				await window.wp.apiFetch( {
					path: `/wp/v2/gatherpress_events/${ id }?force=true`,
					method: 'DELETE',
				} );
			}, eventId );
		}
	} );
} );
