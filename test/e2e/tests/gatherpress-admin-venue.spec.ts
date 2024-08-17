/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('e2e test for venue through admin side', () => {
	test('The admin should be able to create a new post for Venue', async ({
		admin,
		page,
	}) => {
		await admin.createNewPost({ postType: 'gatherpress_venue' });

		await page.getByLabel('Add title').isVisible();
		await page.getByLabel('Add title').fill('Test venue');
		await page.getByLabel('Add title').press('Tab');

		const venue = await page.$('.gatherpress-venue__name');
		await venue.press('Backspace');

		await page
			.getByLabel('Empty block; start writing or')
			.fill('test venue information');

		await page.getByLabel('Toggle block inserter').click();
		await page.getByRole('option', { name: 'Paragraph' }).click();
		// await page.screenshot({ path: 'new-venue.png' });
	});
});
