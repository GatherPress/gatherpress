const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
const fs = require('fs');

test.describe('As admin login into GatherPress', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
	});

	test('Navigate to Events Add New page', async ({ page }) => {
		await login({ page });

		// Navigate to Events and Add New.
		await page.goto('/wp-admin/edit.php?post_type=gatherpress_event');
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

		// Create artifacts directory.
		if (!fs.existsSync('artifacts')) {
			fs.mkdirSync('artifacts');
		}

		// Take screenshot.
		await page.screenshot({ path: 'artifacts/add-new-event.png' });
	});
});
