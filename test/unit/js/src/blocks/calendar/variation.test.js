/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';

jest.mock( '@wordpress/blocks', () => ( {
	registerBlockVariation: jest.fn(),
} ) );

/**
 * WordPress dependencies.
 */
import { registerBlockVariation } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import QUERY_VARIATION from '@src/blocks/calendar/templates/query-variation';
// Importing the module registers the variation as a side effect.
import '@src/blocks/calendar/variation';

describe( 'core/query Event Calendar variation', () => {
	// Read the calls before any test can clear them.
	const calls = [ ...registerBlockVariation.mock.calls ];
	const [ [ blockName, variation ] ] = calls;

	it( 'registers one variation of core/query when the module loads', () => {
		expect( calls ).toHaveLength( 1 );
		expect( blockName ).toBe( 'core/query' );
		expect( variation.name ).toBe( 'gatherpress-calendar' );
	} );

	it( 'shows the variation in the inserter, in the GatherPress category', () => {
		expect( variation.title ).toBe( 'Event Calendar' );
		expect( variation.description ).toBe(
			'Show events in a monthly calendar format.',
		);
		expect( variation.icon ).toBe( 'calendar-alt' );
		expect( variation.category ).toBe( 'gatherpress' );
		expect( variation.keywords ).toEqual( [
			'calendar',
			'event',
			'query',
		] );
		expect( variation.scope ).toEqual( [ 'inserter' ] );
	} );

	it( 'queries upcoming and unfinished GatherPress events in date order', () => {
		expect( variation.attributes.enhancedPagination ).toBe( true );
		expect( variation.attributes.query ).toMatchObject( {
			postType: 'gatherpress_event',
			gatherpress_event_query: 'upcoming',
			include_unfinished: 1,
			order: 'asc',
			orderBy: 'datetime',
			inherit: false,
			offset: 0,
			pages: 0,
		} );
	} );

	it( 'inserts the calendar heading, the calendar pagination and the calendar', () => {
		expect( variation.innerBlocks ).toBe( QUERY_VARIATION );

		const [ heading, pagination, calendar ] = variation.innerBlocks;

		expect( variation.innerBlocks ).toHaveLength( 3 );
		expect( heading[ 0 ] ).toBe( 'core/heading' );
		expect( heading[ 1 ].metadata.bindings.content.source ).toBe(
			'gatherpress/calendar-heading',
		);
		expect( pagination[ 0 ] ).toBe( 'core/query-pagination' );
		expect( pagination[ 2 ].map( ( [ name ] ) => name ) ).toEqual( [
			'core/query-pagination-previous',
			'core/query-pagination-next',
		] );
		expect( calendar[ 0 ] ).toBe( 'gatherpress/calendar' );
		expect( calendar[ 2 ][ 0 ][ 0 ] ).toBe( 'gatherpress/calendar-week' );
	} );
} );
