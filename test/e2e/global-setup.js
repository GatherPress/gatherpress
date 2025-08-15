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
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		// Ensure GatherPress plugin is activated
		await ensurePluginActivated( page );

		// Verify plugin is working by checking post types
		await verifyPluginFunctionality( page );

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
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';

	try {
		// Check if GatherPress plugin is active first
		await page.goto( `${ baseUrl }/wp-admin/plugins.php` );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		const gatherPressActive = await page.locator( 'tr[data-slug="gatherpress"] .deactivate' ).count() > 0;
		if ( ! gatherPressActive ) {
			return;
		}

		// Check if venue post type exists
		await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_venue` );

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
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';

	try {
		await page.goto( `${ baseUrl }/wp-admin/plugins.php` );
		await page.waitForSelector( '#wpbody', { timeout: 15000 } );
		await page.waitForTimeout( 2000 ); // Give CI time to load plugins

		// Check for GatherPress plugin - try multiple patterns since CI might differ
		const gatherPressSelectors = [
			'tr[data-slug="gatherpress"]',
			'tr:has-text("GatherPress")',
			'tr:has-text("gatherpress")',
		];

		let gatherPressRow = null;
		for ( const selector of gatherPressSelectors ) {
			const element = page.locator( selector );
			if ( await element.count() > 0 ) {
				gatherPressRow = element.first();
				break;
			}
		}

		if ( ! gatherPressRow ) {
			// Plugin not found - might be auto-activated in CI or missing
			// Check if GatherPress functionality exists by trying to access post type
			await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );

			// If we can access the post type, plugin is active
			const hasEventPostType = ! await page.locator( ':has-text("Invalid post type")' ).count();
			if ( hasEventPostType ) {
				return; // Plugin is working
			}

			throw new Error( 'GatherPress plugin not found in plugins list and post type not available' );
		}

		// Check if it's already active
		const isActive = await gatherPressRow.locator( '.deactivate' ).count() > 0;

		if ( isActive ) {
			// Double-check by testing post type access
			await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );
			return;
		}

		// Try to activate the plugin
		const activateLink = gatherPressRow.locator( '.activate a, a:has-text("Activate")' );
		if ( await activateLink.count() > 0 ) {
			await activateLink.click();

			// Wait for activation success with longer timeout for CI
			await page.waitForSelector( '.notice-success, .updated, .notice-info', { timeout: 15000 } );

			// Verify activation worked by testing post type
			await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );
		} else {
			throw new Error( 'GatherPress plugin found but no activate link available' );
		}
	} catch ( error ) {
		// Take screenshot for debugging
		try {
			await page.screenshot( { path: 'artifacts/plugin-activation-failed.png', fullPage: true } );
		} catch ( screenshotError ) {
			// Ignore screenshot errors
		}

		// In CI, plugin might be automatically active - check post type availability
		try {
			await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
			await page.waitForSelector( '#wpbody', { timeout: 10000 } );

			const hasError = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist")' ).count() > 0;
			if ( ! hasError ) {
				return; // Plugin is working even if we couldn't activate it manually
			}
		} catch {
			// Final fallback failed
		}

		throw error;
	}
}

async function verifyPluginFunctionality( page ) {
	const baseUrl = process.env.WP_BASE_URL || 'http://localhost:8889';

	try {
		// Test that GatherPress post types are available
		await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_event` );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		// Check for error messages that indicate post type doesn't exist
		const hasErrors = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist"), .error' ).count() > 0;

		if ( hasErrors ) {
			throw new Error( 'GatherPress event post type not available - plugin may not be activated' );
		}

		// Test venue post type as well
		await page.goto( `${ baseUrl }/wp-admin/edit.php?post_type=gatherpress_venue` );
		await page.waitForSelector( '#wpbody', { timeout: 10000 } );

		const hasVenueErrors = await page.locator( ':has-text("Invalid post type"), :has-text("post type does not exist"), .error' ).count() > 0;

		if ( hasVenueErrors ) {
			throw new Error( 'GatherPress venue post type not available - plugin may not be activated' );
		}
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
