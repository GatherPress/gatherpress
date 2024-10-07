/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Events in the Editor', () => {

	test.beforeEach(async ({ admin, page }) => {
		await admin.createNewPost({ postType: 'gatherpress_event' });
		await page.getByLabel('Add title').fill('Change title to allow saving');
	});

	test.afterEach(async ({ editor, page }) => {
		await editor.publishPost(); // this is missing the force and doesnt work.
	});

	test('An admin should be able to publish an online event', async ({
		page,
	}) => {
		await page.getByLabel('Venue Selector').selectOption('Online event');
		await page
			.getByPlaceholder('Add link to online event')
			.fill('www.google.com');
	});

	test('An admin should be able to publish an offline event', async ({
		page,
	}) => {
		await page.getByLabel('Venue Selector').selectOption('Turin'); // Location of WCEU 2024 & imported from https://github.com/GatherPress/demo-data
	});

});
