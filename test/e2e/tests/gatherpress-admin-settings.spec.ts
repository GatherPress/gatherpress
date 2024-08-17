/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('GatherPress Settings', () => {
	test('A link to the plugin settings page is present under the Events menu', async ({
		page,
		admin,
	}) => {
		await admin.visitAdminPage('/');

		const gatherPressMenuItem = page.locator('li', {
			has: page.getByRole('link', { name: 'Events' }),
		});
		const wpGatherPressSettingsItem = gatherPressMenuItem.getByRole(
			'link',
			{
				name: 'Settings',
			}
		);
		const wpGatherPressSettingsItemUrl =
			await wpGatherPressSettingsItem.getAttribute('href');

		await expect(wpGatherPressSettingsItem).toBeVisible();
		await expect(wpGatherPressSettingsItemUrl).toContain(
			'edit.php?post_type=gatherpress_event&page=gatherpress_general'
		);
	});
});
