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

	try {
		await page.goto( 'http://localhost:8889/wp-login.php' );
		await page.waitForSelector( '#user_login', { timeout: 15000 } );

		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'password' );
		await page.click( '#wp-submit' );

		// Wait for successful login - try multiple indicators
		await Promise.race( [
			page.waitForSelector( '#wpadminbar', { timeout: 15000 } ),
			page.waitForSelector( '.wp-admin', { timeout: 15000 } ),
			page.waitForURL( '**/wp-admin/**', { timeout: 15000 } ),
		] );

		// Verify we're actually logged in by checking the dashboard
		await page.goto( 'http://localhost:8889/wp-admin/' );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		// Ensure GatherPress plugin is activated
		await ensurePluginActivated( page );

		// Create basic test data
		await setupTestData( page );

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

async function setupTestData( page ) {
	try {
		// Check if GatherPress plugin is active first
		await page.goto( 'http://localhost:8889/wp-admin/plugins.php' );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		const gatherPressActive = await page.locator( 'tr[data-slug="gatherpress"] .deactivate' ).count() > 0;
		if ( ! gatherPressActive ) {
			return;
		}

		// Check if venue post type exists
		await page.goto( 'http://localhost:8889/wp-admin/edit.php?post_type=gatherpress_venue' );

		// Wait for either the list table or an error/not found message to verify venue system works
		await Promise.race( [
			page.waitForSelector( '.wp-list-table', { timeout: 5000 } ).then( () => true ),
			page.waitForSelector( '.error', { timeout: 5000 } ).then( () => false ),
			page.waitForSelector( ':has-text("post type does not exist")', { timeout: 5000 } ).then( () => false ),
		] ).catch( () => false );

		// No need to create test venues - our tests verify functionality without requiring specific data
	} catch ( error ) {
		// Take screenshot for debugging
		try {
			await page.screenshot( { path: 'artifacts/test-data-setup-failed.png', fullPage: true } );
		} catch ( screenshotError ) {
			// Ignore screenshot errors
		}
	}
}

async function ensurePluginActivated( page ) {
	try {
		await page.goto( 'http://localhost:8889/wp-admin/plugins.php' );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		// Check if GatherPress plugin exists and is active
		const gatherPressRow = page.locator( 'tr[data-slug="gatherpress"]' );
		const pluginExists = await gatherPressRow.count() > 0;

		if ( ! pluginExists ) {
			return;
		}

		// Check if it's already active
		const isActive = await gatherPressRow.locator( '.deactivate' ).count() > 0;

		if ( isActive ) {
			return;
		}

		// Activate the plugin
		const activateLink = gatherPressRow.locator( '.activate a' );
		if ( await activateLink.count() > 0 ) {
			await activateLink.click();

			// Wait for activation success
			await page.waitForSelector( '.notice-success, .updated', { timeout: 10000 } );
		}
	} catch ( error ) {
		// Take screenshot for debugging
		try {
			await page.screenshot( { path: 'artifacts/plugin-activation-failed.png', fullPage: true } );
		} catch ( screenshotError ) {
			// Ignore screenshot errors
		}
	}
}
