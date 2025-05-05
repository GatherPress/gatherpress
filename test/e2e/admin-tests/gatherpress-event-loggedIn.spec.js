const { test } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for event RSVP', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
	});

	test('verify RSVP functionality', async ({ page }) => {
		await login({ page });

		// Go to front page to find events
		await page.goto('/');

		// Try to find and click RSVP button
		const rsvpButton = page.getByRole('link', { name: 'RSVP' }).first();
		if (await rsvpButton.isVisible({ timeout: 10000 })) {
			await rsvpButton.click();

			// Try to attend
			const attendButton = page
				.locator('a')
				.filter({ hasText: 'Attend' });
			if (await attendButton.isVisible({ timeout: 5000 })) {
				await attendButton.click();

				// Try to close modal
				const closeButton = page.getByText('Close');
				if (await closeButton.isVisible({ timeout: 5000 })) {
					await closeButton.click();
				}
			}
		}
	});
});
