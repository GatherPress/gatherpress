const { test, expect } = require( '@playwright/test' );
const {
	gotoAdmin,
	restRequest,
	openEventEditor,
	setEventTimezone,
	enableRecurrence,
	setFrequency,
	setWeekdays,
	setMonthly,
	setEndCondition,
	saveEvent,
	getEventMeta,
	seedEventWithDatetime,
	createUpcomingEventsListPage,
	getUpcomingRows,
	getSeriesRows,
	readSeriesRowVector,
	makeEventOnline,
	deletePost,
	setSiteTimezone,
	getSiteTimezone,
	weeklyOccurrences,
	dateText,
	leadingDateText,
	firstLastWeekdayOnOrAfter,
	toRecurrenceId,
	daysUntilUtcWeekday,
} = require( '../helpers/recurrence' );

/**
 * Post content for a series whose rows carry an RSVP block and an
 * attendee-only online-event link.
 *
 * The online-event link is the highest-consequence consumer of occurrence
 * identity on the front end: it reveals a private meeting URL to attendees
 * only, so under a store keyed on post ID alone, attending **one** date
 * surfaced that URL on **every** date of the series.
 *
 * @since 0.36.0
 *
 * @type {string}
 */
const RSVP_EVENT_CONTENT =
	'<!-- wp:gatherpress/event-date {"isLink":true} /-->' +
	'<!-- wp:gatherpress/online-event -->' +
	'<div class="wp-block-gatherpress-online-event"></div>' +
	'<!-- /wp:gatherpress/online-event -->' +
	'<!-- wp:gatherpress/rsvp {"patternPicked":true} -->' +
	'<div class="wp-block-gatherpress-rsvp"></div>' +
	'<!-- /wp:gatherpress/rsvp -->';

/**
 * The private meeting URL beat 5 watches for.
 *
 * @since 0.36.0
 *
 * @type {string}
 */
const ONLINE_EVENT_URL = 'https://example.test/private-meeting-room';

/**
 * End-to-end coverage for the recurring-events feature (#80).
 *
 * Covers all six beats of the authoring/browsing journey: authoring a rule
 * and publishing, switching the rule and watching the dates change, seeing
 * one row per occurrence in the upcoming list, following a row to that
 * occurrence's own page, RSVPing to one occurrence without it following to
 * the next, and canceling one occurrence. Two more specs cover the
 * recurrence-aware `posts_clauses` leaving an ordinary event alone, and a
 * fixed-offset timezone visibly refusing recurrence.
 *
 * Beat 5 is the load-bearing one, and it is worth saying why. A whole class of
 * defect shares one shape: occurrence identity reaches the row and a consumer
 * does not read it. The interactivity store keyed on post ID alone is one such
 * defect, and it is visible **only** in a browser: the
 * server renders every row correctly and JavaScript then flattens them to a
 * single status. PHP tests cannot see it, and Jest can prove the key is right
 * without proving the page is. Beat 5 is the check that catches that
 * class as a user experiences it, and it asserts the **whole per-row vector**
 * rather than one row, because a single-row assertion passes just as happily
 * when every row is attending, which is exactly the bug.
 *
 * Every seeded post is deleted in a `finally` block, the list pages as well as
 * the events, so repeated local runs don't accumulate orphans, the site timezone is
 * restored in `afterAll`, and every date is derived from "now" rather than
 * pinned to a literal, so the suite does not rot into a time bomb.
 */
