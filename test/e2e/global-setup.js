const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Global setup for E2E tests
 *
 * Uses WordPress E2E test utils to authenticate via REST API
 * and prepare the test environment.
 *
 * @param {Object} config - Playwright configuration object
 */
module.exports = async ( config ) => {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const storageStatePath =
		'string' === typeof storageState ? storageState : undefined;

	// Create request context for API calls.
	const requestContext = await request.newContext( {
		baseURL: baseURL || 'http://localhost:8889',
	} );

	// Create request utils for WordPress REST API operations.
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
	} );

	try {
		// Authenticate and save the storageState to disk.
		await requestUtils.setupRest();

		// eslint-disable-next-line no-console
		console.log( 'Authentication successful - storage state saved' );
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( 'Global setup failed:', error );
		throw error;
	} finally {
		await requestContext.dispose();
	}
};
