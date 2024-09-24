/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Venues in the Editor', () => {
	test.beforeEach(async ({ admin }) => {
		await admin.createNewPost({ postType: 'gatherpress_venue' });
	});

	test('The admin should be able to create a new Venue.', async ({
		editor,
		page,
	}) => {
		await page.getByLabel('Add title').isVisible();
		await page.getByLabel('Add title').fill('Test venue');
		await page.getByLabel('Add title').press('Tab');

		const venue = await page.$('.gatherpress-venue__name');
		await venue.press('Backspace');

		await page
			.getByLabel('Empty block; start writing or')
			.fill('test venue information');

		await editor.publishPost(); // this is missing the force and doesnt work.
	});
});
