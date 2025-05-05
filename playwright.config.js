// playwright.config.js
const { defineConfig, devices } = require('@playwright/test');
require('dotenv').config();

module.exports = defineConfig({
	testDir: './test/e2e',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 2 : 1,
	workers: process.env.CI ? 1 : undefined,
	timeout: 180000,
	reporter: [['html'], ['list', { printSteps: true }]],
	globalSetup: './test/e2e/global-setup.js',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		trace: 'on-first-retry',
		video: 'on-first-retry',
		screenshot: 'only-on-failure',
		storageState: './test/e2e/storageState.json',
	},
	projects: [
		{
			name: 'chromium',
			use: {
				...devices['Desktop Chrome'],
				storageState: './test/e2e/storageState.json',
			},
		},
	],
	outputDir: './test-results/',
	snapshotDir: './artifacts',
});
