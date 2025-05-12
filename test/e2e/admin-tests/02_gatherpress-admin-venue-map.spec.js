const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('Test to create a new venue for an offline event and verify the entered location map should be visible on the venue post.', async ({page}) => {
		await login({ page });

		const postName = 'venue test map-Bengaluru';

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

		await expect(page).toHaveScreenshot('Bengalure_location_map.png', {
			maxDiffPixels: 1000,
			fullPage: true,
		});
	});
});
