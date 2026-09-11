/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';

/**
 * Mock the Interactivity API store so the module can read
 * `gatherPressState.eventApiUrl` at import time without a real store. The
 * state object is created inside the factory (jest.mock is hoisted above
 * any const in this file) and re-exported as `__mockState` so individual
 * tests can mutate it (e.g. remove `i18n` to cover the missing-strings
 * guard in announceRsvpSuccess()).
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const state = {
			eventApiUrl: 'https://example.test/wp-json/gatherpress/v1',
		};

		return {
			store: jest.fn( () => ( { state } ) ),
			__mockState: state,
		};
	},
	{ virtual: true }
);

/**
 * Mock the a11y script module so announcement tests can assert on `speak()`
 * without a DOM live region.
 *
 * Deliberately not `{ virtual: true }`: `@wordpress/a11y` ships a `require`
 * entry point, so Jest resolves it, and the flag would key the mock to the
 * bare specifier instead of that resolved path. Because the module ID for
 * each `(importer, name)` pair is memoized on a `Resolver` that the test
 * worker shares across files, another file loading `src/helpers/interactivity.js`
 * first would cache the resolved path and leave this registration unmatched.
 * The module under test would then receive the real `speak()` while these
 * tests watch the mock. See `test/unit/js/mock-hygiene.test.js`.
 */
jest.mock( '@wordpress/a11y', () => ( {
	speak: jest.fn(),
} ) );

/**
 * WordPress dependencies
 */
import { speak } from '@wordpress/a11y';
// eslint-disable-next-line import/named -- `__mockState` only exists on the virtual interactivity mock above.
import { __mockState as mockInteractivityState } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import {
	activateOnSpace,
	sendRsvpApiRequest,
	getNonce,
	getPostKey,
	withRecurrenceId,
} from '@src/helpers/interactivity';

/**
 * English source strings mirroring Assets::add_interactivity_state(), so the
 * announcement tests assert the fully assembled message.
 */
const I18N_FIXTURE = {
	rsvpAttending: 'Your RSVP was updated. You are attending.',
	rsvpWaitingList: 'Your RSVP was updated. You are on the waiting list.',
	rsvpNotAttending: 'Your RSVP was updated. You are not attending.',
	attendeeCountSingular: '%d attendee.',
	attendeeCountPlural: '%d attendees.',
	onlineLinkReady: 'The event link is now available on this page.',
	rsvpFailed: 'Sorry, there was an issue processing your RSVP. Please try again.',
};

/**
 * Regression coverage for #1769 — sendRsvpApiRequest must not throw when a
 * success-shaped response is missing some or all of `res.responses`. A partial
 * payload (server error, proxy/WAF rewrite) used to read `.count` off undefined
 * and leave the interactivity state inconsistent.
 */
describe( 'sendRsvpApiRequest', () => {
	let state;
	let rsvpPayload;

	beforeEach( () => {
		// Reset the module-level nonce cache so each test fetches fresh.
		getNonce.clearCache();

		state = { posts: { 123: {} } };

		// alert() is not implemented in jsdom; stub it so the failure path
		// (if reached) doesn't throw a "not implemented" error.
		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			// POST /rsvp.
			return Promise.resolve( {
				status: 200,
				json: () => Promise.resolve( rsvpPayload ),
			} );
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
	} );

	it( 'reads counts from a well-formed responses object', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 2,
			anonymous: false,
			online_link: '',
			responses: {
				attending: { count: 7 },
				waiting_list: { count: 3 },
				not_attending: { count: 1 },
			},
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 2, anonymous: false },
			state
		);

		expect( state.posts[ 123 ].eventResponses ).toEqual( {
			attending: 7,
			waitingList: 3,
			notAttending: 1,
		} );
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'falls back to 0 for every count when responses is missing entirely', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		// The key assertion: no throw, and the state is still updated with
		// safe zeroed counts rather than left half-written.
		expect( state.posts[ 123 ].eventResponses ).toEqual( {
			attending: 0,
			waitingList: 0,
			notAttending: 0,
		} );
		expect( state.posts[ 123 ].currentUser.status ).toBe( 'attending' );
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'invokes onSuccess with the response payload on success', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
		};

		const onSuccess = jest.fn();

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state,
			onSuccess
		);

		expect( onSuccess ).toHaveBeenCalledWith( rsvpPayload );
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'does not report a successful request as failed when onSuccess throws (#1719)', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
		};

		const onSuccess = jest.fn( () => {
			throw new TypeError( 'UI update failed' );
		} );

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state,
			onSuccess
		);

		// The request succeeded, so the user-facing failure alert must not
		// fire; the UI error is only logged for debugging.
		expect( window.alert ).not.toHaveBeenCalled();
		// eslint-disable-next-line no-console
		expect( console.warn ).toHaveBeenCalledWith(
			'RSVP post-success UI update failed:',
			expect.any( TypeError )
		);
		// State was still updated before the callback threw.
		expect( state.posts[ 123 ].currentUser.status ).toBe( 'attending' );
	} );

	it( 'falls back to 0 only for the missing sub-keys of a partial responses object', async () => {
		rsvpPayload = {
			success: true,
			status: 'not_attending',
			guests: 0,
			anonymous: false,
			responses: {
				// attending present, the other two missing.
				attending: { count: 4 },
			},
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'not_attending', guests: 0, anonymous: false },
			state
		);

		expect( state.posts[ 123 ].eventResponses ).toEqual( {
			attending: 4,
			waitingList: 0,
			notAttending: 0,
		} );
	} );
} );

