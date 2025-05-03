// @ts-check
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

// Login to WordPress and save the storage state for use in tests.
async function globalSetup(config) {
	const WP_BASE_URL =
		config.projects[0].use.baseURL || 'http://localhost:8889';
	const username = 'admin';
	const password = 'password';

	// eslint-disable-next-line no-console
	console.log('Starting WordPress login process...');

	const browser = await chromium.launch();
	const page = await browser.newPage();

	try {
		await page.goto(`${WP_BASE_URL}/wp-login.php`);
		await page.fill('#user_login', username);
		await page.fill('#user_pass', password);

		await Promise.all([
			page.click('#wp-submit'),
			page.waitForNavigation({ timeout: 30000 })
		]);

		const dashboardVisible = await page.isVisible('#wpadminbar');
		if (!dashboardVisible) {
			throw new Error(
				'Login failed - could not access WordPress dashboard'
			);
		}

		const storageStatePath = path.resolve(__dirname, 'storageState.json');
		const storageStateDir = path.dirname(storageStatePath);
		if (!fs.existsSync(storageStateDir)) {
			fs.mkdirSync(storageStateDir, { recursive: true });
		}

		await page.context().storageState({ path: storageStatePath });
	} finally {
		await browser.close();
	}
}

module.exports = globalSetup;
