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
		baseURL,
	} );

	// Create request utils for WordPress REST API operations.
	//
	// `baseURL` has to be passed here as well as to the request context above.
	// `RequestUtils` keeps its own copy and defaults it to the package's
	// `WP_BASE_URL`, which is `http://localhost:8889` unless that variable is
	// set, and it builds its REST calls from that copy rather than from the
	// context it was handed. Leaving it out therefore aims authentication at
	// port 8889 no matter which port the suite resolved.
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath,
		baseURL,
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
