const { test, expect } = require( '@playwright/test' );
const { createEventWithAPI } = require( '../helpers/create-event-via-api' );

test.describe( 'GatherPress Event Creation via REST API', () => {
	test( 'creates event with RSVP block and shows login message', async ( {
		page,
		playwright,
	} ) => {
		const requestContext = await playwright.request.newContext( {
			extraHTTPHeaders: {
				Authorization:
					'Basic ' +
					Buffer.from(
						`${ process.env.WP_ADMIN_USER }:${ process.env.WP_APP_PASSWORD }`
					).toString( 'base64' ),
				'Content-Type': 'application/json',
			},
		} );

		const eventUrl = await createEventWithAPI( requestContext );

		await page.goto( eventUrl );

		await expect(
			page.locator( '.gatherpress--has-login-url' )
		).toBeVisible();

		await page.getByRole( 'link', { name: 'Login' } ).click();

		await expect( page ).toHaveURL( /wp-login\.php/ );
		await expect( page.locator( '#user_login' ) ).toBeVisible();
		await expect( page.locator( '#user_pass' ) ).toBeVisible();

		await requestContext.dispose();
	} );
} );
