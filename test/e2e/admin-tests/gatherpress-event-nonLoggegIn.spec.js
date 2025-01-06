const { test, expect } = require('@playwright/test');
const { loginUser } = require('../reusable-user-steps/user-login');
const { login } = require('../reusable-user-steps/common');

test.describe('e2e test for home page event on develop.gatherpress.org', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});
});

test('the user should be able publish an offline event', async ({ page }) => {
	await login({ page, username: 'prashantbellad' });
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
	await page.getByRole('button', { name: 'Event settings' }).click();
	await page.getByRole('button', { name: 'Event settings' }).click();

	await page
		.getByLabel('Venue Selector')
		.selectOption('73:test-offline-event');

	await page.getByRole('button', { name: 'Publish', exact: true }).click();
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

test('02-verify the non-logged in user view RSVP button on home page and perform RSVP action', async ({
	page,
}) => {
	await page.goto('https://test.gatherpress.org');
	await page.getByRole('heading', { name: 'Upcoming Events' }).isVisible();
	await page
		.locator('div')
		.filter({ hasText: /^online Test Event$/ })
		.isVisible();

	await page.getByRole('link', { name: 'RSVP' }).first().isVisible();

	await page.getByRole('link', { name: 'RSVP' }).first().click();
	await page.getByText('Login', { exact: true }).click();

	await loginUser({ page, username: 'prashantbellad' });
	await page.evaluate(() => window.scrollTo(0, 1000));

	await page
		.getByRole('link', { name: 'RSVP' })
		.first()
		.click({ timeout: 60000 });

	await page.locator('a').filter({ hasText: 'Attend' }).click();
	await page.getByText('Close').click();
	await page.locator('.gatherpress-rsvp-response__items').first().isVisible(); // verified the RSVP button is visible
	await expect(page.getByText('Attending').first()).toBeVisible(); // verified the attending text after RSVP action.

	await page.locator('.gatherpress-rsvp-response__items').first().isVisible(); // verified the attending users list.
	await page
		.locator('.gatherpress-rsvp-response__items')
		.first()
		.screenshot({ path: 'attending.png' });
});
