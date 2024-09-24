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
		baseURL: process.env.WP_BASE_URL || 'http://127.0.0.1:9400',
	},
	retries: 0,
	webServer: {
		...baseConfig.webServer,
		command: undefined,
		// 'npm run playground:mount -- --blueprint=./test/e2e/blueprint.json',
		port: 9400,
		reuseExistingServer: true,
	},
});
