const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for venue creation', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000); // Increased timeout
		await page.goto('/wp-admin/');
	});

	test('Create a basic venue', async ({ page }) => {
		await login({ page });

		// Navigate directly to new venue without waiting for networkidle
		await page.goto('/wp-admin/post-new.php?post_type=gatherpress_venue');

		// Add title and save without additional waits
		await page.getByLabel('Add title').fill('Test Venue');
		await page.keyboard.press('Control+S');
	});
});
