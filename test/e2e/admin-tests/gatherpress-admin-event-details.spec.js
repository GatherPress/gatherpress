const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for event post, verify the event time is visible on front end', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.waitForLoadState('networkidle');
	});

	test('Verify the event post; event details and timezone should be visible on the front end', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New Event' })
			.click();
		await page.getByLabel('Add title').fill('event time details');
		await page
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('link', { name: 'View Event' })
			.click();

		await page.locator('#wp--skip-link--target').isVisible();
		await page.locator('.wp-block-gatherpress-event-date').isVisible();
	});
});
