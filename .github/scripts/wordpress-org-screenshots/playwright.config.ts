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
const { ...baseConfig} = require( '@wordpress/scripts/config/playwright.config' );

export default defineConfig({
    ...baseConfig,

    // This directory holds all the test files.
    // https://playwright.dev/docs/api/class-testconfig#test-config-test-dir
    testDir: '.',

	snapshotPathTemplate: './../../../.wordpress-org/{arg}{ext}',

	expect: {
		toHaveScreenshot: {
			// https://playwright.dev/docs/test-snapshots#maxdiffpixels
			maxDiffPixelRatio: process.env.UPDATE_ALL_SNAPSHOTS ? 0 : 0.05,
			// https://playwright.dev/docs/test-snapshots#stylepath
			stylePath: './ui-adjustments.css'
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
		// This is kind of a hack PART 2/2,
		// to make sure Playwright DOES NOT start the webserver on its own.
		//
		// Part 1/2 is the "run" of the "Running (& Updating) the screenshot tests" steps
		// in .github/workflows/wordpress-org-screenshots.yml
		//
		// While auto-loading the webserver when needed sounded nice, it introduced a race-condition
		// between the setup of Playground and Playwrights own start event.
		// Playwright listens for the availability of the webserver relatively simple,
		// as soon as there is a status code 200, Playwright starts all engines.
		//
		// Unfortunately Playground is not ready at this point, it hast started WordPress
		// and is going to start stepping through the blueprint, but hasn't loaded GatherPress nor imported any data;
		// Resulting in failing tests.
		//
		// Sending just >undefined< is an idea, taken from @swisspidy at: 
		// https://github.com/swissspidy/wp-performance-action/pull/173/files#diff-980717ce45eb5ef0a66e87dd5b664656800d31ca809fe902f069b5e8f3cdcd31
		command: undefined,
		port: 9400,
		reuseExistingServer: true,
	},
});