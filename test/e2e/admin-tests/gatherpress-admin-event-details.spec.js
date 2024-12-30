const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for event post, verify the event time is visible on front end', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.waitForLoadState('networkidle');
	});

	test('Verify the event post; event details and timezone should be visible on the front end', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

        await page.getByRole('link', { name: 'Events', exact: true }).click();
        await page.locator('#wpbody-content').getByRole('link', { name: 'Add New Event' }).click();
        
	const eventTitle = await page
		.getByLabel('Add title')
		.fill('test: offline  event');

        const time= await page.getByLabel('Block: Event Date');
        
	await page
		.getByLabel('Block: Event Date')
		.locator('div')
		.first()
		.isVisible();

	await page.getByRole('heading', { name: 'Date & time' }).isVisible();

    await page.getByRole('button', { name: 'Event settings' }).click();
    await page.getByRole('button', { name: 'Event settings' }).click();
    await page.getByLabel('Venue Selector').selectOption('76:test-venue-map');

    await page.getByRole('button', { name: 'Publish', exact: true }).click();
    await page.getByLabel('Editor publish').getByRole('button', { name: 'Publish', exact: true }).click();

    await page
    .getByText(`${eventTitle} is now live.`)
    .isVisible({ timeout: 60000 }); // verified the event is live.
await page
    .locator('.post-publish-panel__postpublish-buttons')
    .filter({ hasText: 'View Event' })
    .isVisible({ timeout: 30000 }); // verified the view event button.
		
    await page.goto('/');
    
    await page.getByRole('heading', { name: 'Upcoming Events' }).isVisible();
	await page.getByRole('link', { name: 'test: venue map' }).first().click();

    await page.locator('.wp-block-gatherpress-event-date').isVisible();

    
	});
});
