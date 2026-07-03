/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

/**
 * WordPress dependencies
 *
 * Playwright default configuration, that is used & provided by Gutenberg.
 * https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/config/playwright.config.js
 */
const { ...baseConfig } = require( '@wordpress/scripts/config/playwright.config' );

export default defineConfig({
	...baseConfig,

	// This directory holds all the test files.
	// https://playwright.dev/docs/api/class-testconfig#test-config-test-dir
	testDir: '.',

	// Documentation images live with the user docs; the spec's screenshot
	// names become the file names, so keep them semantic (what the image
	// shows), not timestamped.
	snapshotPathTemplate: './../../../docs/user/user-doc-media/{arg}{ext}',

	expect: {
		toHaveScreenshot: {
			// https://playwright.dev/docs/test-snapshots#maxdiffpixels
			maxDiffPixelRatio: process.env.UPDATE_ALL_SNAPSHOTS ? 0 : 0.05,
			// Shared with the wordpress.org suite: hides environment noise
			// (e.g. stretches the admin menu background) so screenshots stay
			// comparable between runs.
			// https://playwright.dev/docs/test-snapshots#stylepath
			stylePath: './../wordpress-org-screenshots/ui-adjustments.css'
		},
	},

	// Configure projects for major browsers
	// We can test on different or multiple browsers if needed.
	// https://playwright.dev/docs/test-projects#configure-projects-for-multiple-browsers
	projects: [
		{
			name: "chromium",
			use: { ...devices["Desktop Chrome"] },
		},
	],
	// Don't report slow test "files", as we will be running our tests in serial.
	reportSlowTests: null,
	use: {
		...baseConfig.use,
		// Gutenberg uses wp-env by default for Playwright tests,
		// which runs on a different port.
		// Playground runs on port: 9400.
		baseURL: process.env.WP_BASE_URL || 'http://127.0.0.1:9400',
	},
	retries: 0,
	webServer: {
		...baseConfig.webServer,
		// See the same block in the wordpress.org suite's config: Playwright
		// must NOT start (or wait on) the webserver itself — the workflow
		// boots Playground and sleeps until it is ready.
		command: undefined,
		port: 9400,
		reuseExistingServer: true,
	},
});
