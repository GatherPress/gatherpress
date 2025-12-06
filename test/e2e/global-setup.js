const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

/**
 * Global setup for E2E tests
 *
 * Creates authenticated browser state for use across all tests
 */
module.exports = async () => {
	// Create WordPress auth state.
	const browser = await chromium.launch( { headless: true } );
	const page = await browser.newPage();

	// Use environment variable for base URL, fallback to localhost
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';

	try {
		// Navigate to login page with retry for CI environments.
		await page.goto( `${ baseUrl }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '#user_login', { timeout: 30000 } );

		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );

		// Click login and wait for navigation to complete.
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 30000 } ),
			page.click( '#wp-submit' ),
		] );

		// Verify we're actually logged in by waiting for admin elements.
		try {
			await page.waitForSelector( '#wpadminbar, #adminmenu', { timeout: 10000 } );
		} catch ( error ) {
			// Take screenshot for debugging.
			await page.screenshot( { path: 'artifacts/login-failed.png', fullPage: true } );
			throw new Error( 'Login failed - admin elements not found after login attempt' );
		}

		// Ensure GatherPress plugin is activated.
		await page.goto( `${ baseUrl }/wp-admin/plugins.php` );
		await page.waitForLoadState( 'networkidle' );

		const gatherPressPlugin = page.locator( 'tr[data-slug="gatherpress"]' );
		const pluginExists = await gatherPressPlugin.count() > 0;

		if ( pluginExists ) {
			// Check if plugin needs activation.
			const needsActivation = await gatherPressPlugin.locator( '.activate' ).count() > 0;

			if ( needsActivation ) {
				// Activate the plugin.
				await gatherPressPlugin.locator( '.activate a' ).click();
				await page.waitForLoadState( 'networkidle' );
			}
		}

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
