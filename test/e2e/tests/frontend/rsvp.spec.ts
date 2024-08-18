/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('RSVP to an event', () => {
	/* 
	test('02-verify the non-logged in user view RSVP button on home page and perform RSVP action', async ({
		page,
	}) => {
		await page.goto('https://develop.gatherpress.org');
		await page.getByRole('heading', { name: 'Upcoming Events' }).isVisible();
		await page
			.locator('div')
			.filter({ hasText: /^online Test Event$/ })
			.isVisible();
	
		await page.getByRole('link', { name: 'RSVP' }).first().isVisible();
	
		await page.getByRole('link', { name: 'RSVP' }).first().click();
		await page.getByText('Login', { exact: true }).click();
	
		await loginUser({ page, username: 'testuser1' });
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
	}); */

	test('A logged in user can perform RSVP action', async ({ page }) => {
		await page.goto('/');
		await page.getByRole('link', { name: 'Events' }).click(); // Imported from of https://github.com/GatherPress/demo-data

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

		// await page
		// 	.locator('.gatherpress-rsvp-response__items')
		// 	.first()
		// 	.screenshot({ path: 'attending.png' });
	});
});
