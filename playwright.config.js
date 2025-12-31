// playwright.config.js
const { defineConfig, devices } = require( '@playwright/test' );
require( 'dotenv' ).config();

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
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
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
				storageState: './test/e2e/storageState.json',
			},
		},
	],
	outputDir: './test-results/',
} );
