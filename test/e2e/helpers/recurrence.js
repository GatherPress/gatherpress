const { expect } = require( '@playwright/test' );

/**
 * Playwright helpers for the recurring-events e2e suite (#80).
 *
 * Carries the reusable parts of the recurrence journey: seeding an event
 * with a datetime, opening the editor's Recurrence panel, driving its
 * controls, saving/publishing, and reading occurrence rows off the front
 * end. Selectors are anchored to GatherPress's own labels and markup
 * (`gatherpress-recurrence-panel`, the panel's own `SelectControl`/
 * `CheckboxControl` labels) rather than WordPress admin implementation
 * details, per `AGENTS.md`'s e2e guidance.
 *
 * Every function takes a Playwright `page` that is already authenticated
 * (via the shared `storageState.json` `global-setup.js` produces) and, where
 * relevant, already navigated to `/wp-admin/` so `window.wp.apiFetch` is
 * available with a valid REST nonce.
 *
 * @since 0.36.0
 */

/**
 * Default post content for a seeded event.
 *
 * Non-empty so the editor's pattern-picker start page never covers the sidebar
 * controls a later step interacts with. Includes a linked event-date block so
 * specs can read the resolved occurrence date straight off the single event
 * page (bare series URL or a direct occurrence URL).
 *
 * @since 0.36.0
 *
 * @type {string}
 */
const DEFAULT_EVENT_CONTENT =
	'<!-- wp:gatherpress/event-date {"isLink":true} /-->' +
	'<!-- wp:paragraph --><p>Seeded by the recurring-events e2e suite.</p><!-- /wp:paragraph -->';

/**
 * Dismiss any modal overlay blocking the block editor (welcome guide,
 * pattern picker, etc). Waits for the overlay to actually detach rather than
 * a fixed timeout, and loops to cover modals that stack.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 *
 * @return {Promise<void>}
 */
async function dismissEditorModals( page ) {
	const modalOverlay = page.locator( '.components-modal__screen-overlay' ).first();

	for ( let attempt = 0; 3 > attempt; attempt++ ) {
		const isOpen = await modalOverlay.isVisible().catch( () => false );

		if ( ! isOpen ) {
			break;
		}

		await page.keyboard.press( 'Escape' );
		await modalOverlay.waitFor( { state: 'hidden', timeout: 5000 } ).catch( () => {} );
	}
}

/**
 * Perform a REST request through the page's `wp.apiFetch`, raising a legible
 * Error when it rejects.
 *
 * `apiFetch` rejects with a plain object (`{ code, message, data }`), not an
 * `Error`. Playwright serializes a thrown non-`Error` out of `page.evaluate`
 * as the bare string `Object`, so every REST failure in this suite arrives as
 * `Error: page.evaluate: Object` with the entire reason discarded, pointing
 * only at the helper's line number. A missing route, a permissions refusal and
 * a rejected meta value all land the same way. That turns a one-line diagnosis into an
 * investigation. Normalize the rejection inside the page, then raise a real
 * Error on the Node side naming the route, the status and the message.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page    Playwright page object, already on an admin page.
 * @param {Object}                          options `apiFetch` options (`path`, `method`, `data`).
 *
 * @return {Promise<Object>} The parsed response body.
 */
async function restRequest( page, options ) {
	const outcome = await page.evaluate( async ( request ) => {
		if ( 'function' !== typeof window.wp?.apiFetch ) {
			return { error: { code: 'no_api_fetch', message: 'window.wp.apiFetch is not available', status: null } };
		}

		try {
			return { data: await window.wp.apiFetch( request ) };
		} catch ( error ) {
			return {
				error: {
					code: error?.code ?? 'unknown_error',
					message: error?.message ?? String( error ),
					status: error?.data?.status ?? null,
				},
			};
		}
	}, options );

	if ( outcome.error ) {
		const { code, message, status } = outcome.error;

		throw new Error(
			`REST ${ options.method ?? 'GET' } ${ options.path } failed: ` +
				`${ code } (HTTP ${ status ?? 'unknown' }): ${ message }`
		);
	}

	return outcome.data;
}

/**
 * Navigate to `/wp-admin/` and confirm the page is usable for REST seeding.
 *
 * Every spec in this suite starts by loading an admin page so
 * `window.wp.apiFetch` exists with a valid REST nonce. If that load ever
 * comes back without the admin's script bundle, the very next `page.evaluate`
 * dies on `Cannot read properties of undefined (reading 'apiFetch')`. That is
 * a broken test rather than a red one, reported against whichever seeding call
 * happened to run first. Asserting the precondition here turns that into a
 * named failure at the point the precondition was violated.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 *
 * @return {Promise<void>}
 */
