
const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');
import { addNewEvent } from '../reusable-user-steps/common.js';

test.describe('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.setViewportSize({ width: 1920, height: 720 });
		await page.waitForLoadState('networkidle');
	});

	test('the user should be able to publish an online event', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'online event test';

		await addNewEvent({ page });

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
		await page
			.getByLabel('Venue Selector')
			.selectOption('33:online-event', { timeout: 60000 });

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
		await expect(
			page
				.locator('div')
				.filter({ hasText: /^Online event$/ })
				.nth(1)
		).toBeVisible();
	});

	test('the user should be able publish an offline event', async ({
		page,
	}) => {
		await login({ page, username: 'prashantbellad' });

		const postName = 'offline event test';

		await page.getByRole('link', { name: 'Events', exact: true }).click();
		await page
			.locator('#wpbody-content')
			.getByRole('link', { name: 'Add New' })
			.click();

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

		await page
			.getByLabel('Venue Selector')
			.selectOption('73:test-offline-event');

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
	});
});
