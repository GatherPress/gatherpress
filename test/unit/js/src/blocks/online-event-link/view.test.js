/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace shares one registry,
 * mirroring the real runtime.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = {
						state: {},
						actions: {},
						callbacks: {},
					};
				}

				const registry = registries[ name ];

				Object.assign( registry.state, config.state );
				Object.assign( registry.actions, config.actions );
				Object.assign( registry.callbacks, config.callbacks );

				return registry;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
		};
	},
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/online-event-link/view';

const SERIES_POST_ID = 42;
const OCCURRENCES = [ '20260823T180000', '20260829T180000' ];

/**
 * Coverage for the online-event-link block's share of the per-occurrence
 * store key.
 *
 * The block reveals a private meeting URL once the visitor is attending, and it
 * reads that reveal out of the same `state.posts` slice the RSVP blocks write.
 * Keyed on the post ID alone, attending one date of a series revealed the link
 * on every other date's row too. That is the worst-consequence instance of
 * this defect, since the link is the thing the RSVP gate exists to protect.
 */
describe( 'online-event-link view per-occurrence keying', () => {
	let state;
	let callbacks;

	beforeEach( () => {
		jest.clearAllMocks();

		( { state, callbacks } = store( 'gatherpress' ) );

		state.posts = {};
		document.body.innerHTML = '';
	} );

	/**
	 * Renders one online-event-link row, as the server renders it for a visitor
	 * with no link yet.
	 *
	 * @return {HTMLElement} The row element.
	 */
	function addRow() {
		const row = document.createElement( 'div' );

		row.innerHTML =
			'<span class="gatherpress-online-event__text">Online event</span>';

		document.body.append( row );

		return row;
	}

	/**
	 * Runs the watch callback for a row under a given block context.
	 *
	 * @param {HTMLElement} row     The row element.
	 * @param {Object}      context The row's block context.
	 *
	 * @return {void}
	 */
	function runFor( row, context ) {
		getElement.mockReturnValue( { ref: row } );
		getContext.mockReturnValue( context );

		callbacks.updateOnlineEventLink();
	}

	/**
	 * Reads whether a row is currently showing a clickable link.
	 *
	 * @param {HTMLElement} row The row element.
	 *
	 * @return {string} The link href, or an empty string when it is plain text.
	 */
	const linkHref = ( row ) =>
		row.querySelector( 'a.gatherpress-online-event__text' )?.href ?? '';

	it( 'reveals the link only on the occurrence that was RSVPd to', () => {
		const rows = [ addRow(), addRow() ];
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		// First pass initializes each row's slice from the DOM without touching it.
		rows.forEach( ( row, index ) => runFor( row, contexts[ index ] ) );

		expect( rows.map( linkHref ) ).toEqual( [ '', '' ] );

		state.posts[ `${ SERIES_POST_ID }:${ OCCURRENCES[ 1 ] }` ].onlineEventLink =
			'https://example.test/meeting';

		rows.forEach( ( row, index ) => runFor( row, contexts[ index ] ) );

		expect( rows.map( linkHref ) ).toEqual( [
			'',
			'https://example.test/meeting',
		] );
	} );

	it( 'keys an ordinary event on the bare post ID and swaps its link both ways', () => {
		const row = addRow();

		runFor( row, { postId: 7 } );

		expect( Object.keys( state.posts ) ).toEqual( [ '7' ] );
		expect( state.posts[ 7 ].onlineEventLink ).toBe( '' );

		state.posts[ 7 ].onlineEventLink = 'https://example.test/meeting';
		runFor( row, { postId: 7 } );

		expect( linkHref( row ) ).toBe( 'https://example.test/meeting' );

		state.posts[ 7 ].onlineEventLink = 'https://example.test/moved';
		runFor( row, { postId: 7 } );

		expect( linkHref( row ) ).toBe( 'https://example.test/moved' );

		state.posts[ 7 ].onlineEventLink = '';
		runFor( row, { postId: 7 } );

		expect( linkHref( row ) ).toBe( '' );
		expect(
			row.querySelector( 'span.gatherpress-online-event__text' )
		).not.toBeNull();
	} );

	it( 'seeds the slice from an already-rendered link', () => {
		const row = document.createElement( 'div' );

		row.innerHTML =
			'<a class="gatherpress-online-event__text" href="https://example.test/live">Join</a>';
		document.body.append( row );

		runFor( row, {
			postId: SERIES_POST_ID,
			recurrenceId: OCCURRENCES[ 0 ],
		} );

		expect(
			state.posts[ `${ SERIES_POST_ID }:${ OCCURRENCES[ 0 ] }` ]
				.onlineEventLink
		).toBe( 'https://example.test/live' );
	} );

	it( 'bails on a row with no post and on a row with no link element', () => {
		const row = addRow();

		runFor( row, {} );

		expect( state.posts ).toEqual( {} );

		row.innerHTML = '';
		runFor( row, { postId: 7 } );

		expect( state.posts[ 7 ] ).not.toHaveProperty( 'onlineEventLink' );
	} );
} );