async function gotoAdmin( page ) {
	await page.goto( '/wp-admin/' );
	await page.waitForLoadState( 'load' );

	const hasApiFetch = await page.evaluate( () => 'function' === typeof window.wp?.apiFetch );

	expect(
		hasApiFetch,
		'/wp-admin/ loaded with window.wp.apiFetch available for REST seeding'
	).toBe( true );
}

/**
 * Ensure the "Event settings" sidebar panel is expanded.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 *
 * @return {Promise<void>}
 */
async function openEventSettingsPanel( page ) {
	const eventSettingsButton = page.getByRole( 'button', { name: /Event settings/i } ).first();

	await expect( eventSettingsButton ).toBeVisible( { timeout: 15000 } );

	const expanded = await eventSettingsButton.getAttribute( 'aria-expanded' );

	if ( 'true' !== expanded ) {
		await eventSettingsButton.click();
	}

	// The Recurrence panel's own wrapper div confirms the panel actually
	// mounted, not merely that the toggle reports expanded.
	await expect( page.locator( '.gatherpress-recurrence-panel' ) ).toBeVisible( { timeout: 15000 } );
}

/**
 * Seed a GatherPress event with a datetime via the REST API, reusing the
 * same `wp.apiFetch` seeding pattern the existing event specs use (avoids
 * `npm run wp-env run`'s separate Docker compose, which conflicts on port
 * with the environment `pretest:e2e` already started).
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page                    Playwright page object, already on an admin page.
 * @param {Object}                          options                 Seed options.
 * @param {string}                          options.title           Event title.
 * @param {number}                          [options.daysFromNow]   Days from now the event starts, derived from "now"
 *                                                                  rather than a hard-coded date.
 * @param {number}                          [options.hour]          Local start hour (24h).
 * @param {number}                          [options.durationHours] Event duration in hours.
 * @param {string}                          [options.timezone]      IANA timezone string.
 * @param {string}                          [options.status]        Post status ('publish' or 'draft').
 * @param {string}                          [options.content]       Post content, defaulting to an event-date block
 *                                                                  plus a paragraph.
 *
 * @return {Promise<{eventId: number, link: string}>} The created event's ID and permalink.
 */
async function seedEventWithDatetime( page, {
	title,
	daysFromNow = 7,
	hour = 18,
	durationHours = 2,
	timezone = 'America/New_York',
	status = 'publish',
	content = DEFAULT_EVENT_CONTENT,
} = {} ) {
	// Built entirely from UTC fields, deliberately: `gatherpress_datetime`
	// wants a naive "local wall clock" string paired with the `timezone`
	// field, and the machine's own system timezone (whatever the host happens
	// to be set to) must never leak into which calendar day that string names.
	// Mixing local-timezone `Date` getters/setters with `toISOString()`'s UTC
	// conversion shifts the calendar day near midnight on any host not itself
	// running UTC.
	const now = new Date();
	const daysAhead = new Date( now.getTime() + ( daysFromNow * 24 * 60 * 60 * 1000 ) );
	const start = new Date( Date.UTC(
		daysAhead.getUTCFullYear(),
		daysAhead.getUTCMonth(),
		daysAhead.getUTCDate(),
		hour, 0, 0
	) );
	const end = new Date( start.getTime() + ( durationHours * 60 * 60 * 1000 ) );
	const pad = ( value ) => String( value ).padStart( 2, '0' );
	const fmt = ( date ) => `${ date.getUTCFullYear() }-${ pad( date.getUTCMonth() + 1 ) }-` +
		`${ pad( date.getUTCDate() ) } ${ pad( date.getUTCHours() ) }:00:00`;

	const res = await restRequest( page, {
		path: '/wp/v2/gatherpress_events',
		method: 'POST',
		data: {
			title,
			status,
			content,
			meta: {
				gatherpress_datetime: JSON.stringify( {
					dateTimeStart: fmt( start ),
					dateTimeEnd: fmt( end ),
					timezone,
				} ),
			},
		},
	} );

	return {
		eventId: res.id,
		link: res.link,
		// Plain components so a caller can rebuild the exact local anchor
		// date/time this event was seeded with, e.g. to compute an expected
		// occurrence ID or an expected rendered date.
		anchor: {
			year: start.getUTCFullYear(),
			month: start.getUTCMonth(),
			day: start.getUTCDate(),
			hour: start.getUTCHours(),
		},
	};
}

/**
 * Navigate to an event's editor and get it into a state where the "Event
 * settings" panel's controls (including Recurrence) are visible.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page    Playwright page object.
 * @param {number}                          eventId Event post ID.
 *
 * @return {Promise<void>}
 */
