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
// using Object Rest Spread operator magic
// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Spread_syntax
// const { use, ...baseConfig} = require( '@wordpress/scripts/config/playwright.config' );
const { ...baseConfig} = require( '@wordpress/scripts/config/playwright.config' );

// Remove storageState
// The default conf of Gutenberg has storageState set, which means "We are logged-in".
// To make sure our tests run the other way around, we remove this now,
// and re-set it on a project- or test-level.
// const { storageState, ...baseUse} = use;
// const newConf = {
//     ...baseConfig,
//     use: { ...baseUse },
// }
// console.log(newConf);
// const WP_AUTH_STORAGE = '.auth/wordpress.json';

export default defineConfig({
    // ...newConf,
    ...baseConfig,

    // This directory holds all the test files.
    // https://playwright.dev/docs/api/class-testconfig#test-config-test-dir
    //
    // IDEA: Maybe this should be set to "../../src/"
    // where the test files would be housed directly with their components, blocks, etc.
    testDir: 'tests',

    // This is run before any tests. Check the file for more information.
    globalSetup: 'global-setup.ts',
    // use: {
        // https://playwright.dev/docs/api/class-testoptions#test-options-storage-state
        // storageState: WP_AUTH_STORAGE
        // storageState: ''
    // },
	// Configure projects for major browsers
    // We can test on different or multiple browsers if needed.
    // https://playwright.dev/docs/test-projects#configure-projects-for-multiple-browsers
    projects: [
		{
		  name: "chromium",
		  use: { ...devices["Desktop Chrome"] },
		},

		// {
		// 	name: 'firefox',
		// 	use: { ...devices['Desktop Firefox'] },
		// },

		// {
		// 	name: 'webkit',
		// 	use: { ...devices['Desktop Safari'] },
		// },

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
});