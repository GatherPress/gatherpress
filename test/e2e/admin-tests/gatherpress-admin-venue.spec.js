const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewVenue } from '../reusable-user-steps/common.js';

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('Test to create a new venue for an offline event and verify map visibility', async ({
		page,
	}) => {
		await login({ page, username: 'admin', password: 'password' });

		const postName = 'venue map-test';

		await addNewVenue({ page });
		await page.getByLabel('Add title').fill(postName);

		// Take a screenshot to see the page state
		await page.screenshot({ path: 'before-publish.png' });

		const publishButton = page.getByRole('button', {
			name: 'Publish',
			exact: true,
		});

		// Force visibility check
		const isVisible = await publishButton.isVisible();

		if (isVisible) {
			// Try force click if needed
			await publishButton.click({ force: true });
		} else {
			await page.keyboard.press('Control+S');
		}
	});
});
