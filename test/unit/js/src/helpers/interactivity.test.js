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
 */
jest.mock(
	'@wordpress/a11y',
	() => ( {
		speak: jest.fn(),
	} ),
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { speak } from '@wordpress/a11y';
// eslint-disable-next-line import/named -- `__mockState` only exists on the virtual mock above.
import { __mockState as mockInteractivityState } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { sendRsvpApiRequest, getNonce } from '@src/helpers/interactivity';

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
