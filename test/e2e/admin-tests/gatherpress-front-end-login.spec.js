const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for login to front end guests', () => {
	const devUrl = 'https://develop.gatherpress.org/';
	test.beforeEach(async ({ page }) => {
		test.setTimeout(60000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});

	//TODO- Replace the event creation test with the POST api request.

	test.only('To publish the event post', async ({
		page,
	}) => {
		await login({ page, username: 'testuser1' });

		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page.screenshot({ path: 'event-page.png' });

		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

		await page.getByLabel('Document Overview').click();

		await page.getByLabel('List View').locator('div').nth(1).isVisible();

		const eventTitle = await page
			.getByLabel('Add title')
			.fill('test event');
		await page.getByLabel('Venue Selector').selectOption('33:online-event');

		await page.screenshot({ path: 'add-new-event.png' });

		await page.getByRole('button',{ name: 'Publish', exact: true }).click();

		await page
			.getByLabel('Editor publish').getByRole('button',{ name: 'Publish', exact: true })
			.click();

		await page
			.getByText({ eventTitle }, 'is now live.')
			.isVisible({ timeout: 60000 });
	});

	test('verify that the user is able to login after click on RSVP >> login', async ({
		page,
	}) => {
		await page.goto(devUrl);

		await page
			.getByRole('heading', { name: 'Upcoming Events' })
			.isVisible();

		await page.getByRole('link', { name: 'RSVP' }).click();

		await page.getByText('Login', { exact: true }).click();

		await login({ page, username: 'testuser1' });

		await page.goto(devUrl, { timeout: 120000 });

		await page.getByRole('link', { name: 'RSVP' }).click();

		await page.locator('a').filter({ hasText: 'Attend' }).click();

		await page.waitForLoadState('networkidle');

		await page.screenshot({ path: 'post-attendies.png' });
	});
});
