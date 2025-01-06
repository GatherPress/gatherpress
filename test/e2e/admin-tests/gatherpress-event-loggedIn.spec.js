const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
		await login({ page, username: 'prashantbellad' });
	});

	test('01-the user should be able to publish an online event', async ({
		page,
	}) => {
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

		await page.getByLabel('Add title').fill('online event');

		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		await page.getByLabel('Settings', { exact: true }).click();
		await page.getByLabel('Settings', { exact: true }).click();

		await page.getByRole('button', { name: 'Event settings' }).click();

		await page.getByRole('button', { name: 'Event settings' }).click();
		await page.getByRole('button', { name: 'Event settings' }).click();
		await page.getByLabel('Venue Selector').selectOption('33:online-event');
		const currentDate = new Date().toISOString().split('T')[0]; // format YYYY-MM-DD
		const eventTitle = await page
			.getByLabel('Add title')
			.fill(`online T-Event: ${currentDate}`);

		await page
			.getByPlaceholder('Add link to online event')
			.fill('www.google.com');

		await page.getByRole('button', { name: 'Event settings' }).click();

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

	test('02-verify the logged in user view RSVP button on home page and perform RSVP action', async ({
		page,
	}) => {
		await page.getByRole('menuitem', { name: 'GatherPress' }).click();

		await page.evaluate(() => window.scrollTo(0, 5000));
		await page
			.getByRole('link', { name: 'RSVP' })
			.first()
			.click({ timeout: 60000 });

		await page.locator('a').filter({ hasText: 'Attend' }).click();
		await page.getByText('Close').click();
		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.isVisible(); // verified the RSVP button is visible.

		await page.getByText('Attending').first().isVisible({ timeout: 30000 }); // verified the logged in user perform RSVP action

		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.isVisible(); // verified the attending users list.
		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.screenshot({ path: 'attending.png' });
	});
});
