const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewEvent } from '../reusable-user-steps/common.js';

test.describe.skip('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
		await login({ page });
	});

	test('The user should be able add featured image in post and verify the added featured image post', async ({
		page,
	}) => {
		const postName = 'featured image test';

		await addNewEvent({ page });
		const settingButton = await page.getByLabel('Settings', {
			exact: true,
		});

		const settingExpand = await settingButton.getAttribute('aria-expanded');

		if (settingExpand === 'false') {
			await settingButton.click();
		}
		await expect(settingButton).toHaveAttribute('aria-expanded', 'true');

		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();

		await page.getByLabel('Add title').fill(postName);

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

		await page.waitForLoadState('domcontentloaded');
		const FeaturedImage = await page.screenshot({
			fullPage: true,
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
		expect(FeaturedImage).toMatchSnapshot('playwright-featured-image.png');
	});
});
