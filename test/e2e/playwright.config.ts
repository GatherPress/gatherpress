/**
 * External dependencies
 */
import os from 'os';
import { defineConfig, devices } from '@playwright/test';

/**
 * WordPress dependencies
 *
 * Playwright default configuration, that is used & provided by Gutenberg.
 * https://github.com/WordPress/gutenberg/blob/trunk/packages/scripts/config/playwright.config.js
 */
const {
	...baseConfig
} = require('@wordpress/scripts/config/playwright.config');

export default defineConfig({
	...baseConfig,

	// This directory holds all the test files.
	// https://playwright.dev/docs/api/class-testconfig#test-config-test-dir
	//
	// IDEA: Maybe this should be set to "../../src/"
	// where the test files would be housed directly with their components, blocks, etc.
	testDir: 'tests',

	// Configure projects for major browsers
	// We can test on different or multiple browsers if needed.
	// https://playwright.dev/docs/test-projects#configure-projects-for-multiple-browsers
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},

		{
			name: 'firefox',
			use: { ...devices['Desktop Firefox'] },
		},

		{
			name: 'webkit',
			use: {
				...devices['Desktop Safari'],
				/**
				 * Headless webkit won't receive dataTransfer with custom types in the
				 * drop event on Linux. The solution is to use `xvfb-run` to run the tests.
				 * ```sh
				 * xvfb-run npm run test:e2e
				 * ```
				 * See `.github/workflows/e2e-tests.yml` for advanced usages.
				 */
				headless: os.type() !== 'Linux',
			},
		},

		/* Test against mobile viewports. */
		// {
		//   name: 'Mobile Chrome',
		//   use: { ...devices['Pixel 5'] },
		// },
		// {
		//   name: 'Mobile Safari',
		//   use: { ...devices['iPhone 12'] },
		// },

		/* Test against branded browsers. */
		// {
		// 	name: 'Microsoft Edge',
		// 	use: { ...devices['Desktop Edge'], channel: 'msedge' },
		// },
		// {
		// 	name: 'Google Chrome',
		// 	use: { ...devices['Desktop Chrome'], channel: 'chrome' },
		// },
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
		// Part 1/2 is the "run" of the "Starting Playground, staring Playwright & running the tests" step
		// in .github/workflows/e2e-tests.yml
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