test.describe( 'Recurring events', () => {
	/**
	 * The site timezone as found, restored in `afterAll`.
	 *
	 * Three specs below need a named timezone and set one; other e2e
	 * files share this WordPress install, so leaving it changed is a
	 * cross-file side effect.
	 *
	 * @type {string}
	 */
	let originalTimezone;

	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage();

		await gotoAdmin( page );
		originalTimezone = await getSiteTimezone( page );
		await page.close();
	} );

	test.afterAll( async ( { browser } ) => {
		const page = await browser.newPage();

		await gotoAdmin( page );
		await setSiteTimezone( page, originalTimezone );
		await page.close();
	} );

	test( 'the recurrence-aware event query neither drops nor duplicates an ordinary, non-recurring event', async ( {
		page,
	} ) => {
		// Named for what it proves. The site-level guard's actual criterion is
		// that a site with `gatherpress_has_recurring_events` set to '0' runs
		// byte-identical SQL and performs no extra writes. That is a
		// query-level guarantee, and it
		// is proven by the PHP suite (a `$wpdb->queries` capture across the real
		// entry points), not here: an e2e run cannot establish a genuinely
		// flag-off site while other specs in the same run are creating recurring
		// series, and a spec whose stated precondition is false is a spec that
		// cannot fail for the reason its name gives.
		//
		// What *is* worth proving from the browser is the other half: the
		// `posts_clauses` filters this feature adds to every event query must
		// leave a non-recurring event exactly where it was: one row, its own
		// date, its own plain permalink. The LEFT JOIN, the COALESCE ordering
		// and the NULL branch all have to be right for that to hold.
		await gotoAdmin( page );

		const title = `Ordinary event ${ Date.now() }`;
		const { eventId, link, anchor } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: 5,
		} );

		expect( eventId, 'ordinary event seeded via REST' ).toBeTruthy();

		let listPageId;

		try {
			// The permalink itself must render, with the right title.
			await page.goto( link );
			await page.waitForLoadState( 'load' );
			await expect( page.locator( 'body' ) ).toContainText( title );

			// Same event, seen through the upcoming-events list: exactly one
			// row, not duplicated by an occurrence join and not dropped by an
			// inner one.
			await gotoAdmin( page );

			const { pageId, link: listUrl } = await createUpcomingEventsListPage(
				page,
				`Ordinary event upcoming list ${ Date.now() }`
			);

			listPageId = pageId;

			const rows = await getUpcomingRows( page, listUrl );
			const matching = rows.filter( ( row ) => row.title === title );

			expect(
				matching,
				'an ordinary (non-recurring) event appears exactly once in the upcoming list'
			).toHaveLength( 1 );

			// And it is still *itself*: its own anchor date, and its own plain
			// permalink rather than an occurrence URL. A non-recurring event has
			// no occurrence row, so `COALESCE( o.datetime_start_gmt, ... )` must
			// fall through to the events table for both.
			const expectedDateText = new Date(
				anchor.year,
				anchor.month,
				anchor.day
			).toLocaleDateString( 'en-US', {
				month: 'long',
				day: 'numeric',
				year: 'numeric',
			} );

			expect(
				matching[ 0 ].dateText,
				"the row shows the ordinary event's own anchor date"
			).toContain( expectedDateText );

			expect(
				matching[ 0 ].href,
				"the row links to the ordinary event's own plain permalink, with no occurrence segment"
			).toBe( link );
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );

			if ( listPageId ) {
				await deletePost( page, listPageId, 'pages' );
			}
		}
	} );

	test( 'a fixed-offset timezone visibly refuses recurrence', async ( { page } ) => {
		await gotoAdmin( page );

		// Seeded with a named zone so the only variable under test is the
		// in-editor timezone change below.
		const { eventId } = await seedEventWithDatetime( page, {
			title: `Fixed offset ${ Date.now() }`,
			daysFromNow: 10,
			timezone: 'America/New_York',
		} );

		try {
			await openEventEditor( page, eventId );

			// The three-click organizer path: open Event settings (already
			// open), open the Time Zone dropdown, pick a manual UTC offset.
			await setEventTimezone( page, 'UTC-5' );

			await expect(
				page.getByText( /Recurring events require a named timezone/i )
			).toBeVisible();

			const repeatToggle = page.getByRole( 'checkbox', { name: 'Repeat' } );
			await expect( repeatToggle ).toBeDisabled();
			await expect( repeatToggle ).not.toBeChecked();
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );
		}
	} );

	test( 'Beat 1: authoring a bi-weekly Tuesday/Thursday rule persists and publishes', async ( {
		page,
	} ) => {
		await gotoAdmin( page );
		await setSiteTimezone( page, 'America/New_York' );

		const title = `Bi-weekly Tue/Thu ${ Date.now() }`;
		const { eventId } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: 7,
			status: 'draft',
		} );

		try {
			await openEventEditor( page, eventId );
			await enableRecurrence( page );
			await setFrequency( page, { frequency: 'weekly', interval: 2 } );
			await setWeekdays( page, [ 'Tuesday', 'Thursday' ] );
			await saveEvent( page );

			const meta = await getEventMeta( page, eventId );
			const rule = JSON.parse( meta.gatherpress_recurrence );

			expect( rule.frequency ).toBe( 'weekly' );
			expect( rule.interval ).toBe( 2 );
			expect( rule.weekdays.sort() ).toEqual( [ 2, 4 ] );

			// The server-derived, read-only mirrors are what the expander
			// and the front end actually read, so this proves the panel's write
			// round-tripped through a real save, not just local React state.
			expect( meta.gatherpress_recurrence_frequency ).toBe( 'weekly' );
			expect( meta.gatherpress_recurrence_interval ).toBe( 2 );
			expect( meta.gatherpress_recurrence_byday ).toBe( 'TU,TH' );

			// The post is published, not left as a draft.
			const { status } = await restRequest( page, {
				path: `/wp/v2/gatherpress_events/${ eventId }`,
			} );

			expect( status ).toBe( 'publish' );
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );
		}
	} );

	test( 'Beat 2: switching to monthly last-Wednesday changes the projected occurrence dates', async ( {
		page,
	} ) => {
		await gotoAdmin( page );
		await setSiteTimezone( page, 'America/New_York' );

		const title = `Monthly switch ${ Date.now() }`;
		// Anchored on a Monday, deliberately never a Wednesday. A `daysFromNow`
		// literal would put the anchor on whatever weekday the suite happens to
		// run. Since a broken bare-series resolution renders the anchor
		// date, a spec asserting only "the shown date is a Wednesday" would pass
		// with the resolver entirely dead on one run in seven. Pinning the
		// anchor off a rule weekday, and asserting the exact resolved date
		// below rather than its day-of-week, closes both halves of that.
		const { eventId, anchor } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: daysUntilUtcWeekday( 1 ),
		} );

		try {
			await openEventEditor( page, eventId );
			await enableRecurrence( page );
			await setFrequency( page, { frequency: 'weekly' } );
			await setWeekdays( page, [ 'Tuesday' ] );
			await saveEvent( page );

			const weeklyMeta = await getEventMeta( page, eventId );

			expect( weeklyMeta.gatherpress_recurrence_frequency ).toBe( 'weekly' );

			// Switch to monthly, last Wednesday of the month.
			await setFrequency( page, { frequency: 'monthly' } );
			await setMonthly( page, {
				mode: 'nth_weekday',
				ordinal: 'Last',
				weekday: 'Wednesday',
			} );
			await saveEvent( page );

			const monthlyMeta = await getEventMeta( page, eventId );

			expect( monthlyMeta.gatherpress_recurrence_frequency ).toBe( 'monthly' );
			expect( monthlyMeta.gatherpress_recurrence_monthly_mode ).toBe( 'nth_weekday' );
			expect( monthlyMeta.gatherpress_recurrence_monthly_ordinal ).toBe( -1 );
			expect( monthlyMeta.gatherpress_recurrence_monthly_weekday ).toBe( 3 );

			// The dates actually changed, and changed to the *right* dates.
			// The expected value is computed independently in JS
			// (`firstLastWeekdayOnOrAfter` mirrors `Expander::next_monthly_date()`
			// composed with `nth_weekday_of_month()` at ordinal -1), never read
			// back from anything the app produced. Asserting the exact date
			// rather than "it is a Wednesday" is what makes "last" load-bearing:
			// a server resolving `-1` as the *first* Wednesday of the month also
			// produces a Wednesday.
			const { link } = await restRequest( page, {
				path: `/wp/v2/gatherpress_events/${ eventId }`,
			} );

			await page.goto( link );
			await page.waitForLoadState( 'load' );

			const anchorDate = new Date( anchor.year, anchor.month, anchor.day );
			const expectedOccurrence = firstLastWeekdayOnOrAfter( anchorDate, 3 );
			const expectedDateText = expectedOccurrence.toLocaleDateString( 'en-US', {
				month: 'long',
				day: 'numeric',
				year: 'numeric',
			} );

			await expect(
				page.locator( 'body' ),
				'the series permalink resolves to the last Wednesday on or after the anchor'
			).toContainText( expectedDateText );
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );
		}
	} );

	test( 'Beats 3-4: a recurring series expands into multiple upcoming entries, and following one shows a real occurrence date', async ( {
		page,
	} ) => {
		await gotoAdmin( page );
		await setSiteTimezone( page, 'America/New_York' );

		// A full editor round trip plus five front-end navigations; give it
		// room on a loaded CI runner rather than letting a timeout read as a
		// product failure.
		test.slow();

		const title = `List expansion ${ Date.now() }`;
		// Anchored on a Monday, deliberately outside the Tuesday/Thursday
		// rule below. The anchor date and the rule's first occurrence must
		// differ, or a broken occurrence-resolution path would coincidentally
		// pass on any run where the anchor already lands on a rule day.
		const { eventId, anchor } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: daysUntilUtcWeekday( 1 ),
			hour: 18,
			status: 'draft',
		} );

		let listPageId;

		try {
			await openEventEditor( page, eventId );
			await enableRecurrence( page );
			await setFrequency( page, { frequency: 'weekly', interval: 2 } );
			await setWeekdays( page, [ 'Tuesday', 'Thursday' ] );
			await setEndCondition( page, { endType: 'count', count: 12 } );
			await saveEvent( page );

			const { link: seriesLink } = await restRequest( page, {
				path: `/wp/v2/gatherpress_events/${ eventId }`,
			} );

			const { pageId, link: listUrl } = await createUpcomingEventsListPage(
				page,
				`Beats 3-4 upcoming list ${ Date.now() }`,
				{ perPage: 50, search: title }
			);

			listPageId = pageId;

			await page.goto( listUrl );
			await page.waitForLoadState( 'load' );

			const matching = await readSeriesRowVector( getSeriesRows( page, title ) );

			// The expected occurrence dates are computed independently in JS
			// (`weeklyOccurrences()` mirrors `Expander::matches_weekly()`
			// composed with the day scan, Monday-start week buckets and all),
			// never read back from anything the app produced.
			const anchorDate = new Date( anchor.year, anchor.month, anchor.day );
			const expected = weeklyOccurrences( anchorDate, [ 2, 4 ], 2, 12 );
			const [ firstOccurrence, secondOccurrence ] = expected;
			const seriesBase = seriesLink.replace( /\/$/, '' );

			// Beat 3: the series shows up as one row per occurrence, at the
			// occurrence's own date, in occurrence order.
			//
			// This asserts the exact projected dates rather than
			// `length > 1`, because `length > 1` is weaker than the property
			// it is named for: a query that expanded to the right *number* of
			// rows and rendered the anchor's date into every one of them
			// satisfies a count and fails the requirement.
			expect(
				matching.map( ( row ) => leadingDateText( row.dateText ) ),
				'the upcoming list shows exactly the projected occurrence dates, in order'
			).toEqual( expected.map( dateText ) );

			// Beat 4, part zero, and the journey a reader actually performs:
			// click a row and land on that row's occurrence. Nothing else in
			// this file exercises the row's *own* href; every other assertion
			// below builds the URL itself, so a row that linked to the bare
			// series would leave them all green.
			//
			// A later row, not the first: the bare-series fallback resolves to
			// the next-upcoming occurrence, so a row whose href had lost its
			// occurrence segment entirely would still land on the *first*
			// occurrence's date and satisfy an assertion aimed at it. The third
			// row sits in a different interval bucket, and only a preserved
			// occurrence segment can reach it.
			const followed = matching[ 2 ];
			const followedResponse = await page.goto( followed.href );
			await page.waitForLoadState( 'load' );

			expect(
				followedResponse.status(),
				"a row's own link resolves"
			).toBe( 200 );

			await expect(
				page.locator( 'body' ),
				"following a row's own link lands on that row's occurrence date"
			).toContainText( leadingDateText( followed.dateText ) );

			expect(
				leadingDateText( followed.dateText ),
				'and that row is a later occurrence, not the one a bare-series fallback would resolve to'
			).toBe( dateText( expected[ 2 ] ) );

			// Beat 4, part one: the exact occurrence URL
			// (`{permalink}{Ymd}T{His}/`) resolves and shows that specific
			// occurrence's date.
			const firstUrl = `${ seriesBase }/${ toRecurrenceId( firstOccurrence, '180000' ) }/`;
			const firstResponse = await page.goto( firstUrl );
			await page.waitForLoadState( 'load' );

			expect(
				firstResponse.status(),
				'the occurrence URL resolves (pretty permalinks are in effect for it)'
			).toBe( 200 );

			await expect(
				page.locator( 'body' ),
				"the occurrence URL's page shows that exact occurrence's date"
			).toContainText( dateText( firstOccurrence ) );

			// Beat 4, part two, and the assertion that makes part one mean
			// anything: the *second* occurrence's URL must render the *second*
			// occurrence's date. The bare-series fallback
			// (`Rewrite::maybe_resolve_bare_series()`) always resolves to the
			// next upcoming occurrence, which is the first one, so it can
			// satisfy every assertion about the first occurrence's URL without
			// the occurrence segment being read at all. Only a later
			// occurrence's date distinguishes "the URL was parsed" from "the
			// URL was discarded and the series resolved from scratch."
			const secondUrl = `${ seriesBase }/${ toRecurrenceId( secondOccurrence, '180000' ) }/`;
			const secondResponse = await page.goto( secondUrl );
			await page.waitForLoadState( 'load' );

			expect(
				secondResponse.status(),
				"a later occurrence's URL also resolves"
			).toBe( 200 );

			await expect(
				page.locator( 'body' ),
				"a later occurrence's URL shows that later occurrence's date, not the series' next-upcoming one"
			).toContainText( dateText( secondOccurrence ) );

			// A well-formed but nonexistent recurrence ID must 404 rather than
			// quietly rendering the series. This drives `parse_request()`'s
			// `error=404` branch and the `redirect_canonical` suppression that
			// goes with it. Without the latter, core finds the series by its
			// `name` query var and 301s to the bare series URL, turning a stale
			// or hand-typed link into "renders the series at its anchor date."
			const bogusDate = new Date( firstOccurrence.getTime() + ( 400 * 24 * 60 * 60 * 1000 ) );
			const bogusResponse = await page.goto(
				`${ seriesBase }/${ toRecurrenceId( bogusDate, '180000' ) }/`
			);

			expect(
				bogusResponse.status(),
				'a well-formed recurrence ID with no matching occurrence row 404s'
			).toBe( 404 );

			// Beat 4, part three: follow the series resource's bare permalink,
			// separately from the occurrence-specific links the list rows emit
			// (the rows' own hrefs were asserted above). The bare link lands on
			// the next-upcoming occurrence via
			// `Rewrite::maybe_resolve_bare_series()`,
			// rather than the series' stale anchor date. The anchor
			// is a Monday and the rule is Tuesday/Thursday, so "the first
			// occurrence" and "the anchor" are never the same date.
			await page.goto( seriesLink );
			await page.waitForLoadState( 'load' );

			await expect(
				page.locator( 'body' ),
				'the bare series URL resolves to the next-upcoming occurrence'
			).toContainText( dateText( firstOccurrence ) );

			await expect(
				page.locator( 'body' ),
				"the bare series URL does not render the series' own anchor date"
			).not.toContainText( dateText( anchorDate ) );
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );

			if ( listPageId ) {
				await deletePost( page, listPageId, 'pages' );
			}
		}
	} );

	test( 'Beat 5: an RSVP on one occurrence does not follow to the next, and neither does the attendee-only link', async ( {
		page,
	} ) => {
		// An editor round trip, a six-row loop rendering each occurrence's own
		// RSVP block and online-event link, an in-page RSVP and a reload. The
		// heaviest spec in the file; give it room on a loaded CI runner rather
		// than letting a timeout read as a product failure.
		test.slow();

		await gotoAdmin( page );
		await setSiteTimezone( page, 'America/New_York' );

		const title = `Per-occurrence RSVP ${ Date.now() }`;
		// Anchored on a Monday, outside the Tuesday/Thursday rule, so the
		// anchor and the first occurrence are never the same date.
		const { eventId, anchor } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: daysUntilUtcWeekday( 1 ),
			hour: 18,
			content: RSVP_EVENT_CONTENT,
		} );

		let listPageId;

		try {
			await makeEventOnline( page, eventId, ONLINE_EVENT_URL );

			await openEventEditor( page, eventId );
			await enableRecurrence( page );
			await setFrequency( page, { frequency: 'weekly' } );
			await setWeekdays( page, [ 'Tuesday', 'Thursday' ] );
			await setEndCondition( page, { endType: 'count', count: 6 } );
			// Saving from the editor is also what seeds the RSVP block's inner
			// blocks: the starter markup carries an empty wrapper, and the
			// block's edit component fills its template in at runtime. A
			// REST-only fixture would render an RSVP block with nothing in it.
			await saveEvent( page );

			await gotoAdmin( page );

			const { pageId, link: listUrl } = await createUpcomingEventsListPage(
				page,
				`Beat 5 upcoming list ${ Date.now() }`,
				{ perPage: 50, withPostContent: true, search: title }
			);

			listPageId = pageId;

			await page.goto( listUrl );
			await page.waitForLoadState( 'load' );

			const rows = getSeriesRows( page, title );
			const anchorDate = new Date( anchor.year, anchor.month, anchor.day );
			const expected = weeklyOccurrences( anchorDate, [ 2, 4 ], 1, 6 );
			const before = await readSeriesRowVector( rows );

			expect(
				before.map( ( row ) => leadingDateText( row.dateText ) ),
				'the list opens on one row per occurrence, in occurrence order'
			).toEqual( expected.map( dateText ) );

			// Baseline: nobody is attending anything, so the private meeting
			// URL is withheld on every row. Asserting the vector here is what
			// makes the vector after the RSVP mean something. Without it, a
			// page that rendered the link everywhere from the start would still
			// "show the link on the row we RSVPed to".
			expect(
				before.map( ( row ) => row.onlineTag ),
				'before any RSVP, no row exposes the private meeting URL'
			).toEqual( expected.map( () => 'SPAN' ) );

			expect(
				before.map( ( row ) => row.onlineHref ),
				'and no row carries the URL as an href'
			).toEqual( expected.map( () => null ) );

			// RSVP to the SECOND occurrence, deliberately.
			//
			// Rule 3a #8: never pick a fixture where the right answer and the
			// fallback answer coincide. Every fallback in this subsystem
			// resolves to the series' *next upcoming* occurrence, which is the
			// first row. RSVPing to the first row would leave a
			// request-scoped, series-wide or bare-series read producing exactly
			// the expected vector. Only a later occurrence separates "the row's
			// own identity was read" from "something resolved the series from
			// scratch".
			const targetIndex = 1;
			const target = rows.nth( targetIndex );

			expect(
				before[ targetIndex ].recurrenceId,
				'the row being RSVPed to publishes its own occurrence identity to the client'
			).toBe( toRecurrenceId( expected[ targetIndex ], '180000' ) );

			await target.locator( '.gatherpress-modal--trigger-open button' ).first().click();

			const modal = target
				.locator( '.gatherpress-modal--type-rsvp.gatherpress--is-visible' )
				.first();

			await expect( modal, "the row's own RSVP modal opens" ).toBeVisible();
			await modal.locator( '.gatherpress-rsvp--trigger-update button' ).first().click();

			// The client half, asserted WITHOUT reloading. This is the check
			// nothing else in the build can make: `state.posts` is keyed by
			// `getPostKey( postId, recurrenceId )`, and if that key ever
			// collapses back to the post ID alone, every row of this
			// series shares one entry and `sendRsvpApiRequest`'s single write
			// lights up all six at once. The server markup is correct per row;
			// only a browser can see JavaScript flatten it.
			await expect(
				target.locator( '.gatherpress-online-event__text' ),
				'the row that was RSVPed to reveals the private meeting URL'
			).toHaveAttribute( 'href', ONLINE_EVENT_URL );

			const after = await readSeriesRowVector( rows );

			expect(
				after.map( ( row ) => row.onlineTag ),
				'exactly one row exposes the private meeting URL, and it is the one that was RSVPed to'
			).toEqual( expected.map( ( _date, index ) => ( targetIndex === index ? 'A' : 'SPAN' ) ) );

			expect(
				after.map( ( row ) => row.onlineHref ),
				'no sibling occurrence of the same series carries the URL'
			).toEqual(
				expected.map( ( _date, index ) => ( targetIndex === index ? ONLINE_EVENT_URL : null ) )
			);

			expect(
				after.map( ( row ) => row.rsvpStatus ),
				'and the RSVP block itself reports attending on that row alone'
			).toEqual(
				expected.map( ( _date, index ) => ( targetIndex === index ? 'attending' : 'no_status' ) )
			);

			// The dates did not shuffle underneath the vector, so the row that
			// went green is the occurrence it claims to be.
			expect(
				after.map( ( row ) => leadingDateText( row.dateText ) ),
				'the rows are still the same occurrences, in the same order'
			).toEqual( expected.map( dateText ) );

			// The server half: reload and read it all again. This proves the
			// RSVP was *written* scoped to one occurrence rather than merely
			// displayed that way. `Event::maybe_get_online_event_link()` asks
			// the RSVP store per row, so a series-wide write would light every
			// row up here even with a perfectly keyed client store.
			await page.reload();
			await page.waitForLoadState( 'load' );

			const reloaded = await readSeriesRowVector( getSeriesRows( page, title ) );

			expect(
				reloaded.map( ( row ) => row.onlineHref ),
				'after a reload the server still withholds the URL from every other occurrence'
			).toEqual(
				expected.map( ( _date, index ) => ( targetIndex === index ? ONLINE_EVENT_URL : null ) )
			);

			expect(
				reloaded.map( ( row ) => row.rsvpStatus ),
				'and the stored RSVP belongs to one occurrence, not to the series'
			).toEqual(
				expected.map( ( _date, index ) => ( targetIndex === index ? 'attending' : 'no_status' ) )
			);
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );

			if ( listPageId ) {
				await deletePost( page, listPageId, 'pages' );
			}
		}
	} );

	test( 'Beat 6: a canceled occurrence leaves the list, still resolves, and says it was canceled', async ( {
		page,
	} ) => {
		// The organizer path here opens the editor twice, once to author the
		// rule and once to come back to the Occurrences panel, so give it room
		// on a loaded CI runner rather than letting a timeout read as a product
		// failure.
		test.slow();

		await gotoAdmin( page );
		await setSiteTimezone( page, 'America/New_York' );

		const title = `Cancel one occurrence ${ Date.now() }`;
		const { eventId, anchor, link: seriesLink } = await seedEventWithDatetime( page, {
			title,
			daysFromNow: daysUntilUtcWeekday( 1 ),
			hour: 18,
		} );

		let listPageId;

		try {
			await openEventEditor( page, eventId );
			await enableRecurrence( page );
			await setFrequency( page, { frequency: 'weekly' } );
			await setWeekdays( page, [ 'Tuesday', 'Thursday' ] );
			await setEndCondition( page, { endType: 'count', count: 6 } );
			await saveEvent( page );

			const anchorDate = new Date( anchor.year, anchor.month, anchor.day );
			const expected = weeklyOccurrences( anchorDate, [ 2, 4 ], 1, 6 );

			// Cancel the SECOND occurrence, not the first, for the same reason as
			// beat 5. Canceling the first would also move what the bare-series
			// URL resolves to, so "it left the list" could be satisfied by a
			// resolver that had simply advanced; and an off-by-one in the
			// panel's own row-to-occurrence mapping would be invisible against
			// the first row. Canceling a middle occurrence leaves neighbors
			// on both sides to prove the rest of the series is untouched.
			const cancelIndex = 1;

			// Re-open the editor so the Occurrences panel mounts against a
			// series that already has occurrence rows: its `useEffect` fetches
			// once, on mount, and the rule was authored after that in the
			// session above. This is the organizer's own path: the panel is on
			// the event they come back to, not the one they just created.
			await openEventEditor( page, eventId );

			const panelRows = page.locator( '.gatherpress-occurrence-row' );

			await expect(
				panelRows,
				'the Occurrences panel lists every upcoming occurrence of the series'
			).toHaveCount( expected.length );

			const cancelRow = panelRows.nth( cancelIndex );

			await expect(
				cancelRow.locator( '.gatherpress-occurrence-row__date' ),
				'the panel row about to be canceled is the occurrence it claims to be'
			).toContainText( dateText( expected[ cancelIndex ] ) );

			await expect(
				cancelRow.locator( '.gatherpress-occurrence-row__status' )
			).toHaveText( 'Scheduled' );

			// Driven through the UI, not the REST route: the button is the path
			// an organizer takes and the one nothing else in the build covers.
			await cancelRow.getByRole( 'button', { name: 'Cancel' } ).click();

			await expect(
				cancelRow.locator( '.gatherpress-occurrence-row__status' ),
				'the panel reports the occurrence canceled'
			).toHaveText( 'Canceled', { timeout: 20000 } );

			await expect(
				cancelRow.getByRole( 'button', { name: 'Restore' } ),
				'and offers to restore it, so the action is reversible'
			).toBeVisible();

			// Every other panel row is untouched, because cancellation is
			// occurrence state and never a mutation of the rule.
			await expect(
				panelRows.locator( '.gatherpress-occurrence-row__status' ).filter( {
					hasText: 'Canceled',
				} ),
				'exactly one occurrence is canceled, not the series'
			).toHaveCount( 1 );

			await gotoAdmin( page );

			const { pageId, link: listUrl } = await createUpcomingEventsListPage(
				page,
				`Beat 6 upcoming list ${ Date.now() }`,
				{ perPage: 50, search: title }
			);

			listPageId = pageId;

			await page.goto( listUrl );
			await page.waitForLoadState( 'load' );

			const remaining = await readSeriesRowVector( getSeriesRows( page, title ) );

			// Half one: it left the upcoming list, and the other five are all
			// still there, in order. Asserting the whole vector rather than
			// "the canceled date is absent" is what separates "one occurrence
			// was dropped" from "the join went wrong and took the series with
			// it", which is exactly what a `status = 'scheduled'` predicate in
			// the WHERE clause rather than the JOIN condition would do.
			expect(
				remaining.map( ( row ) => leadingDateText( row.dateText ) ),
				'the canceled occurrence leaves the upcoming list and its siblings stay'
			).toEqual(
				expected
					.filter( ( _date, index ) => cancelIndex !== index )
					.map( dateText )
			);

			// Half two: its own URL still resolves. Somebody holding a link to
			// the canceled date has to be *told*, not 404ed and left guessing.
			const canceledUrl =
				`${ seriesLink.replace( /\/$/, '' ) }/` +
				`${ toRecurrenceId( expected[ cancelIndex ], '180000' ) }/`;
			const response = await page.goto( canceledUrl );
			await page.waitForLoadState( 'load' );

			expect(
				response.status(),
				"a canceled occurrence's URL still resolves rather than 404ing"
			).toBe( 200 );

			// Half three: the page says so, on the occurrence's own date.
			await expect(
				page.locator( '.gatherpress-occurrence-canceled-notice' ),
				'the canceled occurrence says it was canceled'
			).toHaveText( 'This occurrence has been canceled.' );

			await expect(
				page.locator( 'body' ),
				"and is still the occurrence's own date, not the series' anchor"
			).toContainText( dateText( expected[ cancelIndex ] ) );

			// A sibling occurrence's page carries no such notice. The notice
			// is scoped to the canceled row, not prepended to the series.
			const siblingUrl =
				`${ seriesLink.replace( /\/$/, '' ) }/` +
				`${ toRecurrenceId( expected[ cancelIndex + 1 ], '180000' ) }/`;

			await page.goto( siblingUrl );
			await page.waitForLoadState( 'load' );

			await expect(
				page.locator( '.gatherpress-occurrence-canceled-notice' ),
				'a scheduled sibling occurrence carries no cancellation notice'
			).toHaveCount( 0 );
		} finally {
			await gotoAdmin( page );
			await deletePost( page, eventId );

			if ( listPageId ) {
				await deletePost( page, listPageId, 'pages' );
			}
		}
	} );
} );
