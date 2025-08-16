const { test, expect } = require( '@playwright/test' );

/**
 * Basic GatherPress Tests
 *
 * Essential tests to verify GatherPress is working without complex UI interactions.
 */
test.describe( 'GatherPress Basic Functionality', () => {
	test( 'should access events list page', async ( { page } ) => {
		// Navigate to events list - this should work if post type is registered
		await page.goto( '/wp-admin/edit.php?post_type=gatherpress_event' );

		// Use load instead of networkidle to avoid timeout issues
		await page.waitForLoadState( 'load' );

		// Verify we can access the page without post type errors
		const hasError = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist")' ).count();
		expect( hasError ).toBe( 0 );
	} );

	test( 'should access venues list page', async ( { page } ) => {
		// Navigate to venues list
		await page.goto( '/wp-admin/edit.php?post_type=gatherpress_venue' );
		await page.waitForLoadState( 'load' );

		// Verify we can access the page without post type errors
		const hasError = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist")' ).count();
		expect( hasError ).toBe( 0 );
	} );

	test( 'should find GatherPress plugin in admin', async ( { page } ) => {
		// Navigate to plugins page
		await page.goto( '/wp-admin/plugins.php' );
		await page.waitForLoadState( 'load' );

		// Look for GatherPress plugin
		const gatherPressPlugin = await page.locator( 'tr:has-text("GatherPress"), tr:has-text("gatherpress")' ).count();
		expect( gatherPressPlugin ).toBeGreaterThan( 0 );
	} );

	test( 'should have GatherPress menu items', async ( { page } ) => {
		// Go to admin dashboard
		await page.goto( '/wp-admin/' );
		await page.waitForLoadState( 'load' );

		// Look for GatherPress-related links
		const gatherPressLinks = await page.locator( 'a[href*="gatherpress"], a[href*="edit.php?post_type=gatherpress_event"]' ).count();
		expect( gatherPressLinks ).toBeGreaterThan( 0 );
	} );
} );
