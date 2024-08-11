import { test, expect, WP_AUTH_STORAGE } from '@test-utils';

// We have multiple tests in this file, all requiring us to be authenticated.
// Compare this to the front-end.spec.ts.
// test.use({ storageState: WP_AUTH_STORAGE });

// test.beforeAll(async ({ requestUtils }) => {
//     await requestUtils.activatePlugin('gatherpress');
// });

test.describe( 'GatherPress Settings', () => {
    test('A link to the plugin settings page is present under the Events menu', async ({
        page,
        admin,
    }) => {
        await admin.visitAdminPage('/');

        const gatherPressMenuItem = page.locator('li', {
            has: page.getByRole('link', { name: 'Events' }),
        });
        const wpGatherPressSettingsItem = gatherPressMenuItem.getByRole('link', {
            name: 'Settings',
        });
        const wpGatherPressSettingsItemUrl =
            await wpGatherPressSettingsItem.getAttribute('href');

        await expect(wpGatherPressSettingsItem).toBeVisible();
        await expect(wpGatherPressSettingsItemUrl).toContain(
            'edit.php?post_type=gatherpress_event&page=gatherpress_general',
        );
    });
});