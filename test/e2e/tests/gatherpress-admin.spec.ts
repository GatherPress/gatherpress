/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('As admin login into gatherPress', () => {
	test('The Event menu item should be preloaded after clicking Add New button', async ({
		page,
	}) => {
		await page.getByRole('link', { name: 'Events', exact: true }).click();
		// await page.screenshot({ path: 'event-page.png' });

		/* 		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

		await page.getByLabel('Document Overview').click();

		await page.getByLabel('List View').locator('div').nth(1).isVisible();
		await page.screenshot({ path: 'add-new-event.png' }); */

		// Maybe better use ... ?
		// await admin.createNewPost( { postType: 'gatherpress_event' } );

		/* 		await page.getByRole('link', { name: 'Events', exact: true }).click();

		await page.getByRole('link', { name: 'Venues' }).click();
		await page.screenshot({ path: 'vanue-page.png' }); */
	});
});
