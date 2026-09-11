// playwright.config.js
const { defineConfig, devices } = require( '@playwright/test' );
const { resolveBaseUrl } = require( './test/e2e/resolve-wp-env-port' );
require( 'dotenv' ).config();

const baseURL = resolveBaseUrl();

// `@wordpress/e2e-test-utils-playwright` reads `WP_BASE_URL` once, at module
// load, into the constant its REST discovery uses:
// `RequestUtils.setupRest()` calls `getAPIRootURL()`, which issues its `HEAD`
// against that constant rather than against the request context's `baseURL` or
// the instance's own. Passing `baseURL` to either therefore cannot redirect
// discovery, and on a checkout that is not on the package's default port
// authentication fails before a single spec runs.
//
// Exporting the resolved value here is what reaches it: this config is
// evaluated before `global-setup.js` pulls the package in. An explicit
// `WP_BASE_URL` still wins, so pointing the suite somewhere else keeps
// working.
process.env.WP_BASE_URL = process.env.WP_BASE_URL || baseURL;

module.exports = defineConfig( {
	testDir: './test/e2e',
	fullyParallel: false, // Disable parallel for better stability
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 1,
	workers: 1, // Use single worker to avoid conflicts
	timeout: 180000,
	expect: {
		timeout: 10000, // Shorter expect timeout for faster feedback
	},
	reporter: [
		[ 'html' ],
		[ 'list', { printSteps: true } ],
		[ 'junit', { outputFile: 'test-results/junit.xml' } ],
	],
	globalSetup: './test/e2e/global-setup.js',
	use: {
		baseURL,
		trace: 'on-first-retry',
		video: 'on-first-retry',
		screenshot: 'only-on-failure',
		storageState: './test/e2e/storageState.json',
		actionTimeout: 15000, // Longer action timeout for slow WordPress admin
		navigationTimeout: 30000, // Longer navigation timeout
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices[ 'Desktop Chrome' ],
				// In CI, run against the Google Chrome preinstalled on the
				// GitHub runner instead of Playwright's downloaded Chromium —
				// the `playwright install chromium` download hangs after
				// completing on the runners (observed 2026-05-30). Local runs
				// keep using the downloaded Chromium so no system Chrome is
				// required for development.
				...( process.env.CI ? { channel: 'chrome' } : {} ),
				// Repeated from the top-level `use` for the same reason
				// `storageState` is: `global-setup.js` reads
				// `config.projects[ 0 ].use`, and Playwright does not merge the
				// top-level `use` into that object. Without this the resolved
				// port never reaches global setup, which then falls back to
				// `@wordpress/e2e-test-utils-playwright`'s own
				// `localhost:8889` default and authenticates against whatever
				// happens to be on that port. On a checkout running at 8889
				// that is invisible; on any other it is the silent
				// port-disagreement `resolveWpEnvPort()` exists to prevent.
				baseURL,
				storageState: './test/e2e/storageState.json',
			},
		},
	],
	outputDir: './test-results/',
} );
