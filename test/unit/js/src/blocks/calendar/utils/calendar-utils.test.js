/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { buildTaxQueryArgs } from '@src/blocks/calendar/utils/calendar-utils';

const TAXONOMIES = [
	{ slug: 'category', rest_base: 'categories' },
	{ slug: '_gatherpress_venue', rest_base: 'gatherpress_venues' },
];

describe( 'buildTaxQueryArgs', () => {
	it( 'maps include and exclude lists to the REST names, as core does', () => {
		expect(
			buildTaxQueryArgs(
				{
					include: { _gatherpress_venue: [ 15 ] },
					exclude: { category: [ 3 ] },
				},
				TAXONOMIES,
			),
		).toEqual( { gatherpress_venues: [ 15 ], categories_exclude: [ 3 ] } );
	} );

	it( 'reads a taxQuery saved before the include and exclude lists', () => {
		expect( buildTaxQueryArgs( { category: [ 3, 4 ] }, TAXONOMIES ) ).toEqual( {
			categories: [ 3, 4 ],
		} );
	} );

	it( 'leaves out unknown taxonomies, empty lists and a missing taxQuery', () => {
		expect(
			buildTaxQueryArgs(
				{ include: { unknown: [ 1 ], category: [] } },
				TAXONOMIES,
			),
		).toEqual( {} );
		expect( buildTaxQueryArgs( { include: { category: [ 3 ] } } ) ).toEqual(
			{},
		);
		expect( buildTaxQueryArgs( undefined, TAXONOMIES ) ).toEqual( {} );
		expect( buildTaxQueryArgs( 'category', TAXONOMIES ) ).toEqual( {} );
	} );
} );
