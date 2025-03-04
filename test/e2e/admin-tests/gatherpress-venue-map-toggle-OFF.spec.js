const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewVenue } from '../reusable-user-steps/common.js';


test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.waitForLoadState('networkidle');
	});

	test('Verify the offline venue location map should not be visible on the venue post when the display map toggled button is disabled.', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'offline test event';

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
		await page.locator('#map').click({ force: true });

		await page.getByRole('tab', { name: 'Block' }).click();
		await page.getByLabel('Display the map').uncheck();
		await expect(page.getByLabel('Hide the map')).toBeVisible();

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

		await expect(
			page
				.locator('#wp--skip-link--target')
				.getByRole('heading', { postName })
		).toBeVisible();
		
		await page.screenshot({
			path: 'venue_post_no_map.png',
			fullPage: true,
		});
	});
});
