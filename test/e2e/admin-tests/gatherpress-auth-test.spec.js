const { test, expect } = require( '@playwright/test' );

/**
 * Simple authentication test to verify basic login works
 */
test.describe( 'Authentication Test', () => {
	test( 'should be logged in and access WordPress admin', async ( { page } ) => {
		// Go to admin dashboard
		await page.goto( '/wp-admin/' );

		// Should NOT be redirected to login page
		const currentUrl = page.url();
		expect( currentUrl ).not.toContain( 'wp-login.php' );

		// Should have admin elements
		const adminElements = await Promise.all( [
			page.locator( '#wpadminbar' ).count(),
			page.locator( '#wpbody' ).count(),
			page.locator( '.wp-admin' ).count(),
		] );

		// At least one admin indicator should be present
		const hasAdminElement = adminElements.some( ( count ) => 0 < count );
		expect( hasAdminElement ).toBe( true );

		// Check for admin menu
		const adminMenu = page.locator( '#adminmenu' );
		await expect( adminMenu ).toBeVisible();

		// Take screenshot for verification
		await page.screenshot( { path: 'artifacts/auth-verification.png' } );
	} );

	test( 'should access GatherPress admin pages', async ( { page } ) => {
		// Go to admin dashboard first
		await page.goto( '/wp-admin/' );

		// Look for GatherPress in the admin menu
		const gatherPressMenu = page.locator( '#menu-posts-gatherpress_event, a:has-text("Events")' );

		if ( 0 < await gatherPressMenu.count() ) {
			// Try to access events page
			await page.goto( '/wp-admin/edit.php?post_type=gatherpress_event' );

			// Should load without error
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );

			// Should not show "post type does not exist" error
			const hasError = 0 < await page.locator( ':has-text("post type does not exist")' ).count();
			expect( hasError ).toBe( false );
		} else {
			// Check plugins page to see if GatherPress is there
			await page.goto( '/wp-admin/plugins.php' );
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );

			const gatherPressPlugin = page.locator( 'tr[data-slug="gatherpress"]' );
			const pluginExists = 0 < await gatherPressPlugin.count();

			if ( pluginExists ) {
				const isActive = 0 < await gatherPressPlugin.locator( '.deactivate' ).count();
				expect( isActive ).toBeTruthy();
			} else {
			}
		}

		// Take screenshot for debugging
		await page.screenshot( { path: 'artifacts/gatherpress-admin-check.png' } );
	} );
} );
