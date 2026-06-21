const { test, expect } = require( '@playwright/test' );

/**
 * Regression test for #1607: stack overflow when changing the year in the
 * Date & time start picker.
 *
 * Before the fix, pressing Down on the year input of the start datetime
 * picker (in relative-duration mode under an IANA timezone) caused
 * `validateDateTimeEnd` to recursively call `updateDateTimeStart` against
 * the stale store state. The recursion exhausted the call stack inside
 * `moment.tz` and the editor crashed with a "The editor has encountered
 * an unexpected error" overlay.
 *
 * Preconditions required to reproduce the original crash — keep these
 * in place if you ever rework the test setup, or you'll silently stop
 * exercising the regression:
 *  - Event has start + end times that match a preset duration (relative
 *    mode). With end = start + 2h the duration `SelectControl` matches
 *    the "2 hours" preset and `useMatchedDuration` returns 2 — which is
 *    what makes `updateDateTimeStart` enter the relative-mode branch
 *    that triggered the recursion.
 *  - Event timezone is an IANA identifier (e.g. America/New_York). Manual
 *    UTC offsets like `+05:00` do not trigger the bug because they take
 *    a different code path inside `createMomentWithTimezone` that does
 *    not call `moment.tz` (and so never built up the deep stack frames
 *    that overflowed).
 *
 * Why we seed via `wp.apiFetch` from inside the page rather than wp-cli:
 * `npm run wp-env run` spins up a separate Docker compose run that
 * conflicts on port 8888 with the test environment that `pretest:e2e`
 * already started. Going through `wp.apiFetch` reuses the existing
 * authenticated browser context (cookies + WordPress nonce) and writes
 * to the same DB Playwright connects to.
 */
test.describe( '#1607 datetime picker year-down regression', () => {
	test( 'year-down on start picker does not crash the editor', async ( {
		page,
	} ) => {
		// Land on a wp-admin page first so wp.apiFetch is available with
		// the right nonce for authenticated REST writes.
		await page.goto( '/wp-admin/' );
		await page.waitForLoadState( 'load' );

		// Seed an event via the REST API. The `gatherpress_datetime` meta
		// is the authoritative writable field — the individual `_start`,
		// `_end`, and `_timezone` keys are derived from it on save.
		const eventId = await page.evaluate( async () => {
			const res = await window.wp.apiFetch( {
				path: '/wp/v2/gatherpress_events',
				method: 'POST',
				data: {
					title: 'E2E #1607 datetime picker regression',
					status: 'publish',
					meta: {
						gatherpress_datetime: JSON.stringify( {
							dateTimeStart: '2099-04-29 18:00:00',
							dateTimeEnd: '2099-04-29 20:00:00',
							timezone: 'America/New_York',
						} ),
					},
				},
			} );
			return res.id;
		} );

		expect(
			eventId,
			'event creation via REST returned an id'
		).toBeTruthy();

		try {
			await page.goto(
				`/wp-admin/post.php?post=${ eventId }&action=edit`
			);
			await page.waitForLoadState( 'load' );

			// Dismiss any first-run modals (welcome guide, etc).
			await page.waitForTimeout( 500 );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 200 );
			await page.keyboard.press( 'Escape' );

			// Make sure the Event settings panel is open. WP usually opens
			// it by default on first load, but a previous visit could have
			// collapsed it via persisted UI state.
			const eventSettingsButton = page
				.getByRole( 'button', { name: /event settings/i } )
				.first();
			if ( 0 < ( await eventSettingsButton.count() ) ) {
				const expanded =
					await eventSettingsButton.getAttribute(
						'aria-expanded'
					);
				if ( 'true' !== expanded ) {
					await eventSettingsButton.click();
				}
			}

			// Open the start datetime picker. The button id is set in
			// DateTimeStart.js and is stable across @wordpress/components
			// versions.
			const startButton = page.locator( '#gatherpress-datetime-start' );
			await expect( startButton ).toBeVisible( { timeout: 15000 } );
			await expect( startButton ).toContainText( '2099' );
			await startButton.click();

			// Locate the year input inside the picker. WP's DateTimePicker
			// renders an `<input type="number">` with aria-label "Year",
			// which Playwright's spinbutton role matches.
			const yearInput = page.getByRole( 'spinbutton', {
				name: /year/i,
			} );
			await expect( yearInput ).toBeVisible( { timeout: 5000 } );

			// Focus the input and press Down — the exact gesture from
			// #1607.
			await yearInput.click();
			await page.keyboard.press( 'ArrowDown' );

			// Give React a moment to either crash (old behavior) or
			// successfully re-render (new behavior).
			await page.waitForTimeout( 1000 );

			// Editor must NOT have surfaced the React error boundary
			// overlay. This is the assertion that would have failed before
			// the fix.
			await expect(
				page.getByText(
					/editor has encountered an unexpected error/i
				)
			).toHaveCount( 0 );

			// Close the picker so the start label sits in the foreground
			// for the textContent assertion below.
			await page.keyboard.press( 'Escape' );

			// Start label should now reflect the previous year.
			await expect( startButton ).toContainText( '2098' );
		} finally {
			// Always clean up the seed event, even if assertions fail, so
			// repeated local runs don't accumulate orphans in the test DB.
			await page.evaluate( async ( id ) => {
				await window.wp.apiFetch( {
					path: `/wp/v2/gatherpress_events/${ id }?force=true`,
					method: 'DELETE',
				} );
			}, eventId );
		}
	} );
} );
