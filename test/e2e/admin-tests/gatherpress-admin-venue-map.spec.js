const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewVenue } from '../reusable-user-steps/common.js';

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		//await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});

	test('Test to create a new venue for an offline event and verify the entered location map should be visible on the venue post.', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'venue test map-pune';

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
		await page.locator('#map').isVisible({ timeout: 30000 });

		await page.waitForLoadState('domcontentloaded');
		await expect(page.locator('#map')).toBeVisible({ timeout: 30000 });

		await page
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('button', { name: 'Publish', exact: true })
			.click();

		await page
			.getByText(`${postName} is now live.`)
			.isVisible({ timeout: 60000 }); // verified the event is live.

		await page
			.getByLabel('Editor publish')
			.getByRole('link', { name: 'View Venue' })
			.click();

		await page.waitForLoadState('domcontentloaded');

		await page.waitForSelector('#map');

		await page.locator('#map').isVisible({ timeout: 30000 });

		await expect(page).toHaveScreenshot('location_map.png', {
			maxDiffPixels: 800,
			fullPage: true,
			timeout: 30000,
			mask: [
				page.locator('header'),
				page.locator('h1'),
				page.locator('h3'),
				page.locator('nav'),
				page.locator('.wp-block-template-part'),
				page.locator('.wp-block-gatherpress-event-date'),
				page.locator('footer'),
			],
		});
	});
});
