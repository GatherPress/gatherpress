// test/e2e/global-setup.js
const { chromium } = require('@playwright/test');
const path = require('path');
const fs = require('fs');

async function globalSetup() {
	// eslint-disable-next-line no-console
	console.log('Running global setup...');

	// Create artifacts directory.
	if (!fs.existsSync('artifacts')) {
		fs.mkdirSync('artifacts');
	}

	// Create WordPress auth state
	const browser = await chromium.launch();
	const page = await browser.newPage();

	try {
		await page.goto('http://localhost:8889/wp-login.php');
		await page.fill('#user_login', 'admin');
		await page.fill('#user_pass', 'password');
		await page.click('#wp-submit');
		await page.waitForSelector('#wpadminbar');

		const storageStatePath = path.join(__dirname, 'storageState.json');
		await page.context().storageState({ path: storageStatePath });
		// eslint-disable-next-line no-console
		console.log(`Created auth state at ${storageStatePath}`);
	} finally {
		await browser.close();
	}
}

module.exports = globalSetup;
