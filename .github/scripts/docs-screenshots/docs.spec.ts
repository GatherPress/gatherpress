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

	test.describe( 'Event Calendar', () => {
		// A fixed month keeps the images the same from one run to the next. The
		// demo data has no events in April 2027, and the month has no change of
		// daylight saving time.
		const MONTH = '2027-04';
		const WEEK = '2027-04-12';

		const EVENTS = [
			[ 'Farmers Market Meetup', '2027-04-01 10:00:00', '2027-04-01 12:00:00' ],
			[ 'JavaScript Meetup', '2027-04-05 18:30:00', '2027-04-05 20:30:00' ],
			[ 'Lunch and Learn: Accessibility', '2027-04-07 12:00:00', '2027-04-07 13:00:00' ],
			[ 'Community Cleanup Day', '2027-04-08 00:00:00', '2027-04-08 23:59:59', true ],
			[ 'Planning Session', '2027-04-12 10:00:00', '2027-04-12 11:00:00' ],
			[ 'Coffee and Code', '2027-04-13 09:00:00', '2027-04-13 10:30:00' ],
			[ 'Photography Walk', '2027-04-13 14:00:00', '2027-04-13 16:00:00' ],
			[ 'WordCamp Boston', '2027-04-14 09:00:00', '2027-04-16 17:00:00' ],
			[ 'Design Critique', '2027-04-15 12:00:00', '2027-04-15 13:30:00' ],
			[ 'Book Club', '2027-04-15 18:00:00', '2027-04-15 19:30:00' ],
			[ 'Board Game Night', '2027-04-17 17:00:00', '2027-04-17 19:30:00' ],
			[ 'Volunteer Day', '2027-04-18 00:00:00', '2027-04-18 23:59:59', true ],
			[ 'Open Mic Night', '2027-04-22 19:00:00', '2027-04-22 21:00:00' ],
			[ 'Hackathon Weekend', '2027-04-24 09:00:00', '2027-04-25 18:00:00' ],
			[ 'Volunteer Orientation', '2027-04-28 17:00:00', '2027-04-28 19:00:00' ],
		];

		// Room for a whole month next to the block sidebar.
		test.use( { viewport: { width: 1440, height: 1100 } } );

		test.beforeAll( async ( { requestUtils } ) => {
			// beforeAll runs again in a new worker after a failure, so skip events
			// that are already there.
			const existing = await requestUtils.rest( {
				path: '/wp/v2/gatherpress_events',
				params: { per_page: 100, status: 'publish', context: 'edit' },
			} );
			const keys = existing.map(
				( post ) => `${ post.title.raw } ${ post.meta.gatherpress_datetime_start }`
			);

			for ( const [ title, start, end, isAllDay = false ] of EVENTS ) {
				if ( keys.includes( `${ title } ${ start }` ) ) {
					continue;
				}

				await requestUtils.rest( {
					method: 'POST',
					path: '/wp/v2/gatherpress_events',
					data: {
						title,
						status: 'publish',
						excerpt: 'A community event in the calendar.',
						meta: {
							gatherpress_datetime: JSON.stringify( {
								dateTimeStart: start,
								dateTimeEnd: end,
								timezone: 'America/New_York',
							} ),
							gatherpress_is_all_day: isAllDay,
						},
					},
				} );
			}
		} );

		/**
		 * Closes the editor's welcome guide, which opens for a new page.
		 *
		 * @param page The Playwright page of the editor.
		 */
		async function dismissWelcomeGuide( page ) {
			await page.evaluate( () => {
				const preferences = window.wp.data.dispatch( 'core/preferences' );
				preferences.set( 'core/edit-post', 'welcomeGuide', false );
				preferences.set( 'core', 'welcomeGuide', false );
			} );
			const guide = page.getByRole( 'dialog', { name: /welcome/i } );
			if ( await guide.isVisible() ) {
				await page.keyboard.press( 'Escape' );
			}
			await expect( guide ).toBeHidden();
		}

		/**
		 * Inserts the Event Calendar and sets its Calendar block, its day and the
		 * labels of its Previous and Next links.
		 *
		 * @param editor        The editor fixture.
		 * @param calendarAttrs Attributes of the Calendar block.
		 * @param options       Class name of the Calendar Day block, and the unit
		 *                      the Previous and Next links name.
		 * @return The client ID of the Calendar block.
		 */
		async function insertCalendar( editor, calendarAttrs, { dayClassName = '', unit = 'Month' } = {} ) {
			const clientId = await editor.page.evaluate(
				( { attrs, dayClass, label } ) => {
					const { blocks, data } = window.wp;
					const variation = blocks
						.getBlockVariations( 'core/query' )
						.find( ( v ) => 'gatherpress-calendar' === v.name );
					const query = blocks.createBlock(
						'core/query',
						variation.attributes,
						blocks.createBlocksFromInnerBlocksTemplate( variation.innerBlocks )
					);

					data.dispatch( 'core/block-editor' ).insertBlocks( query );

					const editor = data.select( 'core/block-editor' );
					const calendar = editor.getBlocksByName( 'gatherpress/calendar' )[ 0 ];
					const day = editor.getBlocksByName( 'gatherpress/calendar-day' )[ 0 ];

					data.dispatch( 'core/block-editor' ).updateBlockAttributes( calendar, attrs );

					if ( dayClass ) {
						data.dispatch( 'core/block-editor' ).updateBlockAttributes( day, {
							className: dayClass,
						} );
					}

					// The Calendar Settings panel renames these when the view changes.
					for ( const [ name, text ] of [
						[ 'core/query-pagination-previous', `Previous ${ label }` ],
						[ 'core/query-pagination-next', `Next ${ label }` ],
					] ) {
						for ( const link of editor.getBlocksByName( name ) ) {
							data.dispatch( 'core/block-editor' ).updateBlockAttributes( link, {
								label: text,
							} );
						}
					}

					return calendar;
				},
				{ attrs: calendarAttrs, dayClass: dayClassName, label: unit }
			);

			await expect(
				editor.canvas.locator( '.gatherpress-calendar__entry:visible' ).first()
			).toBeVisible();

			return clientId;
		}

		test( 'Event Calendar: a month in the editor', async ( { admin, editor, page } ) => {
			await admin.createNewPost( { postType: 'page', title: 'Events' } );
			await dismissWelcomeGuide( page );

			const calendar = await insertCalendar(
				editor,
				{ selectedDate: MONTH },
				{ dayClassName: 'is-style-ribbon' }
			);

			// Show the Calendar Settings panel, which the docs explain next.
			await page.evaluate( ( id ) => {
				window.wp.data.dispatch( 'core/block-editor' ).selectBlock( id );
				window.wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
			}, calendar );

			await expect( page.getByRole( 'button', { name: 'Calendar Settings' } ) ).toBeVisible();

			// The block toolbar floats over the Previous Month link.
			await page.addStyleTag( {
				content: '.block-editor-block-contextual-toolbar { visibility: hidden; }',
			} );

			await expect( page ).toHaveScreenshot( 'event-calendar-editor-month.png' );
		} );

		test( 'Event Calendar: a week with the time grid', async ( {
			admin,
			editor,
			page,
		} ) => {
			await admin.createNewPost( { postType: 'page', title: 'This week' } );
			await dismissWelcomeGuide( page );

			await insertCalendar(
				editor,
				{ viewType: 'week', selectedDate: WEEK, enableTimeGrid: true },
				{ unit: 'Week' }
			);

			const id = await editor.publishPost();

			await page.goto( `/?page_id=${ id }` );

			const calendar = page.locator( '.wp-block-query:has(.wp-block-gatherpress-calendar)' );

			await expect( calendar.locator( '.gatherpress-calendar__entry:visible' ).first() ).toBeVisible();

			await expect( calendar ).toHaveScreenshot( 'event-calendar-week-time-grid.png' );
		} );
	} );
} );
