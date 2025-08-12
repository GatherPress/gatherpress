const { test } = require( '@playwright/test' );
const { login } = require( '../reusable-user-steps/common.js' );

test.describe( 'As admin login into GatherPress', () => {
	test.beforeEach( async ( { page } ) => {
		test.setTimeout( 180000 );
		await page.goto( '/wp-admin/' );
	} );

	test( 'Navigate to Events Add New page', async ( { page } ) => {
		await login( { page } );

		// Go directly to the Add New page.
		await page.goto( '/wp-admin/post-new.php?post_type=gatherpress_event' );

		// Take screenshot.
		await page.screenshot( { path: 'artifacts/add-new-event.png' } );
	} );
} );
