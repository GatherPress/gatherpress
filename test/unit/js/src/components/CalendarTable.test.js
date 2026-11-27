/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import { describe, expect, it, jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import { CalendarTable } from '@src/components/CalendarTable';

jest.mock( '@src/components/CalendarWeek', () => ( {
	CalendarWeek: ( { numRibbonSlots } ) => (
		<tr data-ribbon-slots={ numRibbonSlots } />
	),
} ) );

/**
 * Returns the ribbon row count each rendered week was given.
 *
 * @param {Object} calendar Calendar data structure.
 *
 * @return {Array<string>} Ribbon row counts in week order.
 */
const renderRibbonSlots = ( calendar ) => {
	const { container } = render( <CalendarTable calendar={ calendar } /> );

	return Array.from(
		container.querySelectorAll( 'tr[data-ribbon-slots]' ),
		( row ) => row.dataset.ribbonSlots,
	);
};

const weeks = [ [], [] ];

describe( 'CalendarTable', () => {
	it( 'gives each week its own ribbon rows', () => {
		expect(
			renderRibbonSlots( {
				units: [
					{
						dayNames: [],
						weeks,
						numRibbonSlots: 2,
						weekRibbonSlots: [ 0, 2 ],
					},
				],
			} ),
		).toEqual( [ '0', '2' ] );
	} );

	it( 'falls back to the unit ribbon rows', () => {
		expect(
			renderRibbonSlots( {
				numRibbonSlots: 3,
				units: [ { dayNames: [], weeks, numRibbonSlots: 1 } ],
			} ),
		).toEqual( [ '1', '1' ] );
	} );

	it( 'falls back to the calendar ribbon rows, then none', () => {
		expect(
			renderRibbonSlots( { dayNames: [], weeks, numRibbonSlots: 3 } ),
		).toEqual( [ '3', '3' ] );
		expect( renderRibbonSlots( { dayNames: [], weeks } ) ).toEqual( [
			'0',
			'0',
		] );
	} );
} );
