const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');


test.describe('e2e test for venue map through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/')
		await page.waitForLoadState('networkidle');
	});

	test('Test to create a new venue for an offline event and verify the entered location map should be visible on the venue post.', async ({
		page,
	}) => {
		await login({ page });

		const postName = 'venue pune';

		await page.goto('/wp-admin/post-new.php?post_type=gatherpress_venue')

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
		await expect(page.locator('#map')).toBeVisible({ timeout: 30000 });

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

		await page.waitForSelector('#map');

		await page.screenshot({ path: 'artifacts/pune-venue.png' });

		await expect(page).toHaveScreenshot('pune-venue.png', {
			maxDiffPixels: 20000,

		});
	});
});
