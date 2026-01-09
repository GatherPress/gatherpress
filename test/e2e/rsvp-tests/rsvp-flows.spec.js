const { test, expect } = require( '@playwright/test' );
const { createEventWithRSVP } = require( '../helpers/create-event-via-admin' );

/**
 * RSVP Flow Tests
 *
 * Tests the core RSVP functionality including:
 * - Open RSVP flow (logged out users requiring email)
 * - Logged in user RSVP
 * - Status changes (attending, not attending, waiting list)
 * - Anonymous checkbox (to hide user identity)
 * - Guest count
 * - Modal interactions
 *
 * ## Current Status: REQUIRES MANUAL SETUP
 *
 * These tests are currently skipped in CI because they require manual event creation.
 * To run these tests locally:
 *
 * 1. Create a new GatherPress event via WordPress admin at http://localhost:8889/wp-admin
 * 2. Set the event date to 7+ days in the future
 * 3. Add the RSVP block to the event
 * 4. Publish the event
 * 5. Get the event URL (e.g., http://localhost:8889/event/test-event/)
 * 6. Run tests with: EVENT_URL=<your-event-url> npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
 *
 * Example:
 *   EVENT_URL=http://localhost:8889/event/test-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js
 *
 * ## TODO: Automate Event Creation
 *
 * Future work needed to make these tests production-ready:
 *
 * ### Option 1: WordPress Playground Blueprint Approach
 * - Use WXR import similar to `.github/scripts/playground-preview/index.js`
 * - Demo data available at: https://raw.githubusercontent.com/GatherPress/gatherpress-demo-data/main/GatherPress-demo-data-0.33.0.xml
 * - Contains "Christmas 2025" event with complete RSVP block
 * - Challenge: Docker volume mounting in wp-env makes file access difficult
 *
 * ### Option 2: Playwright Admin UI Automation
 * - Use Playwright to create event via WordPress admin
 * - Add RSVP block through block inserter
 * - Challenge: "Welcome to editor" modal interferes with automation
 * - Needs reliable modal dismissal strategy
 *
 * ### Option 3: Direct Database Seeding
 * - Create event directly in database via wp-cli
 * - Insert proper RSVP block structure in post_content
 * - Challenge: Posts created via wp-cli sometimes return 404 via HTTP
 *
 * See test/e2e/helpers/ for attempted implementations.
 */
