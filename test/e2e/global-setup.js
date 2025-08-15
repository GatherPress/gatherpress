const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Global setup for E2E tests
 *
 * Creates authenticated browser state for use across all tests
 */
module.exports = async () => {
	// Create WordPress auth state
	const browser = await chromium.launch();
	const page = await browser.newPage();
	
	// Use environment variable for base URL, fallback to localhost
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';

	try {
		// Navigate to login page with retry for CI environments
		await page.goto( `${ baseUrl }/wp-login.php`, { waitUntil: 'networkidle' } );
		await page.waitForSelector( '#user_login', { timeout: 30000 } );

		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );

		// Wait for successful login - try multiple indicators
		await Promise.race( [
			page.waitForSelector( '#wpadminbar', { timeout: 15000 } ),
			page.waitForSelector( '.wp-admin', { timeout: 15000 } ),
			page.waitForURL( `${ baseUrl }/wp-admin/**`, { timeout: 15000 } ),
		] );

		// Verify we're actually logged in by checking the dashboard
		await page.goto( `${ baseUrl }/wp-admin/` );
		await page.waitForLoadState( 'networkidle' );

		// Simple plugin verification - just check if post type exists
		await verifyPluginWorking( page );

		const storageStatePath = path.join( __dirname, 'storageState.json' );
		await page.context().storageState( { path: storageStatePath } );
	} catch ( error ) {
		// Take screenshot for debugging
		try {
			await page.screenshot( { path: 'artifacts/global-setup-failed.png', fullPage: true } );
		} catch ( screenshotError ) {
			// Ignore screenshot errors
		}

		throw error;
	} finally {
		await browser.close();
	}
};

async function verifyPluginWorking( page ) {
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';
	
	try {
		// Simple test: try to access GatherPress event post type
		// Since WP-CLI shows plugin is active, this should work
		await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
		await page.waitForLoadState( 'networkidle' );
		
		// Just wait a bit for page to stabilize
		await page.waitForTimeout( 2000 );
		
		// Check if we have an error indicating post type doesn't exist
		const hasPostTypeError = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist")' ).count() > 0;
		
		if ( hasPostTypeError ) {
			throw new Error( 'GatherPress event post type not available' );
		}
		
		// Plugin is working if we got here without errors
		
	} catch ( error ) {
		// Take screenshot for debugging
		try {
			await page.screenshot( { path: 'artifacts/plugin-verification-failed.png', fullPage: true } );
		} catch ( screenshotError ) {
			// Ignore screenshot errors
		}
		
		throw error;
	}
}