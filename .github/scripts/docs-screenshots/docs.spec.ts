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
 * from hand-captured PNG images to specs incrementally.
 */
test.describe( 'Documentation screenshots', () => {
	test( 'GatherPress settings: Events tab', async ( { admin, page } ) => {
		await admin.visitAdminPage(
			'edit.php',
			'post_type=gatherpress_event&page=gatherpress_events_settings'
		);

		// Assert the tab actually loaded before capturing. A permission page is
		// still a valid PNG, so without this the suite screenshots the error and
		// reports success (#2122). Matching the href rather than the label keeps
		// the assertion locale-independent.
		await expect(
			page.locator( 'a.nav-tab-active[href*="page=gatherpress_events_settings"]' )
		).toBeVisible();

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

		// Assert the tab actually loaded before capturing. A permission page is
		// still a valid PNG, so without this the suite screenshots the error and
		// reports success (#2122). Matching the href rather than the label keeps
		// the assertion locale-independent.
		await expect(
			page.locator( 'a.nav-tab-active[href*="page=gatherpress_rsvp_settings"]' )
		).toBeVisible();

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
			'post_type=gatherpress_event&page=gatherpress_venues_settings'
		);

		// Assert the tab actually loaded before capturing. A permission page is
		// still a valid PNG, so without this the suite screenshots the error and
		// reports success (#2122). Matching the href rather than the label keeps
		// the assertion locale-independent.
		await expect(
			page.locator( 'a.nav-tab-active[href*="page=gatherpress_venues_settings"]' )
		).toBeVisible();

		await expect( page ).toHaveScreenshot( 'settings-venues-tab.png', {
			fullPage: true,
		} );
	} );
} );
