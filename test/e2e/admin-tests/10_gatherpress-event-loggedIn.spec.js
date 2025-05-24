const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe.skip('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test.skip('the user should be able publish an offline event and verify the logged in user view RSVP button on home page and perform RSVP action', async ({
		page,
	}) => {
		await login({ page });

		const postName = 'offline event test';

		await page.goto('/wp-admin/post-new.php?post_type=gatherpress_event');

		await page.getByLabel('Add title').fill(postName);
		await page
			.getByLabel('Block: Event Date')
			.locator('div')
			.first()
			.isVisible();
		await page.getByRole('heading', { name: 'Date & time' }).isVisible();

		const settingButton = await page.getByLabel('Settings', {
			exact: true,
		});

		const settingExpand = await settingButton.getAttribute('aria-expanded');

		if (settingExpand === 'false') {
			await settingButton.click();
		}

		await expect(settingButton).toHaveAttribute('aria-expanded', 'true');

		const eventButton = await page.getByRole('button', {
			name: 'Event settings',
		});
		const eventExpand = await eventButton.getAttribute('aria-expanded');

		if (eventExpand === 'false') {
			await eventButton.click();
		}

		await expect(eventButton).toHaveAttribute('aria-expanded', 'true');

		await page.getByLabel('Venue Selector').selectOption('venue pune');

		await page
			.getByRole('button', { name: 'Publish', exact: true })
			.click();
		await page
			.getByLabel('Editor publish')
			.getByRole('button', { name: 'Publish', exact: true })
			.click();

		await page
			.getByLabel('Editor publish')
			.getByRole('link', { name: 'View Event' })
			.click();

		await page
			.locator('#wp--skip-link--target')
			.getByRole('heading', { postName }).isVisible;

		await expect(page.locator('#map')).toBeVisible();

		await page.getByRole('button', { name: 'RSVP' }).click();

		await page.locator('a').filter({ hasText: 'Attend' }).click();

		await page.getByText('Close').click();

		await page.getByText('Attending').first().isVisible({ timeout: 30000 });

		await page.screenshot({
			path: 'artifacts/rsvp-attending.png',
			fullPage: true,
		});
	});

	test.skip('02-verify the logged in user view RSVP button on home page and perform RSVP action', async ({
		page,
	}) => {
		await page.getByRole('menuitem', { name: 'GatherPress' }).click();
		await page.evaluate(() => window.scrollTo(0, 5000));
		await page
			.getByRole('link', { name: 'RSVP' })
			.first()
			.click({ timeout: 60000 });

		await page.locator('a').filter({ hasText: 'Attend' }).click();
		await page.getByText('Close').click();
		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.isVisible(); // verified the RSVP button is visible.

		await page.getByText('Attending').first().isVisible({ timeout: 30000 }); // verified the logged in user perform RSVP action

		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.isVisible(); // verified the attending users list.
		await page
			.locator('.gatherpress-rsvp-response__items')
			.first()
			.screenshot({ path: 'attending.png' });
	});
});
