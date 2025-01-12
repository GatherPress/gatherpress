const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

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

		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page.getByRole('link', { name: 'Venues' }).click();
		await page.getByRole('link', { name: 'Add New Venue' }).click();

		const currentDate = new Date().toISOString().split('T')[0]; // format YYYY-MM-DD
		const eventTitle = await page
			.getByLabel('Add title')
			.fill(`test: venue map:${currentDate}`);
		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		//await page.getByLabel('Settings', { exact: true }).click();

		await page.getByRole('button', { name: 'Venue settings' }).click();

		await page.getByLabel('Full Address').fill('Pune');

		await page.locator('.gatherpress-venue__full-address').isVisible();
		await page.locator('#map').isVisible({ timeout: 30000 });
		await expect(page.locator('#map')).toBeVisible();

		await page
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('button', { name: 'Publish', exact: true })
			.click();

		await page
			.getByText(`${eventTitle} is now live.`)
			.isVisible({ timeout: 60000 }); // verified the event is live.

		await page
			.getByLabel('Editor publish')
			.getByRole('link', { name: 'View Venue' })
			.click();

		await page.waitForSelector('#map');
		//await page.locator('#map').isVisible({ timeout: 30000 });

		await expect(page).toHaveScreenshot('location_map.png', {
			fullPage: true,
			mask:[
				page.locator('header'),
				page.locator('h1'),
				page.locator('h3'),
				page.locator('nav'),
				page.locator('.wp-block-template-part'),
				page.locator('footer'),
			]
		});
	});
});