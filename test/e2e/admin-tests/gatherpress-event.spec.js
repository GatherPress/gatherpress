const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common');

test.describe('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
		await login({ page, username: 'testuser1' });
	});

	test('the user should be able to publish an online event', async ({
		page,
	}) => {
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();
		const currentDate = new Date().toISOString().split('T')[0]; // format YYYY-MM-DD
		const eventTitle = await page
			.getByLabel('Add title')
			.fill(`online T-Event: ${currentDate}`);
		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();
		await page.getByRole('button', { name: 'Event settings' }).click();
		await page
			.getByLabel('Venue Selector')
			.selectOption('ol', { timeout: 60000 });
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
			.locator('.post-publish-panel__postpublish-buttons')
			.filter({ hasText: 'View Event' })
			.isVisible({ timeout: 30000 }); // verified the view event button.
	});

	test('the user should be able publish an offline event', async ({
		page,
	}) => {
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();
		const currentDate = new Date().toISOString().split('T')[0]; // format YYYY-MM-DD
		const eventTitle = await page
			.getByLabel('Add title')
			.fill(`offline T-Event:${currentDate}`);
		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();
		await page.getByRole('button', { name: 'Event settings' }).click();
		await page
			.getByLabel('Venue Selector')
			.selectOption('offline event', { timeout: 60000 });
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
			.locator('.post-publish-panel__postpublish-buttons')
			.filter({ hasText: 'View Event' })
			.isVisible({ timeout: 30000 }); // verified the view event button.
	});
});