/**
 * Screen-reader announcements for successful RSVP updates (WCAG 4.1.3).
 * The message is assembled from the API response inside
 * announceRsvpSuccess() and spoken via the core a11y module's polite
 * live region.
 */
describe( 'sendRsvpApiRequest announcements', () => {
	let state;
	let rsvpPayload;

	beforeEach( () => {
		getNonce.clearCache();

		// Clear any speak() history from other suites so an earlier
		// matching call can't produce a false positive here.
		speak.mockClear();

		state = { posts: { 123: {} } };
		mockInteractivityState.i18n = { ...I18N_FIXTURE };

		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			return Promise.resolve( {
				status: 200,
				json: () => Promise.resolve( rsvpPayload ),
			} );
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
		delete mockInteractivityState.i18n;
	} );

	it( 'announces the attending status with a singular attendee count', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			responses: { attending: { count: 1 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 1 attendee.',
			'polite'
		);
	} );

	it( 'uses the plural template when more than one attendee', async () => {
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			responses: { attending: { count: 7 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 7 attendees.',
			'polite'
		);
	} );

	it( 'announces the response status when the server bumps to the waiting list', async () => {
		// The user requested attending, but the event is full and the
		// server answered with waiting_list — the announcement must
		// reflect the actual resulting status.
		rsvpPayload = {
			success: true,
			status: 'waiting_list',
			guests: 0,
			anonymous: false,
			responses: { attending: { count: 5 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are on the waiting list. 5 attendees.',
			'polite'
		);
	} );

	it( 'appends the online-link sentence when the link is revealed by this update', async () => {
		// The online-event-link block initialized with no visible link:
		// this response transitions it from unavailable to available.
		state.posts[ 123 ].onlineEventLink = '';

		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			online_link: 'https://meet.example.test/room',
			responses: { attending: { count: 1 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 1 attendee. The event link is now available on this page.',
			'polite'
		);
	} );

	it( 'does not re-announce a link that was already visible', async () => {
		// e.g. a guest-count update while attending: the response still
		// carries online_link, but the link did not just appear.
		state.posts[ 123 ].onlineEventLink = 'https://meet.example.test/room';

		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 1,
			anonymous: false,
			online_link: 'https://meet.example.test/room',
			responses: { attending: { count: 1 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 1, anonymous: false },
			state
		);

		// toHaveBeenCalledWith only proves *some* call matched, and the
		// expected string here is identical to the one the singular-count
		// test produces. Pinning the call count makes this assert the
		// absence of the link sentence rather than leaning on the
		// mockClear() in beforeEach to keep history empty.
		expect( speak ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 1 attendee.',
			'polite'
		);
	} );

	it( 'does not mention the link when the online-event-link block never initialized', async () => {
		// No onlineEventLink key in state: the block is not on this page,
		// so "available on this page" would be false.
		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			online_link: 'https://meet.example.test/room',
			responses: { attending: { count: 1 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		// Same reasoning as the already-visible case above: the count pins
		// that no second, link-bearing announcement was made.
		expect( speak ).toHaveBeenCalledTimes( 1 );
		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 1 attendee.',
			'polite'
		);
	} );

	it( 'replaces positional placeholders that translations may use', async () => {
		mockInteractivityState.i18n.attendeeCountPlural = '%1$d attendees.';

		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			responses: { attending: { count: 3 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).toHaveBeenCalledWith(
			'Your RSVP was updated. You are attending. 3 attendees.',
			'polite'
		);
	} );

	it( 'announces nothing when i18n strings are unavailable', async () => {
		// No strings — announcing hardcoded English on a localized site
		// would be worse than staying silent.
		delete mockInteractivityState.i18n;

		rsvpPayload = {
			success: true,
			status: 'attending',
			guests: 0,
			anonymous: false,
			responses: { attending: { count: 1 } },
		};

		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( speak ).not.toHaveBeenCalled();
		// The request itself still succeeded.
		expect( state.posts[ 123 ].currentUser.status ).toBe( 'attending' );
	} );
} );

/**
 * The Space half of the button keyboard contract for role="button" anchors
 * (dropdown and RSVP-response-toggle triggers). Space must become a click —
 * and must not scroll the page; every other key must pass through untouched
 * so anchors keep their native Enter behavior.
 */
describe( 'activateOnSpace', () => {
	const makeEvent = ( key, repeat = false ) => ( {
		key,
		repeat,
		preventDefault: jest.fn(),
	} );

	it( 'turns Space into a click and prevents the scroll default', () => {
		const ref = { click: jest.fn() };
		const event = makeEvent( ' ' );

		activateOnSpace( event, ref );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( ref.click ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'still prevents scrolling on key repeat but only clicks once', () => {
		// Holding Space fires repeat keydowns; a native button suppresses
		// the scroll default for the whole hold while activating only once.
		const ref = { click: jest.fn() };
		const event = makeEvent( ' ', true );

		activateOnSpace( event, ref );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect( ref.click ).not.toHaveBeenCalled();
	} );

	it( 'leaves Enter alone (anchors handle it natively)', () => {
		const ref = { click: jest.fn() };
		const event = makeEvent( 'Enter' );

		activateOnSpace( event, ref );

		expect( event.preventDefault ).not.toHaveBeenCalled();
		expect( ref.click ).not.toHaveBeenCalled();
	} );

	it( 'leaves Tab alone so focus can move away from the trigger', () => {
		const ref = { click: jest.fn() };
		const event = makeEvent( 'Tab' );

		activateOnSpace( event, ref );

		expect( event.preventDefault ).not.toHaveBeenCalled();
		expect( ref.click ).not.toHaveBeenCalled();
	} );
} );

describe( 'withRecurrenceId', () => {
	it( 'contributes nothing when the row is not rendering an occurrence', () => {
		expect( withRecurrenceId( undefined ) ).toEqual( {} );
	} );

	it( 'contributes nothing when the emitted identifier is empty', () => {
		expect( withRecurrenceId( '' ) ).toEqual( {} );
	} );

	it( 'carries the occurrence the row is rendering', () => {
		expect( withRecurrenceId( '20260903T180000' ) ).toEqual( {
			recurrence_id: '20260903T180000',
		} );
	} );
} );

/**
 * The store key carries occurrence identity on the client: one post rendered many
 * times must not share one entry in `state.posts`.
 */
describe( 'getPostKey', () => {
	it( 'returns the bare post ID for a row with no occurrence', () => {
		expect( getPostKey( 123, undefined ) ).toBe( 123 );
	} );

	it( 'returns the bare post ID when the emitted identifier is empty', () => {
		expect( getPostKey( 123, '' ) ).toBe( 123 );
	} );

	it( 'composes post and occurrence into one key', () => {
		expect( getPostKey( 123, '20260903T180000' ) ).toBe(
			'123:20260903T180000'
		);
	} );

	it( 'cannot collide with a bare post-ID key', () => {
		// A bare key is all digits; a composite one always carries the
		// separator, so no occurrence identifier can forge another post's key.
		expect( String( getPostKey( 123, undefined ) ) ).toMatch( /^\d+$/ );
		expect( getPostKey( 123, '20260903T180000' ) ).toContain( ':' );
	} );
} );

describe( 'sendRsvpApiRequest occurrence scoping', () => {
	let state;

	beforeEach( () => {
		getNonce.clearCache();

		state = { posts: { 123: {} } };
		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			return Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve( {
						success: true,
						status: 'attending',
						guests: 0,
						anonymous: false,
						responses: {},
					} ),
			} );
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
	} );

	const rsvpRequestBody = () => {
		const call = global.fetch.mock.calls.find( ( [ url ] ) =>
			url.endsWith( '/rsvp' )
		);

		return JSON.parse( call[ 1 ].body );
	};

	it( 'sends the occurrence identifier the row is rendering', async () => {
		await sendRsvpApiRequest(
			123,
			{
				status: 'attending',
				guests: 0,
				anonymous: false,
				recurrenceId: '20260903T180000',
			},
			state
		);

		expect( rsvpRequestBody().recurrence_id ).toBe( '20260903T180000' );
	} );

	it( 'writes the response into that occurrence own state slice', async () => {
		await sendRsvpApiRequest(
			123,
			{
				status: 'attending',
				guests: 0,
				anonymous: false,
				recurrenceId: '20260903T180000',
			},
			state
		);

		// The bare post key must be left exactly as it was found: a sibling row
		// of the same series reads through it, and updating it here is the
		// collapse this whole change exists to prevent.
		expect( state.posts[ '123:20260903T180000' ].currentUser.status ).toBe(
			'attending'
		);
		expect( state.posts[ 123 ] ).toEqual( {} );
	} );

	it( 'leaves the request body untouched off an occurrence page', async () => {
		await sendRsvpApiRequest(
			123,
			{ status: 'attending', guests: 0, anonymous: false },
			state
		);

		expect( rsvpRequestBody() ).not.toHaveProperty( 'recurrence_id' );
	} );
} );
