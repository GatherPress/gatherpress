const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewVenue } from '../reusable-user-steps/common.js';

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(60000);
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('Create a new venue with address', async ({ page }) => {
		await login({ page });
		const postName = 'venue test map-pune';
		await addNewVenue({ page });
		await page.getByLabel('Add title').fill(postName);

		// Save with keyboard shortcut instead of trying to publish
		await page.keyboard.press('Control+S');
	});
});
