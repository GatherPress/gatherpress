const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewEvent } from '../reusable-user-steps/common.js';

test.describe('e2e test for event post', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('Verify event creation and details', async ({ page }) => {
		await login({ page });
		await addNewEvent({ page });
		await page.getByLabel('Add title').fill('test event');

		await page.keyboard.press('Control+S');
	});
});
