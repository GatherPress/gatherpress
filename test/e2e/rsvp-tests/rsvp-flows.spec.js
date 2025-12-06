const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );

/**
 * RSVP Flow Tests
 *
 * Tests the core RSVP functionality including:
 * - Open RSVP flow (logged out users)
 * - Logged in user RSVP
 * - Status changes
 * - Anonymous checkbox
 * - Guest count
 *
 * ## Setup
 *
 * These tests require a GatherPress event with an RSVP block.
 * TODO: Automate event creation - currently requires manual setup:
 *
 * 1. Create a new Event in WordPress admin
 * 2. Add the RSVP block to the event
 * 3. Set a future date for the event
 * 4. Publish the event
 * 5. Set EVENT_URL environment variable to the event permalink
 *    Example: EVENT_URL=http://localhost:8889/event/test-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
 *
 * Future improvement: Use WordPress E2E utilities to automate event creation with RSVP block.
 */
test.describe( 'RSVP Flows', () => {
	let eventPermalink;

	test.beforeAll( async () => {
		// Check if EVENT_URL is provided.
		if ( process.env.EVENT_URL ) {
			eventPermalink = process.env.EVENT_URL;
			return;
		}

		// Try to find an existing event with RSVP block.
		try {
			const result = execSync(
				'npm run wp-env run cli -- wp post list --post_type=gatherpress_event --post_status=publish --posts_per_page=1 --field=url',
				{ encoding: 'utf-8' }
			);
			const lines = result.trim().split( '\n' );
			eventPermalink = lines[ lines.length - 1 ].trim();

			// eslint-disable-next-line no-console
			console.log( `Using existing event: ${ eventPermalink }` );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'No event found. Please create an event with an RSVP block or set EVENT_URL environment variable.' );
			throw new Error( 'Test setup failed: No event available for testing' );
		}
	} );

	test.describe( 'Open RSVP Flow (Logged Out Users)', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'should show RSVP modal when clicking RSVP button as logged out user', async ( { page } ) => {
			// Visit event as logged out user.
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Find and click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await expect( rsvpButton ).toBeVisible();
			await rsvpButton.click();

			// Modal should open.
			const modal = page.locator( '.gatherpress-modal--type-rsvp' );
			await expect( modal ).toBeVisible();

			// Should have email field for open RSVP.
			const emailField = modal.locator( 'input[type="email"]' );
			await expect( emailField ).toBeVisible();
		} );

		test( 'should allow RSVP with email for open RSVP', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			// Fill email field.
			const modal = page.locator( '.gatherpress-modal--type-rsvp' );
			const emailField = modal.locator( 'input[type="email"]' );
			await emailField.fill( 'test@example.com' );

			// Submit RSVP.
			const submitButton = modal.locator( 'button:has-text("RSVP")' );
			await submitButton.click();

			// Should show success state.
			await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );
		} );

		test( 'should validate email field in open RSVP', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Try to submit without email.
			const submitButton = modal.locator( 'button:has-text("RSVP")' );
			await submitButton.click();

			// Should show validation error or prevent submission.
			const emailField = modal.locator( 'input[type="email"]' );
			const isInvalid = await emailField.evaluate( ( el ) => ! el.checkValidity() );
			expect( isInvalid ).toBe( true );
		} );
	} );

	test.describe( 'Logged In User RSVP', () => {
		test( 'should allow RSVP without email for logged in users', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );
			await expect( modal ).toBeVisible();

			// Should NOT have email field for logged in users.
			const emailField = modal.locator( 'input[type="email"]' );
			await expect( emailField ).toHaveCount( 0 );

			// Submit RSVP.
			const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Should show success state.
			await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );
		} );

		test( 'should change RSVP status from attending to not attending', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// First, ensure we're attending.
			let rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			let modal = page.locator( '.gatherpress-modal--type-rsvp' );
			let submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Wait for attending status.
			await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );

			// Now change to not attending.
			rsvpButton = page.locator( 'button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Select "Not Attending" radio button.
			const notAttendingRadio = modal.locator( 'input[value="not_attending"]' );
			await notAttendingRadio.click();

			submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Should show not attending status.
			await expect( page.locator( ':has-text("Not Attending")' ) ).toBeVisible( { timeout: 10000 } );
		} );

		test( 'should handle waiting list status', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Select "Waiting List".
			const waitingListRadio = modal.locator( 'input[value="waiting_list"]' );
			await waitingListRadio.click();

			const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Should show waiting list status.
			await expect( page.locator( ':has-text("Waiting")' ) ).toBeVisible( { timeout: 10000 } );
		} );
	} );

	test.describe( 'Anonymous Checkbox', () => {
		test( 'should have anonymous checkbox to hide identity', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Should have anonymous checkbox.
			const anonymousCheckbox = modal.locator( 'input[type="checkbox"][name*="anonymous"]' );
			await expect( anonymousCheckbox ).toBeVisible();
		} );

		test( 'should allow RSVP with anonymous checked', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Check anonymous checkbox.
			const anonymousCheckbox = modal.locator( 'input[type="checkbox"][name*="anonymous"]' );
			await anonymousCheckbox.check();

			// Submit RSVP.
			const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Should show success state.
			await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );
		} );
	} );

	test.describe( 'Guest Count', () => {
		test( 'should allow adding guests to RSVP', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Find guest count field (might be number input or similar).
			const guestField = modal.locator( 'input[type="number"][name*="guest"], input[name*="guests"]' );

			if ( 0 < await guestField.count() ) {
				// Set guest count.
				await guestField.fill( '2' );

				// Submit RSVP.
				const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
				await submitButton.click();

				// Should show success.
				await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );
			} else {
				// Guest field might not be enabled for this event - skip test.
				test.skip();
			}
		} );
	} );

	test.describe( 'Modal Interactions', () => {
		test( 'should close modal when clicking close button', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );
			await expect( modal ).toBeVisible();

			// Click close button.
			const closeButton = modal.locator( 'button.gatherpress-modal--trigger-close' );
			await closeButton.click();

			// Modal should be hidden.
			await expect( modal ).toBeHidden();
		} );

		test( 'should close modal after successful RSVP', async ( { page } ) => {
			await page.goto( eventPermalink );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Submit RSVP.
			const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Modal should close.
			await expect( modal ).toBeHidden( { timeout: 10000 } );
		} );
	} );
} );
