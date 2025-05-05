const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe.skip('e2e test for event creation', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(180000); // Increase timeout to 3 minutes
		await page.goto('/wp-admin/');
	});

	test('Create an event post', async ({ page }) => {
		await login({ page });

		await page.goto('/wp-admin/post-new.php?post_type=gatherpress_event');

		await page.getByLabel('Add title').waitFor({ timeout: 20000 });

		await page.getByLabel('Add title').fill('test event');
		await page.keyboard.press('Control+S');
	});
});
