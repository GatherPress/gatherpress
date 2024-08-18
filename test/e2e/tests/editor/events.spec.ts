/**
 * WordPress dependencies
 */
const { test } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Events in the Editor', () => {
	test.beforeEach(async ({ admin, page }) => {
		await admin.createNewPost({ postType: 'gatherpress_event' });
		await page.getByLabel('Add title').fill('Change title to allow saving');
		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		// Open the Document -> Event settings panel.
		const panelToggle = page.getByRole('button', {
			name: 'Event settings',
		});

		if ((await panelToggle.getAttribute('aria-expanded')) === 'false') {
			await panelToggle.click();
		}
	});

	test.afterEach(async ({ editor, page }) => {
		// Click again to close the element, to let upcoming tests not get flaky.
		await page.getByRole('button', { name: 'Event settings' }).click();
		await editor.publishPost();
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

	/* 
	test('A user should be able to publish an online event', async ({
		page,
	}) => {
		await page.getByLabel('Venue Selector').selectOption('Online event');
	});

	test('A user should be able publish an offline event', async ({
		page,
	}) => {
		await page.getByLabel('Venue Selector').selectOption('Turin'); // Location of WCEU 2024 & imported from https://github.com/GatherPress/demo-data
	}); */
});
