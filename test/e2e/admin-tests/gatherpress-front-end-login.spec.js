const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for login to front end guests', () => {
	const devUrl = 'https://develop.gatherpress.org/';
	test.beforeEach(async ({ page }) => {
		test.setTimeout(60000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});

	test('verify that the user is able to login after click on RSVP >> login', async ({
		page,
	}) => {
		await page.goto(devUrl);
		await page.screenshot({ path: 'homepage.png', fullPage: true });

		await page.getByRole('link', { name: 'RSVP' }).click();

		await page.getByText('Login', { exact: true }).click();

		await login({ page, username: 'testuser1' });

		await page.goto(devUrl);

		await page.getByRole('link', { name: 'RSVP' }).click();

		await page.locator('a').filter({ hasText: 'Attend' }).click();

		await page.waitForLoadState('networkidle');

		await page.screenshot({ path: 'post-attendies.png', fullPage: true });
	});
});
