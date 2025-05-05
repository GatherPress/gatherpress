const { test, expect } = require('@playwright/test');
const { login } = require('../reusable-user-steps/common.js');

test.describe('e2e test for publish event through admin side', () => {
	test.beforeEach(async ({ page }) => {
		test.setTimeout(120000);
		await page.goto('/wp-admin/');
		await page.waitForLoadState('networkidle');
	});

	test('the user should be able to publish an online event', async ({
		page,
	}) => {
		await login({ page });

		const postName = 'online event test';

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
		await page
			.getByPlaceholder('Add link to online event')
			.fill('https://google-meet.com');

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
				.filter({ hasText: `${postName}` })
				.nth(1)
		).toBeVisible();

		await page.locator('.wp-block-gatherpress-dropdown', 'Add to calendar').click({ timeout: 1200 });

		await page.locator('.wp-block-gatherpress-dropdown-item').locator('div').filter({ hasText: /^Google Calendar$/ }).isVisible();

		await page.screenshot({ path: 'artifacts/new-online-event.png' });
	});

	test('the user should be able publish an offline event', async ({
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

		await page
			.getByLabel('Venue Selector')
			.selectOption('venue pune');

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

		await page.screenshot({ path: 'artifacts/new-offline-event.png', fullPage: true });
	});
});
