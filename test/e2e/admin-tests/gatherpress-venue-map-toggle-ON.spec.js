const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for venue map through admin side', () => {
    test.beforeEach(async ({ page }) => {
        test.setTimeout(120000);
        await page.waitForLoadState('networkidle');

    });

 

 test('Verify the offline venue location map should be visible on the venue post when the display map toggled button is enabled.', async ({
        page,
    }) => {
        await login({ page, username: 'prashantbellad' });
        
        await page.getByRole('link', { name: 'Events', exact: true }).click();
        await page.getByRole('link', { name: 'Venues' }).click();
        await page.getByRole('link', { name: 'Add New Venue' }).click();

        const currentDate = new Date().toISOString().split('T')[0]; // format YYYY-MM-DD
        const eventTitle = await page
            .getByLabel('Add title')
            .fill(`test: venue map:${currentDate}`);
        await page
            .getByLabel('Block: Event Date')
            .locator('div')
            .first()
            .isVisible();
        await page.getByRole('heading', { name: 'Date & time' }).isVisible();

        await page.getByLabel('Settings', { exact: true }).click();
        await page.getByLabel('Settings', { exact: true }).click();
        await page.getByRole('button', { name: 'Venue settings' }).click();
        await page.getByRole('button', { name: 'Venue settings' }).click();
        await page.getByLabel('Full Address').fill('hinjewadi, pune, India');

        await page.locator('.gatherpress-venue__full-address').isVisible();

        await page.locator('#map').click();

        await page.getByRole('tab', { name: 'Block' }).click();
        await expect(page.getByLabel('Display the map')).toBeVisible();
        


        await page.getByRole('button', { name: 'Publish', exact: true }).click();
        await page.getByLabel('Editor publish').getByRole('button', { name: 'Publish', exact: true }).click();
        await page.getByLabel('Editor publish').getByRole('link', { name: 'View Venue' }).click();

        await expect(page.locator('#map')).toBeVisible();
        await page.screenshot({ path: 'venue_post_map.png', fullPage: true })
    });
});
