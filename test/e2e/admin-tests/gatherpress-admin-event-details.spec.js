const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewEvent } from '../reusable-user-steps/common.js';

test.describe('e2e test for event post, verify the event time is visible on front end', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.waitForLoadState('networkidle');
	});

	test('Verify the event post; event details and timezone should be visible on the front end', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'test event : details';

		await addNewEvent({ page });

		await page.getByLabel('Add title').fill(postName);

		await page.getByLabel('Block: Event Date').isVisible();
		await page
			.getByLabel('Block: Event Date')
			.screenshot({ path: 'event-details.png' });
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

		await page.waitForLoadState('domcontentloaded');
		await page.locator('#wp--skip-link--target').isVisible();
		await page
			.locator('#wp--skip-link--target')
			.screenshot({ path: 'event-details-post.png' });

		await expect(page).toHaveScreenshot('event_details.png', {
			fullPage: true,
			mask: [
				page.locator('header'),
				page.locator('h1'),
				page.locator('h3'),
				page.locator('nav'),
				page.locator('.wp-block-template-part'),
				page.locator('footer'),
			],
		});
	});
});
