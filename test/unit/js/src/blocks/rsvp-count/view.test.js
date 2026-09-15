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
import '@src/blocks/rsvp-count/view';

const SERIES_POST_ID = 42;
const OCCURRENCES = [ '20260823T180000', '20260829T180000' ];

/**
 * Coverage for the RSVP count block's share of the per-occurrence store key.
 *
 * The count block seeds `state.posts[…].eventResponses` from a `data-counts`
 * attribute the server renders per row, then watches that slice. Keyed on the
 * post ID alone, the second row of a series overwrites the first's counts on
 * page load and every row then reports the same number. The server markup is
 * correct throughout, which is what makes it invisible to a PHP test.
 */
describe( 'rsvp-count view per-occurrence keying', () => {
	let state;
	let callbacks;

	beforeEach( () => {
		jest.clearAllMocks();

		( { state, callbacks } = store( 'gatherpress' ) );

		state.posts = {};
		document.body.innerHTML = '';
	} );

	/**
	 * Renders one count block row.
	 *
	 * @param {Object} counts The `data-counts` payload the server rendered.
	 *
	 * @return {HTMLElement} The row element.
	 */
	function addRow( counts ) {
		const row = document.createElement( 'div' );

		row.dataset.status = 'attending';
		row.dataset.counts = JSON.stringify( counts );
		row.dataset.singularLabel = '%d attendee';
		row.dataset.pluralLabel = '%d attendees';
		row.innerHTML = '<span class="gatherpress-rsvp-count__text"></span>';

		document.body.append( row );

		return row;
	}

	/**
	 * Runs one callback for a row under a given block context.
	 *
	 * @param {Function}    callback The registered callback.
	 * @param {HTMLElement} row      The row element.
	 * @param {Object}      context  The row's block context.
	 *
	 * @return {void}
	 */
	function runFor( callback, row, context ) {
		getElement.mockReturnValue( { ref: row } );
		getContext.mockReturnValue( context );

		callback();
	}

	/**
	 * Reads the rendered count text of a row.
	 *
	 * @param {HTMLElement} row The row element.
	 *
	 * @return {string} The visible count text.
	 */
	const countText = ( row ) =>
		row.querySelector( '.gatherpress-rsvp-count__text' ).textContent;

	it( 'seeds and renders a distinct count for every occurrence of one series', () => {
		const rows = [ addRow( { attending: 1 } ), addRow( { attending: 4 } ) ];
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		rows.forEach( ( row, index ) =>
			runFor( callbacks.initRsvpCount, row, contexts[ index ] )
		);
		rows.forEach( ( row, index ) =>
			runFor( callbacks.updateRsvpCount, row, contexts[ index ] )
		);

		expect( rows.map( countText ) ).toEqual( [
			'1 attendee',
			'4 attendees',
		] );
		expect(
			OCCURRENCES.map(
				( recurrenceId ) =>
					state.posts[ `${ SERIES_POST_ID }:${ recurrenceId }` ]
						?.eventResponses?.attending
			)
		).toEqual( [ 1, 4 ] );
	} );

	it( 'keys an ordinary event on the bare post ID', () => {
		const row = addRow( { attending: 2, waiting_list: 1, not_attending: 3 } );

		runFor( callbacks.initRsvpCount, row, { postId: 7 } );
		runFor( callbacks.updateRsvpCount, row, { postId: 7 } );

		expect( Object.keys( state.posts ) ).toEqual( [ '7' ] );
		expect( state.posts[ 7 ].eventResponses ).toEqual( {
			attending: 2,
			waitingList: 1,
			notAttending: 3,
		} );
		expect( countText( row ) ).toBe( '2 attendees' );
	} );

	it( 'falls back to zeroed counts when the server rendered none', () => {
		const row = addRow( {} );

		runFor( callbacks.initRsvpCount, row, { postId: 7 } );

		expect( state.posts[ 7 ].eventResponses ).toEqual( {
			attending: 0,
			waitingList: 0,
			notAttending: 0,
		} );
	} );

	it( 'does not re-seed a slice a live RSVP has already updated', () => {
		const row = addRow( { attending: 1 } );
		const context = {
			postId: SERIES_POST_ID,
			recurrenceId: OCCURRENCES[ 0 ],
		};
		const key = `${ SERIES_POST_ID }:${ OCCURRENCES[ 0 ] }`;

		runFor( callbacks.initRsvpCount, row, context );

		// A visitor RSVPs; the count block re-initializes on a later watch pass.
		// The server's original attribute is gone by then, and the live count
		// must survive rather than being rolled back to the rendered one.
		state.posts[ key ].eventResponses.attending = 9;

		runFor( callbacks.initRsvpCount, row, context );

		expect( state.posts[ key ].eventResponses.attending ).toBe( 9 );
	} );

	it( 'bails on a row with no post and on a row with no element', () => {
		const row = addRow( { attending: 1 } );

		runFor( callbacks.initRsvpCount, row, {} );
		runFor( callbacks.updateRsvpCount, row, {} );

		getElement.mockReturnValue( undefined );
		getContext.mockReturnValue( { postId: SERIES_POST_ID } );
		callbacks.initRsvpCount();
		callbacks.updateRsvpCount();

		expect( state.posts ).toEqual( {} );
	} );

	it( 'renders nothing before the state slice exists, and uses default labels', () => {
		const row = addRow( { attending: 5 } );

		delete row.dataset.singularLabel;
		delete row.dataset.pluralLabel;
		delete row.dataset.status;

		runFor( callbacks.updateRsvpCount, row, { postId: 7 } );

		expect( countText( row ) ).toBe( '' );

		runFor( callbacks.initRsvpCount, row, { postId: 7 } );
		runFor( callbacks.updateRsvpCount, row, { postId: 7 } );

		expect( countText( row ) ).toBe( '5 attendees' );
	} );

	it( 'leaves a row with no text element alone', () => {
		const row = addRow( { attending: 5 } );

		runFor( callbacks.initRsvpCount, row, { postId: 7 } );

		row.innerHTML = '';

		expect( () =>
			runFor( callbacks.updateRsvpCount, row, { postId: 7 } )
		).not.toThrow();
	} );
} );
