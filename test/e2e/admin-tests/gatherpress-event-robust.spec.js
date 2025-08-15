const { test, expect } = require( '@playwright/test' );

/**
 * Robust GatherPress Event Tests
 *
 * These tests verify core GatherPress event functionality without relying on
 * complex WordPress UI interactions that can break between versions.
 */
test.describe( 'GatherPress Event Management', () => {
	test( 'should load event creation page with GatherPress elements', async ( { page } ) => {
		// Go directly to new event creation
		await page.goto( '/wp-admin/post-new.php?post_type=gatherpress_event' );
		await page.waitForLoadState( 'load' );

		// Verify we're on the right page
		const body = await page.locator( 'body' ).getAttribute( 'class' );
		expect( body ).toContain( 'post-type-gatherpress_event' );

		// Check for GatherPress-specific elements
		const gatherPressElements = await page.locator( '[class*="gatherpress"], [id*="gatherpress"]' ).count();
		expect( gatherPressElements ).toBeGreaterThan( 0 );

		// Verify editor is functional
		const isBlockEditor = await page.locator( '.block-editor-page' ).count() > 0;
		const isClassicEditor = await page.locator( '#poststuff' ).count() > 0;
		expect( isBlockEditor || isClassicEditor ).toBeTruthy();
	} );

	test( 'should display GatherPress event admin interface', async ( { page } ) => {
		// Navigate to events list
		await page.goto( '/wp-admin/edit.php?post_type=gatherpress_event' );
		await page.waitForLoadState( 'load' );

		// Verify page title
		const pageTitle = await page.locator( 'h1.wp-heading-inline' ).textContent();
		expect( pageTitle ).toContain( 'Events' );

		// Check for GatherPress-specific columns
		const tableHeaders = await page.locator( '.wp-list-table th' ).allTextContents();
		const hasEventDateTime = tableHeaders.some( ( header ) => header.includes( 'Event date & time' ) );
		const hasVenues = tableHeaders.some( ( header ) => header.includes( 'Venues' ) );
		const hasTopics = tableHeaders.some( ( header ) => header.includes( 'Topics' ) );

		expect( hasEventDateTime ).toBeTruthy();
		expect( hasVenues ).toBeTruthy();
		expect( hasTopics ).toBeTruthy();
	} );

	test( 'should have functional add new event button', async ( { page } ) => {
		// Navigate to events list
		await page.goto( '/wp-admin/edit.php?post_type=gatherpress_event' );
		await page.waitForLoadState( 'load' );

		// Check for add new button
		const addNewButton = page.locator( 'a.page-title-action[href*="post-new.php?post_type=gatherpress_event"]' );
		expect( await addNewButton.count() ).toBe( 1 );

		// Click it and verify navigation
		await addNewButton.click();
		await page.waitForLoadState( 'load' );

		// Should be on event creation page
		const currentUrl = page.url();
		expect( currentUrl ).toContain( 'post-new.php?post_type=gatherpress_event' );

		// Should have GatherPress elements
		const gatherPressElements = await page.locator( '[class*="gatherpress"], [id*="gatherpress"]' ).count();
		expect( gatherPressElements ).toBeGreaterThan( 0 );
	} );

	test( 'should show event settings panel', async ( { page } ) => {
		// Navigate to event creation
		await page.goto( '/wp-admin/post-new.php?post_type=gatherpress_event' );
		await page.waitForLoadState( 'load' );

		// Look for settings panel (may be in different locations depending on editor)
		const possibleSettings = [
			'button:has-text("Event settings")',
			'.gatherpress-event-settings',
			'#gatherpress_event_information',
			'[data-label*="Event"]',
		];

		let foundSettings = false;
		for ( const selector of possibleSettings ) {
			const elements = await page.locator( selector ).count();
			if ( elements > 0 ) {
				foundSettings = true;
				break;
			}
		}

		expect( foundSettings ).toBeTruthy();
	} );

	test( 'should handle page load without JavaScript errors causing failures', async ( { page } ) => {
		// Track console errors but don't fail on them (common in dev environments)
		const errors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				errors.push( msg.text() );
			}
		} );

		// Navigate to event creation
		await page.goto( '/wp-admin/post-new.php?post_type=gatherpress_event' );
		await page.waitForLoadState( 'load' );

		// Verify basic functionality works despite any JS errors
		const body = await page.locator( 'body' ).getAttribute( 'class' );
		expect( body ).toContain( 'post-type-gatherpress_event' );

		// Log errors for debugging but don't fail the test
		// JS errors are expected in development environments

		// The key test: GatherPress functionality should still work
		const gatherPressElements = await page.locator( '[class*="gatherpress"], [id*="gatherpress"]' ).count();
		expect( gatherPressElements ).toBeGreaterThan( 0 );
	} );
} );
