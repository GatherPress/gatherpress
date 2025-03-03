const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

import { addNewVenue } from '../reusable-user-steps/common.js';

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.waitForLoadState('networkidle');
	});

	test('Verify the offline venue location map should be visible on the venue post when the display map toggled button is enabled.', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'venue map : toggle on';

		await addNewVenue({ page });

		await page.getByLabel('Add title').fill(postName);

		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		const settingButton = await page.getByLabel('Settings', {
			exact: true,
		});

		const settingExpand = await settingButton.getAttribute('aria-expanded');

		if (settingExpand === 'false') {
			await settingButton.click();
		}
		await expect(settingButton).toHaveAttribute('aria-expanded', 'true');

		const venueButton = await page.getByRole('button', {
			name: 'venue settings',
		});
		const venueExpand = await venueButton.getAttribute('aria-expanded');

		if (venueExpand === 'false') {
			await venueButton.click();
		}

		await expect(venueButton).toHaveAttribute('aria-expanded', 'true');
		await page.getByLabel('Full Address').fill('Pune');

		await page.locator('.gatherpress-venue__full-address').isVisible();

		await page.waitForSelector('#map');
		await page.locator('#map').click();

		await page.getByRole('tab', { name: 'Block' }).click();
		await expect(page.getByLabel('Display the map')).toBeVisible();

		await page
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('link', { name: 'View Venue' })
			.click();

		await expect(page.locator('#map')).toBeVisible();
		await expect(page).toHaveScreenshot('event_toggle_on.png', {
			fullPage: true,
			map: [
				page.locator('header'),
				page.locator('h1'),
				page.locator('h3'),
				page.locator('nav'),
				page.locator('[rel="prev"]'),
				page.locator('.wp-block-template-part'),
				page.locator('.wp-block-gatherpress-event-date'),
				page.locator('footer'),
			],
		});
	});
});
