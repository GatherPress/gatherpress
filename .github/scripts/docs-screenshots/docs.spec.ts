/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * Internal dependencies
 */
import { highlight } from './highlight';

/**
 * Documentation screenshots.
 *
 * Every test generates one image used by the user documentation under
 * docs/user/. Unlike the wordpress.org suite these are English-only and
 * named semantically — the screenshot name IS the file name referenced
 * from the markdown, so treat renames as breaking changes.
 *
 * Pilot set: the three GatherPress settings tabs, feeding the 0.34.0
 * rewrite of docs/user/configuration.md (#1845). Further images migrate
 * from hand-captured PNGs to specs incrementally.
 */
test.describe( 'Documentation screenshots', () => {
	test( 'GatherPress settings: Events tab', async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'edit.php',
			'post_type=gatherpress_event&page=gatherpress_events'
		);

		await expect( page ).toHaveScreenshot( 'settings-events-tab.png', {
			fullPage: true,
		} );
	} );

	test( 'GatherPress settings: RSVP tab, highlighting the RSVP Mode field', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage(
			'edit.php',
			'post_type=gatherpress_event&page=gatherpress_rsvp_settings'
		);

		// Draw attention to the RSVP Mode select, the setting the docs
		// section explains first.
		await highlight( page, page.locator( 'select[name*="rsvp_mode"]' ) );

		await expect( page ).toHaveScreenshot( 'settings-rsvp-tab.png', {
			fullPage: true,
		} );
	} );

	test( 'GatherPress settings: Venues tab', async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'edit.php',
			'post_type=gatherpress_event&page=gatherpress_venues'
		);

		await expect( page ).toHaveScreenshot( 'settings-venues-tab.png', {
			fullPage: true,
		} );
	} );
} );
