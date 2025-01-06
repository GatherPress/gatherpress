const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common');

test.describe('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
		await login({ page, username: 'prashantbellad' });
	});

	test('the user should be able to publish an online event', async ({
		page,
	}) => {
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();

		await page.getByLabel('Add title').fill('Featured Image test');

		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		await page.getByRole('button', { name: 'Set featured image' }).click();

		await page
			.locator('.attachments-wrapper')
			.locator('li')
			.first()
			.click();

		await page.getByRole('button', { name: 'Set featured image' }).click();

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
		await page.locator('#wp--skip-link--target img').isVisible();

		await page.screenshot({ path: 'featured-image.png', fullPage: true });
	});
});
