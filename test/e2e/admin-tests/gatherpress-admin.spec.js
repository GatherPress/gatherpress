const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe.skip('As admin login into GatherPress', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});

	test('The Event menu item should be accessible', async ({ page }) => {
		await login({ page });

		// Navigate to Events
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page.waitForTimeout(1000);

		// Click Add New with safe timeout
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();
	});
});
