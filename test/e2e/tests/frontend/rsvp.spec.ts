/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('RSVP to an event', () => {
	test('A logged in user can perform RSVP action', async ({ page }) => {
		await page.goto('/');
		// Be explicit witn the header,
		// because "TT5" has an "Events" demo link in its footer.
		await page
			.locator('header')
			.getByRole('link', { name: 'Events' })
			.click({ force: true }); // Imported from of https://github.com/GatherPress/gatherpress-demo-data

		await page
			.getByRole('link', { name: 'RSVP' })
			.first()
			.click({ force: true, timeout: 60000 });

		await page
			.locator('a')
			.filter({ hasText: 'Attend' })
			.click({ force: true });
		await page.getByText('Close').click({ force: true });
		await expect(
			page.locator('.gatherpress-rsvp-response__items').first()
		).toBeVisible(); // verified the RSVP button is visible.

		await expect(page.getByText('Attending').first()).toBeVisible({
			timeout: 30000,
		}); // verified the logged in user performed RSVP action

		await expect(
			page.locator('.gatherpress-rsvp-response__items').first()
		).toBeVisible(); // verified the attending users list.
	});
});
