/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	buildEventQuery,
	toEventOptions,
} from '@src/components/EventSelect';

/**
 * Builds an event record shaped like the REST response.
 *
 * @param {number} id    Post ID.
 * @param {string} title Rendered title.
 *
 * @return {Object} The record.
 */
const event = ( id, title ) => ( { id, title: { rendered: title } } );

describe( 'buildEventQuery', () => {
	it( 'requests unpublished events, which is what an organizer filters by', () => {
		// The default `view` context returns published posts only, which hid
		// drafts the RSVP screen exists to filter.
		expect( buildEventQuery( '' ) ).toMatchObject( {
			context: 'edit',
			status: 'any',
		} );
	} );

	it( 'asks for past events as well as upcoming', () => {
		// The event collection endpoint filters to upcoming when this is
		// absent, and RSVPs are mostly looked up after the event.
		expect( buildEventQuery( '' ).gatherpress_event_query ).toBe( 'all' );
	} );

	it( 'caps the result count so a large site stays usable', () => {
		expect( buildEventQuery( '' ).per_page ).toBe( 10 );
	} );

	it( 'orders by relevance while searching', () => {
		expect( buildEventQuery( 'picnic' ).orderby ).toBe( 'relevance' );
	} );

	it( 'orders by date when there is no search term', () => {
		// `relevance` needs something to be relevant to; without a term the
		// most recent events are the useful default.
		expect( buildEventQuery( '' ).orderby ).toBe( 'date' );
	} );

	it( 'passes the search term through', () => {
		expect( buildEventQuery( 'picnic' ).search ).toBe( 'picnic' );
	} );
} );

describe( 'toEventOptions', () => {
	it( 'maps records to combobox options', () => {
		expect( toEventOptions( [ event( 1, 'Summer Picnic' ) ], null ) ).toEqual(
			[ { value: 1, label: 'Summer Picnic' } ]
		);
	} );

	it( 'decodes entities in titles', () => {
		expect(
			toEventOptions( [ event( 1, 'Bob &amp; Alice' ) ], null )[ 0 ].label
		).toBe( 'Bob & Alice' );
	} );

	it( 'falls back to the ID when an event has no title', () => {
		expect( toEventOptions( [ { id: 7, title: {} } ], null )[ 0 ].label ).toBe(
			'#7'
		);
	} );

	it( 'falls back to the ID when the title key is absent entirely', () => {
		expect( toEventOptions( [ { id: 8 } ], null )[ 0 ].label ).toBe( '#8' );
	} );

	it( 'keeps the selected event in the list when the search excludes it', () => {
		// Otherwise the control renders blank over a real selection.
		expect(
			toEventOptions(
				[ event( 2, 'Autumn Walk' ) ],
				event( 9, 'Winter Social' )
			)
		).toEqual( [
			{ value: 9, label: 'Winter Social' },
			{ value: 2, label: 'Autumn Walk' },
		] );
	} );

	it( 'does not duplicate the selected event when the search includes it', () => {
		const selected = event( 2, 'Autumn Walk' );

		expect( toEventOptions( [ selected ], selected ) ).toHaveLength( 1 );
	} );

	it( 'returns an empty list when there are no records', () => {
		expect( toEventOptions( [], null ) ).toEqual( [] );
	} );

	it( 'tolerates records being undefined before the first response', () => {
		expect( toEventOptions( undefined, null ) ).toEqual( [] );
	} );

	it( 'returns the selection alone when the search found nothing', () => {
		expect( toEventOptions( [], event( 9, 'Winter Social' ) ) ).toEqual( [
			{ value: 9, label: 'Winter Social' },
		] );
	} );
} );
