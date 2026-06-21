/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';

/**
 * Mock the Interactivity API store so the module can read
 * `gatherPressState.eventApiUrl` at import time without a real store.
 */
jest.mock(
	'@wordpress/interactivity',
	() => ( {
		store: jest.fn( () => ( {
			state: {
				eventApiUrl: 'https://example.test/wp-json/gatherpress/v1',
			},
		} ) ),
	} ),
	{ virtual: true }
);

/**
 * Internal dependencies
 */
import { sendRsvpApiRequest, getNonce } from '@src/helpers/interactivity';

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
