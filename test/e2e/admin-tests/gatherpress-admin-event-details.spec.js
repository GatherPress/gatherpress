const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewEvent } from '../reusable-user-steps/common.js';

test.describe.skip(
	'e2e test for event post, verify the event time is visible on front end',
	() => {
		test.beforeEach(async ({ page }) => {
			test.setTimeout(120000);
			await page.waitForLoadState('networkidle');
		});

		test.skip('Verify the event post; event details and timezone should be visible on the front end', async ({
			page,
		}) => {
			await login({ page, username: 'prashantbellad' });

			const postName = 'test event : details';

			await addNewEvent({ page });

			await page.getByLabel('Add title').fill(postName);

			await page.locator('[data-title="Event Date"]').isVisible();

			const settingButton = await page.getByLabel('Settings', {
				exact: true,
			});

			const settingExpand =
				await settingButton.getAttribute('aria-expanded');

			if (settingExpand === 'false') {
				await settingButton.click();
			}
			await expect(settingButton).toHaveAttribute(
				'aria-expanded',
				'true'
			);

			const eventButton = await page.getByRole('button', {
				name: 'Event settings',
			});
			const eventExpand = await eventButton.getAttribute('aria-expanded');

			if (eventExpand === 'false') {
				await eventButton.click();
			}

			await expect(eventButton).toHaveAttribute('aria-expanded', 'true');
			await page
				.getByLabel('Venue Selector')
				.selectOption('76:test-venue-map');

			await expect(page.locator('#map')).toBeVisible();

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
			await page.locator('.wp-block-gatherpress-event-date').isVisible();

			await expect(page).toHaveScreenshot('event_details.png', {
				maxDiffPixels: 10,
				fullPage: true,
				mask: [
					page.locator('header'),
					page.locator('h1'),
					page.locator('h3'),
					page.locator('nav'),
					page.locator('.wp-block-gatherpress-venue'),
					page.locator('.wp-block-template-part'),
					page.locator('footer'),
				],
			});
		});
	}
);
