const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('Verify the venue location map should not be visible on the events when the map toggled is disabled.', async ({page}) => {
		await login({ page });

		const postName = 'offline test venue - no map is visible';

		await page.goto('/wp-admin/post-new.php?post_type=gatherpress_venue');

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

		await page.getByLabel('Full Address').fill('Bengaluru');

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

		await page.screenshot({
			path: 'artifacts/venue_post_no_map.png',
			fullPage: true,
		});
	});
});
