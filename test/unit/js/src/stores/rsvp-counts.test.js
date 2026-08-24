/**
 * External dependencies
 */
import {
	afterEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';

/**
 * WordPress dependencies
 */
import { dispatch, resolveSelect, select } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { RSVP_COUNTS_STORE } from '@src/helpers/namespace';
// Import the actual store to register it and get coverage.
import '@src/stores/rsvp-counts';

jest.mock( '@wordpress/api-fetch' );

/**
 * Builds the request path the store fetches for one post.
 *
 * @param {number} postId The event post ID.
 *
 * @return {string} The request path.
 */
const pathFor = ( postId ) =>
	`/gatherpress/v1/event/rsvp-responses?post_id=${ postId }`;

/**
 * Counts the mocked fetches made for one post.
 *
 * The registry resolves selectors asynchronously, so fetches triggered by
 * earlier tests can land during later ones; a global call count races.
 *
 * @param {number} postId The event post ID.
 *
 * @return {number} How many times the post was fetched.
 */
const fetchesFor = ( postId ) =>
	apiFetch.mock.calls.filter(
		( [ arg ] ) => arg.path === pathFor( postId ),
	).length;

describe( 'RSVP counts store', () => {
	afterEach( () => {
		jest.resetAllMocks();
	} );

	describe( 'selectors', () => {
		it( 'getCounts returns null before counts resolve', () => {
			expect( select( RSVP_COUNTS_STORE ).getCounts( 900 ) ).toBeNull();
		} );

		it( 'getCount returns null before counts resolve', () => {
			expect(
				select( RSVP_COUNTS_STORE ).getCount( 900, 'attending' ),
			).toBeNull();
		} );

		it( 'returns received counts, keyed per post', () => {
			dispatch( RSVP_COUNTS_STORE ).receiveCounts( 1, {
				attending: { count: 3 },
			} );
			dispatch( RSVP_COUNTS_STORE ).receiveCounts( 2, {
				attending: { count: 7 },
			} );

			expect( select( RSVP_COUNTS_STORE ).getCounts( 1 ) ).toEqual( {
				attending: { count: 3 },
			} );
			expect(
				select( RSVP_COUNTS_STORE ).getCount( 1, 'attending' ),
			).toBe( 3 );
			expect(
				select( RSVP_COUNTS_STORE ).getCount( 2, 'attending' ),
			).toBe( 7 );
		} );

		it( 'getCount returns null for a status the response lacks', () => {
			dispatch( RSVP_COUNTS_STORE ).receiveCounts( 3, {} );

			expect(
				select( RSVP_COUNTS_STORE ).getCount( 3, 'attending' ),
			).toBeNull();
		} );
	} );

	describe( 'resolvers', () => {
		it( 'getCounts fetches once and stores the response data', async () => {
			apiFetch.mockResolvedValue( {
				data: { attending: { count: 5 } },
			} );

			const counts = await resolveSelect( RSVP_COUNTS_STORE ).getCounts(
				10,
			);

			expect( counts ).toEqual( { attending: { count: 5 } } );
			expect( fetchesFor( 10 ) ).toBe( 1 );
		} );

		it( 'getCounts stores an empty object when the response has no data', async () => {
			apiFetch.mockResolvedValue( {} );

			expect(
				await resolveSelect( RSVP_COUNTS_STORE ).getCounts( 11 ),
			).toEqual( {} );
		} );

		it( 'getCounts stores an empty object when the fetch fails', async () => {
			apiFetch.mockRejectedValue( new Error( 'offline' ) );

			expect(
				await resolveSelect( RSVP_COUNTS_STORE ).getCounts( 12 ),
			).toEqual( {} );
		} );

		it( 'getCounts skips fetching without a post ID', async () => {
			await resolveSelect( RSVP_COUNTS_STORE ).getCounts( 0 );

			expect( fetchesFor( 0 ) ).toBe( 0 );
		} );

		it( 'getCount delegates to the getCounts resolution', async () => {
			apiFetch.mockResolvedValue( {
				data: { waiting_list: { count: 2 } },
			} );

			expect(
				await resolveSelect( RSVP_COUNTS_STORE ).getCount(
					13,
					'waiting_list',
				),
			).toBe( 2 );
			expect( fetchesFor( 13 ) ).toBe( 1 );
		} );
	} );
} );