async function openEventEditor( page, eventId ) {
	await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit` );
	await page.waitForLoadState( 'load' );
	await dismissEditorModals( page );
	await openEventSettingsPanel( page );
}

/**
 * Set an event's own Time Zone control, inside the "Event settings" panel.
 *
 * This is the three-click organizer path: open Event settings
 * (already open by the time a spec reaches this helper), open the Time Zone
 * dropdown, pick a manual UTC offset.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page  Playwright page object.
 * @param {string}                          label Visible option label, e.g. `UTC-5`.
 *
 * @return {Promise<void>}
 */
async function setEventTimezone( page, label ) {
	const control = page.getByLabel( 'Time Zone' );
	const [ value ] = await control.selectOption( { label } );

	// Wait on the control actually holding the new value rather than on a
	// fixed delay. Anything downstream that depends on the panel re-rendering
	// off this change is asserted by a retrying web-first assertion at the
	// call site, so no sleep is needed here either.
	await expect( control ).toHaveValue( value );
}

/**
 * Turn on the "Repeat" toggle in the Recurrence panel.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 *
 * @return {Promise<void>}
 */
async function enableRecurrence( page ) {
	const repeatToggle = page.getByRole( 'checkbox', { name: 'Repeat' } );

	await expect( repeatToggle ).toBeEnabled( { timeout: 10000 } );

	if ( ! ( await repeatToggle.isChecked() ) ) {
		await repeatToggle.click();
	}
}

/**
 * Set the recurrence frequency and repeat interval.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page                Playwright page object.
 * @param {Object}                          options             Frequency options.
 * @param {string}                          [options.frequency] One of `daily`, `weekly`, `monthly`.
 * @param {number}                          [options.interval]  Repeat-every interval.
 *
 * @return {Promise<void>}
 */
async function setFrequency( page, { frequency, interval } = {} ) {
	if ( frequency ) {
		const control = page.getByLabel( 'Frequency' );

		await control.selectOption( frequency );

		// Switching frequency conditionally mounts/unmounts the weekday and
		// monthly controls below it. Wait on the select committing the new
		// value; the helpers that reach for those conditional controls
		// (`setWeekdays`, `setMonthly`) each wait for their own control to be
		// visible, so React's mount is covered by a real condition rather
		// than by a fixed delay.
		await expect( control ).toHaveValue( frequency );
	}

	if ( interval ) {
		await page.getByLabel( 'Repeat every' ).fill( String( interval ) );
		await page.keyboard.press( 'Tab' );
	}
}

/**
 * Check the given weekday checkboxes in a weekly rule's weekday picker.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 * @param {string[]}                        days Full weekday names, e.g. `[ 'Tuesday', 'Thursday' ]`.
 *
 * @return {Promise<void>}
 */
async function setWeekdays( page, days ) {
	for ( const day of days ) {
		const checkbox = page.getByRole( 'checkbox', { name: day, exact: true } );
		await expect( checkbox ).toBeVisible();

		if ( ! ( await checkbox.isChecked() ) ) {
			await checkbox.check();
		}
	}
}

/**
 * Configure a monthly rule's mode and companion fields.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page              Playwright page object.
 * @param {Object}                          options           Monthly options.
 * @param {string}                          options.mode      One of `day_of_month`, `nth_weekday`.
 * @param {number}                          [options.day]     Day of the month, when `mode` is `day_of_month`.
 * @param {string}                          [options.ordinal] Ordinal label ('First'…'Fourth', 'Last'), when
 *                                                            `mode` is `nth_weekday`.
 * @param {string}                          [options.weekday] Full weekday name, when `mode` is `nth_weekday`.
 *
 * @return {Promise<void>}
 */
async function setMonthly( page, { mode, day, ordinal, weekday } = {} ) {
	const repeatBy = page.getByLabel( 'Repeat by' );

	// The monthly controls only mount once `Frequency` is monthly (or
	// yearly); waiting for this one proves that mount landed.
	await expect( repeatBy ).toBeVisible();
	await repeatBy.selectOption(
		'day_of_month' === mode
			? { label: 'Day of the month' }
			: { label: 'Day of the week' }
	);

	// Switching mode conditionally swaps the day-of-month field for the
	// week/day ordinal pair. Wait for the field that mode actually mounts,
	// rather than sleeping and hoping.
	await expect(
		'day_of_month' === mode
			? page.getByLabel( 'Day of the month' )
			: page.getByLabel( 'Week' )
	).toBeVisible();

	if ( 'day_of_month' === mode && day ) {
		await page.getByLabel( 'Day of the month' ).fill( String( day ) );
		await page.keyboard.press( 'Tab' );
	}

	if ( 'nth_weekday' === mode ) {
		if ( ordinal ) {
			await page.getByLabel( 'Week' ).selectOption( { label: ordinal } );
		}

		if ( weekday ) {
			await page.getByLabel( 'Day', { exact: true } ).selectOption( { label: weekday } );
		}
	}
}

/**
 * Configure the series end condition.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page            Playwright page object.
 * @param {Object}                          options         End-condition options.
 * @param {string}                          options.endType One of `never`, `until`, `count`.
 * @param {string}                          [options.until] End date, `YYYY-MM-DD`, when `endType` is `until`.
 * @param {number}                          [options.count] Occurrence count, when `endType` is `count`.
 *
 * @return {Promise<void>}
 */
async function setEndCondition( page, { endType, until, count } = {} ) {
	const labels = { never: 'Never', until: 'On date', count: 'After' };

	await page.getByLabel( 'Ends' ).selectOption( { label: labels[ endType ] } );

	if ( 'until' === endType && until ) {
		await page.getByLabel( 'End date' ).fill( until );
		await page.keyboard.press( 'Tab' );
	}

	if ( 'count' === endType && count ) {
		await page.getByLabel( 'Number of occurrences' ).fill( String( count ) );
		await page.keyboard.press( 'Tab' );
	}
}

/**
 * Save the currently open event (works for both "Publish" a draft and
 * "Save"/"Update" an already-published post, because the block editor's
 * header button carries whichever label matches the post's current status).
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object.
 *
 * @return {Promise<void>}
 */
async function saveEvent( page ) {
	const headerButton = page
		.getByRole( 'button', { name: /^(Save|Update|Publish)$/ } )
		.first();

	await expect( headerButton ).toBeEnabled( { timeout: 10000 } );
	await headerButton.click();

	// Publishing a draft opens a confirmation panel with its own "Publish"
	// button; saving an already-published post does not. Handle both.
	const finalPublishButton = page
		.getByRole( 'button', { name: 'Publish', exact: true } )
		.last();

	if ( await finalPublishButton.isVisible( { timeout: 2000 } ).catch( () => false ) ) {
		await finalPublishButton.click();
	}

	await page.waitForSelector( '.components-snackbar', { timeout: 20000 } );

	// The snackbar confirms the save request resolved, but the editor's own
	// entity-record save can still be settling a moment later and briefly
	// re-sync the Recurrence panel's local state to what was just saved.
	// Editing again before that settles can race a subsequent change to
	// arrive first and get clobbered by the delayed resync.
	//
	// Wait on the store's own save state rather than on a fixed delay: a
	// sleep long enough to hide the race today is a sleep that stops hiding
	// it on a slower machine, and `retries` in CI would then mask the
	// resulting flake instead of surfacing it.
	await page.waitForFunction(
		() => {
			const editor = window.wp?.data?.select( 'core/editor' );
			const core = window.wp?.data?.select( 'core' );

			if ( ! editor || ! core ) {
				return false;
			}

			return (
				! editor.isSavingPost() &&
				! editor.isAutosavingPost() &&
				! editor.isEditedPostDirty() &&
				! core.isSavingEntityRecord(
					'postType',
					editor.getCurrentPostType(),
					editor.getCurrentPostId()
				)
			);
		},
		undefined,
		{ timeout: 20000 }
	);
}

/**
 * Read an event's `gatherpress_recurrence` blob and derived mirrors via the
 * REST API.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page    Playwright page object, already on an admin page.
 * @param {number}                          eventId Event post ID.
 *
 * @return {Promise<Object>} The event's `meta` object.
 */
async function getEventMeta( page, eventId ) {
	const res = await restRequest( page, {
		path: `/wp/v2/gatherpress_events/${ eventId }?context=edit`,
	} );

	return res.meta;
}

/**
 * Create a page containing the GatherPress "Event Query Loop" variation,
 * scoped to upcoming events, and return its front-end URL. Mirrors the
 * `namespace`/`query` shape the block's own starter pattern
 * (`src/variations/core/query/patterns/index.js`) writes.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page                      Playwright page object, already on an
 *                                                                    admin page.
 * @param {string}                          title                     Page title.
 * @param {Object}                          [options]                 List options.
 * @param {number}                          [options.perPage]         Rows per page for the loop's query.
 * @param {boolean}                         [options.withPostContent] Whether each row also renders the event's
 *                                                                    own content blocks.
 * @param {string}                          [options.search]          Search term scoping the loop to one series.
 *
 * @return {Promise<{pageId: number, link: string}>} The created page's ID and permalink.
 */
async function createUpcomingEventsListPage(
	page,
	title,
	{ perPage = 20, withPostContent = false, search = '' } = {}
) {
	// `core/post-content` is what pulls each row's own blocks into the loop,
	// the RSVP block and the online-event link among them. That is the surface
	// beat 5 needs: one post rendered many times, each row carrying its own
	// occurrence identity.
	// The event's own content already opens with an `event-date` block, so the
	// row adds one only when it is not pulling the content in, because two of
	// them in one row would leave every date lookup ambiguous.
	const rowBlocks = '<!-- wp:post-title {"isLink":true} /-->' +
		( withPostContent ? '<!-- wp:post-content /-->' : '<!-- wp:gatherpress/event-date {"isLink":true} /-->' );

	// A `search` term scopes the loop to one series. Without it the list is
	// every upcoming event on the site, so a spec asserting the *exact* row
	// vector of one series is at the mercy of how many rows other events push
	// past `perPage`, which makes it fail for a reason that has nothing to do
	// with what it is named for.
	const content = `<!-- wp:query {"queryId":1,"query":{"perPage":${ perPage },"pages":0,"offset":0,` +
		`"search":${ JSON.stringify( search ) },` +
		'"postType":"gatherpress_event","gatherpress_event_query":"upcoming",' +
		'"include_unfinished":1,"order":"asc","orderBy":"datetime","inherit":false},' +
		'"namespace":"gatherpress-event-query","className":"gatherpress-event-query"} -->' +
		'<div class="wp-block-query gatherpress-event-query"><!-- wp:post-template -->' +
		rowBlocks +
		'<!-- /wp:post-template --></div><!-- /wp:query -->';

	const res = await restRequest( page, {
		path: '/wp/v2/pages',
		method: 'POST',
		data: { title, status: 'publish', content },
	} );

	return { pageId: res.id, link: res.link };
}

/**
 * Locate every rendered loop row belonging to one series, by its title.
 *
 * Returned as a Playwright `Locator` rather than as read values, because
 * beat 5 has to re-read the same rows after a client-side state change without
 * reloading the page.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page  Playwright page object, already on the list page.
 * @param {string}                          title The series' post title.
 *
 * @return {import('@playwright/test').Locator} The matching rows, in rendered order.
 */
function getSeriesRows( page, title ) {
	return page
		.locator( '.wp-block-post-template > li, .wp-block-post-template > .wp-block-post' )
		.filter( { has: page.locator( '.wp-block-post-title', { hasText: title } ) } );
}

/**
 * Read the whole per-row vector off a set of loop rows.
 *
 * The vector, not one row, is the point. A single-row assertion about an
 * occurrence's RSVP state passes just as happily when *every* row is
 * attending, which is precisely the collapsed-store defect beat 5 exists to
 * catch. Reading every row lets a spec assert "this one, and not the
 * others."
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Locator} rows Rows returned by `getSeriesRows()`.
 *
 * @return {Promise<Array<{dateText: string, href: string|null, recurrenceId: string|undefined,
 *                         onlineTag: string|null, onlineHref: string|null,
 *                         rsvpStatus: string|null}>>} One entry per row.
 */
async function readSeriesRowVector( rows ) {
	// Read in one `evaluateAll` round trip rather than a locator per field per
	// row. Locator-per-field is not just slower: on a row that carries no
	// online-event block at all, every miss waits out the full action timeout
	// before it can be caught, so a twelve-row list spends minutes proving
	// that nothing is there.
	return rows.evaluateAll( ( elements ) =>
		elements.map( ( row ) => {
			const wrapper = row.querySelector( '.gatherpress-online-event__link' );
			const text = row.querySelector( '.gatherpress-online-event__text' );
			const rawContext = wrapper ? wrapper.getAttribute( 'data-wp-context' ) : null;
			// The RSVP block renders every status' inner blocks and hides all
			// but the current one, so the visible `data-rsvp-status` wrapper is
			// this row's RSVP state, server-rendered on load and swapped by
			// `callbacks.renderRsvpBlock` after an in-page RSVP.
			const activeStatus = Array.from( row.querySelectorAll( '[data-rsvp-status]' ) ).find(
				( node ) => ! node.classList.contains( 'gatherpress--is-hidden' )
			);
			const dateEl = row.querySelector( '.wp-block-gatherpress-event-date' );
			const titleLink = row.querySelector( '.wp-block-post-title a' );

			return {
				dateText: dateEl ? dateEl.innerText.trim() : '',
				href: titleLink ? titleLink.getAttribute( 'href' ) : null,
				recurrenceId: rawContext ? JSON.parse( rawContext ).recurrenceId : undefined,
				onlineTag: text ? text.tagName : null,
				onlineHref: text ? text.getAttribute( 'href' ) : null,
				rsvpStatus: activeStatus ? activeStatus.dataset.rsvpStatus : null,
			};
		} )
	);
}

/**
 * Give an event an online-event link and the `online-event` venue term.
 *
 * Both are required before `gatherpress/online-event-link` renders anything:
 * the wrapping `gatherpress/online-event` block returns an empty string unless
 * the event carries the term, and the link itself comes from the meta. The
 * term is a real, pre-existing term in the shadow venue taxonomy, looked up by
 * its slug rather than created here.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page    Playwright page object, already on an admin page.
 * @param {number}                          eventId Event post ID.
 * @param {string}                          url     The private meeting URL to store.
 *
 * @return {Promise<void>}
 */
async function makeEventOnline( page, eventId, url ) {
	const terms = await restRequest( page, { path: '/wp/v2/_gatherpress_venue?slug=online-event' } );

	expect( terms, 'the online-event venue term exists on this site' ).toHaveLength( 1 );

	await restRequest( page, {
		path: `/wp/v2/gatherpress_events/${ eventId }`,
		method: 'POST',
		data: {
			meta: { gatherpress_online_event_link: url },
			_gatherpress_venue: [ terms[ 0 ].id ],
		},
	} );
}

/**
 * Visit the upcoming-events list page and read its rendered occurrence rows.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page    Playwright page object.
 * @param {string}                          pageUrl Front-end URL of the list page.
 *
 * @return {Promise<Array<{title: string, dateText: string, href: string}>>} One entry per rendered row.
 */
async function getUpcomingRows( page, pageUrl ) {
	await page.goto( pageUrl );
	await page.waitForLoadState( 'load' );

	const rows = page.locator( '.wp-block-post-template > li, .wp-block-post-template > .wp-block-post' );
	const count = await rows.count();
	const results = [];

	for ( let i = 0; i < count; i++ ) {
		const row = rows.nth( i );
		const link = row.locator( 'a' ).first();
		results.push( {
			title: ( await row.locator( '.wp-block-post-title' ).innerText().catch( () => '' ) ).trim(),
			dateText: ( await row.locator( '.wp-block-gatherpress-event-date' ).innerText().catch( () => '' ) ).trim(),
			href: await link.getAttribute( 'href' ).catch( () => null ),
		} );
	}

	return results;
}

/**
 * Delete a post via the REST API, tolerating a post type whose collection
 * base differs from its slug (e.g. `gatherpress_events` for
 * `gatherpress_event`).
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page       Playwright page object.
 * @param {number}                          id         Post ID to delete.
 * @param {string}                          [restBase] REST collection base, defaults to `gatherpress_events`.
 *
 * @return {Promise<void>}
 */
async function deletePost( page, id, restBase = 'gatherpress_events' ) {
	await restRequest( page, {
		path: `/wp/v2/${ restBase }/${ id }?force=true`,
		method: 'DELETE',
	} );
}

/**
 * Set the site timezone to a named tz-database identifier via the REST
 * settings endpoint. Recurrence requires a named timezone: tests that author
 * a rule must call this before touching the Recurrence panel.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object, already on an admin page.
 * @param {string}                          tz   IANA timezone string, e.g. `America/New_York`.
 *
 * @return {Promise<void>}
 */
async function setSiteTimezone( page, tz ) {
	await restRequest( page, {
		path: '/wp/v2/settings',
		method: 'POST',
		data: { timezone: tz },
	} );
}

/**
 * Read the site's currently configured timezone via the REST settings
 * endpoint, so a spec that changes it can put it back.
 *
 * Several specs in this suite must run under a named timezone, and
 * other e2e files share this WordPress install. Leaving the site on
 * whatever the last recurrence spec set is a cross-file side effect.
 *
 * @since 0.36.0
 *
 * @param {import('@playwright/test').Page} page Playwright page object, already on an admin page.
 *
 * @return {Promise<string>} The site's `timezone` setting.
 */
async function getSiteTimezone( page ) {
	const settings = await restRequest( page, { path: '/wp/v2/settings' } );

	return settings.timezone;
}

/**
 * Zero out the time portion of a Date, in local time.
 *
 * @since 0.36.0
 *
 * @param {Date} date Date to normalize.
 *
 * @return {Date} A new Date at local midnight of the same calendar day.
 */
function dateOnly( date ) {
	return new Date( date.getFullYear(), date.getMonth(), date.getDate() );
}

/**
 * Compute a `daysFromNow` offset (for `seedEventWithDatetime`) that lands on
 * a specific UTC calendar weekday, at least `minDays` out.
 *
 * A spec that seeds an anchor date and then asserts a *different*, later
 * occurrence's date needs the anchor itself to fall outside the rule's own
 * weekdays. Otherwise the anchor and the first occurrence coincide by
 * chance on any day the test happens to run, and a broken resolution path
 * would go uncaught. Picking the offset dynamically (never a pinned
 * calendar date) keeps the guarantee true on every run.
 *
 * @since 0.36.0
 *
 * @param {number} targetWeekday UTC day-of-week to land on, 0 (Sunday) through 6 (Saturday).
 * @param {number} [minDays]     Minimum number of days out.
 *
 * @return {number} Days from now, suitable for `seedEventWithDatetime`'s `daysFromNow`.
 */
function daysUntilUtcWeekday( targetWeekday, minDays = 3 ) {
	const now = new Date();

	for ( let d = minDays; d < minDays + 7; d++ ) {
		const candidate = new Date( now.getTime() + ( d * 24 * 60 * 60 * 1000 ) );

		if ( candidate.getUTCDay() === targetWeekday ) {
			return d;
		}
	}

	throw new Error( 'No matching weekday found within a week of the minimum.' );
}

/**
 * Find the first date on or after `from` whose day-of-week is in `weekdays`.
 *
 * Mirrors `Expander::next_scanned_date()` for an INTERVAL=1 weekly rule: the
 * scan starts at the anchor date itself (inclusive), not the day after.
 *
 * @since 0.36.0
 *
 * @param {Date}     from     Date to scan forward from (inclusive).
 * @param {number[]} weekdays Day-of-week numbers, 0 (Sunday) through 6 (Saturday).
 *
 * @return {Date} The first matching date.
 */
function nextMatchingWeekday( from, weekdays ) {
	const date = dateOnly( from );

	for ( let step = 0; 14 >= step; step++ ) {
		if ( weekdays.includes( date.getDay() ) ) {
			return date;
		}
		date.setDate( date.getDate() + 1 );
	}

	throw new Error( 'No matching weekday found within two weeks.' );
}

/**
 * Find the last date in a given month whose day-of-week matches `weekday`.
 *
 * Mirrors `Expander::nth_weekday_of_month()` with `ordinal === -1`.
 *
 * @since 0.36.0
 *
 * @param {number} year    Four-digit year.
 * @param {number} month   Zero-based month (0 = January), matching `Date`.
 * @param {number} weekday Day-of-week number, 0 (Sunday) through 6 (Saturday).
 *
 * @return {Date} The last matching date in that month.
 */
function lastWeekdayOfMonth( year, month, weekday ) {
	const date = new Date( year, month + 1, 0 ); // Last day of the month.

	while ( date.getDay() !== weekday ) {
		date.setDate( date.getDate() - 1 );
	}

	return date;
}

/**
 * Resolve the first occurrence of a monthly "last <weekday>" rule on or after an anchor.
 *
 * Mirrors `Expander::next_monthly_date()`: the anchor's own month is tried
 * first, and only rolls to the next month when that month's last-weekday date
 * falls before the anchor.
 *
 * @since 0.36.0
 *
 * @param {Date}   anchor  Series anchor date (date-only).
 * @param {number} weekday Day-of-week number, 0 (Sunday) through 6 (Saturday).
 *
 * @return {Date} The first matching occurrence date.
 */
function firstLastWeekdayOnOrAfter( anchor, weekday ) {
	const candidate = lastWeekdayOfMonth(
		anchor.getFullYear(),
		anchor.getMonth(),
		weekday
	);

	if ( candidate >= dateOnly( anchor ) ) {
		return candidate;
	}

	return lastWeekdayOfMonth( anchor.getFullYear(), anchor.getMonth() + 1, weekday );
}

/**
 * Get the Monday-start week bucket a date falls in.
 *
 * Mirrors `Expander::week_index()`. A weekly rule's interval is counted in
 * Monday-start week buckets, never in seven-day deltas from the anchor.
 *
 * @since 0.36.0
 *
 * @param {Date} date Date to bucket.
 *
 * @return {number} The bucket index, monotonically increasing with the date.
 */
function weekIndex( date ) {
	const day = dateOnly( date );
	// Days since a Monday epoch. `getDay()` is Sunday-start, so shift it.
	const mondayOffset = ( day.getDay() + 6 ) % 7;

	day.setDate( day.getDate() - mondayOffset );

	return Math.round( day.getTime() / ( 24 * 60 * 60 * 1000 ) );
}

/**
 * Project the occurrence dates of a weekly rule, independently of the server.
 *
 * Mirrors `Expander::matches_weekly()` composed with the day scan: an
 * occurrence is a date on or after the anchor, on one of the rule's weekdays,
 * in a week bucket an exact multiple of `interval` from the anchor's own.
 *
 * Computed here rather than read back from anything the application produced,
 * so an assertion built on it is a specification rather than a transcript of
 * current behavior.
 *
 * @since 0.36.0
 *
 * @param {Date}     anchor   Series anchor date.
 * @param {number[]} weekdays Day-of-week numbers, 0 (Sunday) through 6 (Saturday).
 * @param {number}   interval Week-bucket interval.
 * @param {number}   count    How many occurrence dates to project.
 *
 * @return {Date[]} The projected occurrence dates, in ascending order.
 */
function weeklyOccurrences( anchor, weekdays, interval, count ) {
	const start = dateOnly( anchor );
	const anchorBucket = weekIndex( start );
	const dates = [];
	const cursor = new Date( start.getTime() );

	// A generous scan bound: at the widest interval this suite uses, `count`
	// occurrences never need more than this many days, and a bounded loop
	// fails loudly rather than hanging if that assumption ever breaks.
	for ( let step = 0; ( count * interval * 7 ) + 7 >= step && dates.length < count; step++ ) {
		if (
			weekdays.includes( cursor.getDay() ) &&
			0 === ( weekIndex( cursor ) - anchorBucket ) % interval
		) {
			dates.push( new Date( cursor.getTime() ) );
		}

		cursor.setDate( cursor.getDate() + 1 );
	}

	expect( dates, `projected ${ count } weekly occurrence dates` ).toHaveLength( count );

	return dates;
}

/**
 * Format a date the way the `event-date` block's rendered row leads with it.
 *
 * The block renders a full range ("August 25, 2026 6:00 pm to 8:00 pm EDT");
 * the leading calendar date is the part a projection can predict without
 * duplicating the site's time formatting.
 *
 * @since 0.36.0
 *
 * @param {Date} date Date to format.
 *
 * @return {string} The date as `Month D, YYYY`.
 */
function dateText( date ) {
	return date.toLocaleDateString( 'en-US', {
		month: 'long',
		day: 'numeric',
		year: 'numeric',
	} );
}

/**
 * Extract the leading `Month D, YYYY` from a rendered event-date row.
 *
 * @since 0.36.0
 *
 * @param {string} rendered The row's rendered date text.
 *
 * @return {string} The leading calendar date, or the whole string when it does not lead with one.
 */
function leadingDateText( rendered ) {
	const match = /^[A-Z][a-z]+ \d{1,2}, \d{4}/.exec( rendered );

	return match ? match[ 0 ] : rendered;
}

/**
 * Build the `Ymd\THis` recurrence ID for a date at a fixed wall-clock time.
 *
 * `Occurrences::recurrence_id()` formats the occurrence's own local start
 * datetime, and every occurrence in a GatherPress rule shares the anchor's
 * time-of-day (the panel has no per-occurrence time control), so pairing a
 * date-only value with the fixed `hhmmss` this suite always seeds with
 * reproduces the server's own identifier.
 *
 * @since 0.36.0
 *
 * @param {Date}   date   Date-only value.
 * @param {string} hhmmss Wall-clock time as `HHmmss`, matching the event's start time.
 *
 * @return {string} The recurrence ID.
 */
function toRecurrenceId( date, hhmmss ) {
	const year = String( date.getFullYear() ).padStart( 4, '0' );
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );

	return `${ year }${ month }${ day }T${ hhmmss }`;
}

module.exports = {
	dismissEditorModals,
	restRequest,
	gotoAdmin,
	openEventSettingsPanel,
	seedEventWithDatetime,
	openEventEditor,
	setEventTimezone,
	enableRecurrence,
	setFrequency,
	setWeekdays,
	setMonthly,
	setEndCondition,
	saveEvent,
	getEventMeta,
	createUpcomingEventsListPage,
	getUpcomingRows,
	getSeriesRows,
	readSeriesRowVector,
	makeEventOnline,
	deletePost,
	setSiteTimezone,
	getSiteTimezone,
	dateOnly,
	daysUntilUtcWeekday,
	nextMatchingWeekday,
	weekIndex,
	weeklyOccurrences,
	dateText,
	leadingDateText,
	lastWeekdayOfMonth,
	firstLastWeekdayOnOrAfter,
	toRecurrenceId,
};
