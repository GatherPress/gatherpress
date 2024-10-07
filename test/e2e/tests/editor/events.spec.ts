/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Events in the Editor', () => {
	let venueSelector;

	test.beforeEach(async ({ admin, page }) => {
		await admin.createNewPost({ postType: 'gatherpress_event' });
		await page.getByLabel('Add title').fill('Change title to allow saving');
	});

	test('An admin should be able to publish an event', async ({
		editor,
		page,
		// pageUtils,
	}) => {
		await editor.publishPost(); // this is missing the force and doesnt work.
		// await pageUtils.pressKeys( 'primary+s' );
		await page.reload();

		await expect( await page.getByLabel('Add title') ).toHaveText('Change title to allow saving');
	});

	test('An admin should be able to publish an online event', async ({
		editor,
		page,
		// pageUtils,
	}) => {

		await page.getByLabel('Venue Selector').waitFor('attached', 30000 ); // 30sec.;
		venueSelector = await page.getByLabel('Venue Selector');
		await expect(venueSelector).toBeVisible();

		await venueSelector.selectOption('Online event');
		await page
			.getByPlaceholder('Add link to online event')
			.fill('www.gatherpress.org');

		await editor.publishPost(); // this is missing the force and doesnt work.
		// await pageUtils.pressKeys( 'primary+s' );
		await page.reload();

		await expect(venueSelector).toBeVisible();
		await expect(venueSelector).toHaveText('Online event');
	});

	test('An admin should be able to publish an offline event', async ({
		editor,
		page,
	}) => {

		await page.getByLabel('Venue Selector').waitFor('attached', 30000 ); // 30sec.;
		venueSelector = await page.getByLabel('Venue Selector');
		await expect(venueSelector).toBeVisible();

		await venueSelector.selectOption('Turin'); // Location of WCEU 2024 & imported from https://github.com/GatherPress/demo-data

		await editor.publishPost(); // this is missing the force and doesnt work.
		// await pageUtils.pressKeys( 'primary+s' );
		await page.reload();

		await expect(venueSelector).toBeVisible();
		await expect(venueSelector).toHaveText('Turin');
	});
});