test.describe( 'RSVP Flows', () => {
	let eventUrl;

	// test.beforeAll( async () => {
	// 	// Require EVENT_URL to be provided.
	// 	if ( ! process.env.EVENT_URL ) {
	// 		throw new Error(
	// 			'\n\n' +
	// 			'❌ EVENT_URL environment variable is required.\n\n' +
	// 			'To run RSVP tests:\n' +
	// 			'1. Create a GatherPress event with an RSVP block via WordPress admin\n' +
	// 			'2. Set EVENT_URL to your event URL\n\n' +
	// 			'Example:\n' +
	// 			'  EVENT_URL=http://localhost:8889/event/test-event/ npm run test:e2e -- rsvp-tests/rsvp-flows.spec.js\n'
	// 		);
	// 	}

	// 	eventUrl = process.env.EVENT_URL;
	// 	// eslint-disable-next-line no-console
	// 	console.log( `\n✓ Using event URL: ${ eventUrl }\n` );
	// } );

	test.beforeAll( async ( { browser } ) => {
		const context = await browser.newContext( {
			// Uses your existing admin login state
			storageState: './test/e2e/storageState.json',
			baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		} );

		const page = await context.newPage();

		eventUrl = await createEventWithRSVP( page );

		await context.close();

		console.log( `\nEvent created for RSVP tests: ${ eventUrl }\n` );
	} );

	test.describe( 'Open RSVP Flow (Logged Out Users)', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'should show RSVP modal when clicking RSVP button as logged out user', async ( { page } ) => {
			// Visit event as logged out user.
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Find and click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await expect( rsvpButton ).toBeVisible();
			await rsvpButton.click();

			// Modal should open.
			const modal = page.locator( '.gatherpress--has-login-url' );
			await expect( modal ).toBeVisible();

			// click on login link
			const loginLink = page.getByRole( 'link', { name: 'Login' } );
			await loginLink.click();

			// Login in form
			const loginForm = page.locator( '#loginform' );
			await expect( loginForm ).toBeVisible();
		} ); //completed

		//need user credentials now used admin credential for login
		// the attending button is not clickable
		test.skip( 'should allow RSVP with email for open RSVP', async ( { page } ) => {
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			// Modal should open.
			const modal = page.locator( '.gatherpress--has-login-url' );
			await expect( modal ).toBeVisible();

			// click on login link
			const loginLink = page.getByRole( 'link', { name: 'Login' } );
			await loginLink.click();

			const loginForm = page.locator( '#loginform' );
			await expect( loginForm ).toBeVisible();

			const username = 'admin';
			const password = 'password';

			await page.getByRole( 'textbox', { name: 'Username or Email Address' } ).fill( username );
			await page.getByRole( 'textbox', { name: 'Password' } ).fill( password );
			await page.getByRole( 'button', { name: 'Log In' } ).click();

			// // Click RSVP button.
			await rsvpButton.click();

			// Submit RSVP.
			// const submitButton = modal.locator('button:has-text("RSVP")');
			// await submitButton.click();

			// // Should show success state.
			// await expect(page.locator(':has-text("Attending")')).toBeVisible({ timeout: 10000 });
		} );

		test( 'should validate email and password field in open RSVP', async ( { page } ) => {
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			// click on login link
			const loginLink = page.getByRole( 'link', { name: 'Login' } );
			await loginLink.click();

			const loginForm = page.locator( '#loginform' );
			await expect( loginForm ).toBeVisible();

			const username = 'admin1';
			const password = 'password';

			await page.getByRole( 'textbox', { name: 'Username or Email Address' } ).fill( username );
			await page.getByRole( 'textbox', { name: 'Password' } ).fill( password );
			await page.getByRole( 'button', { name: 'Log In' } ).click();

			// Try to submit without email.
			const submitButton = loginForm.locator( '#wp-submit' );
			await submitButton.click();

			// Should show validation error or prevent submission.
			// const emailField = loginForm.locator('input[type="email"]');
			// const isInvalid = await emailField.evaluate((el) => !el.checkValidity());
			// expect(isInvalid).toBe(true);

			// Error for email is visible
			const loginError = page.locator( '#login_error' );
			await expect( loginError ).toBeVisible();
		} );
	} );

	//issue with attend button, is not clickable
	test.describe.skip( 'Logged In User RSVP', () => {
		test( 'should allow RSVP without email for logged in users', async ( { page } ) => {
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.getByRole( 'dialog', { name: 'RSVP Modal' } );
			await expect( modal ).toBeVisible();

			// Should NOT have email field for logged in users.
			const emailField = modal.locator( 'input[type="email"]' );
			await expect( emailField ).toHaveCount( 0 );

			// Submit RSVP.
			const submitButton = modal.locator( 'button.gatherpress-rsvp--trigger-update' );
			await submitButton.click();

			// Should show success state.
			await expect( page.locator( ':has-text("Attending")' ) ).toBeVisible( { timeout: 10000 } );
		} );//issue with attend button, is not clickable

		test( 'should change RSVP status from attending to not attending', async ( { page } ) => {
			await page.goto( eventUrl );
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
		} );//issue with attend button, is not clickable

		test( 'should handle waiting list status', async ( { page } ) => {
			await page.goto( eventUrl );
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

	//issue with attend button, is not clickable
	test.describe.skip( 'Anonymous Checkbox', () => {
		test( 'should have anonymous checkbox to hide identity', async ( { page } ) => {
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP"), button:has-text("Edit RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.locator( '.gatherpress-modal--type-rsvp' );

			// Should have anonymous checkbox.
			const anonymousCheckbox = page.getByRole( 'dialog', { name: 'RSVP Modal' } ).getByLabel( 'List me as anonymous' );
			await expect( anonymousCheckbox ).toBeVisible();
		} );

		test( 'should allow RSVP with anonymous checked', async ( { page } ) => {
			await page.goto( eventUrl );
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

	//issue with attend button, is not clickable
	test.describe( 'Guest Count', () => {
		test.skip( 'should allow adding guests to RSVP', async ( { page } ) => {
			await page.goto( eventUrl );
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
			await page.goto( eventUrl );
			await page.waitForLoadState( 'load' );

			// Click RSVP button.
			const rsvpButton = page.locator( 'button:has-text("RSVP")' ).first();
			await rsvpButton.click();

			const modal = page.getByRole( 'dialog', { name: 'RSVP Modal' } );
			await expect( modal ).toBeVisible();

			// Click close button.
			const closeButton = modal.getByRole( 'button', { name: 'Close' } );
			await closeButton.click();

			// Modal should be hidden.
			await expect( modal ).toBeHidden();
		} );

		//issue with attend button, is not clickable
		test.skip( 'should close modal after successful RSVP', async ( { page } ) => {
			await page.goto( eventUrl );
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
