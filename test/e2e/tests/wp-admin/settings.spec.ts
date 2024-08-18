/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Settings', () => {
	test('A link to the plugin settings page is present under the Events menu', async ({
		page,
		admin,
	}) => {
		await admin.visitAdminPage('/');

		const menu = page.locator('li', {
			has: page.getByRole('link', { name: 'Events' }),
		});
		const settingsMenu = menu.getByRole('link', {
			name: 'Settings',
		});
		const settingsMenuUrl = await settingsMenu.getAttribute('href');

		await expect(settingsMenu).toBeVisible();
		await expect(settingsMenuUrl).toContain(
			'admin.php?page=gatherpress_general'
		);
	});
});
