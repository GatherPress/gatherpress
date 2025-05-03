// @ts-check
const { defineConfig, devices } = require('@playwright/test');
require('dotenv').config();

module.exports = defineConfig({
	testDir: './test/e2e',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	timeout: 60000,
	reporter: [['html'], ['list', { printSteps: true }]],
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		trace: 'on-first-retry',
		video: 'on-first-retry',
		screenshot: 'only-on-failure',
		storageState: './test/e2e/storageState.json',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /global-setup\.js/,
		},
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				storageState: './test/e2e/storageState.json',
			},
			dependencies: ['setup'],
		},
	],
	outputDir: './test-results/',
});
