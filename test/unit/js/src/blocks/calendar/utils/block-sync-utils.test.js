/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	findBlockByName,
	findHeadingBlock,
	getCalendarBlockName,
	getPaginationContainerName,
	getPaginationLabel,
} from '@src/blocks/calendar/utils/block-sync-utils';

/**
 * Build a block bound to the calendar heading source.
 *
 * @param {string} clientId Block client ID.
 * @param {string} source   Binding source name.
 *
 * @return {Object} Block.
 */
const boundHeading = ( clientId, source = 'gatherpress/calendar-heading' ) => ( {
	clientId,
	name: 'core/heading',
	attributes: { metadata: { bindings: { content: { source } } } },
	innerBlocks: [],
} );

describe( 'findBlockByName', () => {
	it( 'finds a block at the top level', () => {
		const calendar = { clientId: 'a', name: 'gatherpress/calendar' };

		expect(
			findBlockByName(
				[ { clientId: 'p', name: 'core/paragraph' }, calendar ],
				'gatherpress/calendar',
			),
		).toBe( calendar );
	} );

	it( 'finds a nested block after a sibling branch that has no match', () => {
		const calendar = {
			clientId: 'c',
			name: 'gatherpress/calendar',
			innerBlocks: [],
		};
		const blocks = [
			{
				clientId: 'g1',
				name: 'core/group',
				innerBlocks: [ { clientId: 'p', name: 'core/paragraph' } ],
			},
			{
				clientId: 'g2',
				name: 'core/group',
				innerBlocks: [
					{
						clientId: 'col',
						name: 'core/column',
						innerBlocks: [ calendar ],
					},
				],
			},
		];

		expect( findBlockByName( blocks, 'gatherpress/calendar' ) ).toBe(
			calendar,
		);
	} );

	it( 'gives the first match in document order', () => {
		const first = { clientId: 'first', name: 'gatherpress/calendar' };
		const blocks = [
			{ clientId: 'g', name: 'core/group', innerBlocks: [ first ] },
			{ clientId: 'second', name: 'gatherpress/calendar' },
		];

		expect( findBlockByName( blocks, 'gatherpress/calendar' ) ).toBe(
			first,
		);
	} );

	it( 'gives null when no block has the name', () => {
		expect(
			findBlockByName(
				[
					{
						clientId: 'g',
						name: 'core/group',
						innerBlocks: [ { clientId: 'p', name: 'core/paragraph' } ],
					},
				],
				'gatherpress/calendar',
			),
		).toBeNull();
		expect( findBlockByName( [], 'gatherpress/calendar' ) ).toBeNull();
		expect( findBlockByName( undefined, 'gatherpress/calendar' ) ).toBeNull();
	} );
} );

describe( 'findHeadingBlock', () => {
	it( 'finds a block bound to the calendar heading source at the top level', () => {
		const heading = boundHeading( 'h' );

		expect(
			findHeadingBlock( [ boundHeading( 'other', 'core/post-meta' ), heading ] ),
		).toBe( heading );
	} );

	it( 'finds a nested bound heading after a sibling branch that has no match', () => {
		const heading = boundHeading( 'h' );
		const blocks = [
			{
				clientId: 'g1',
				name: 'core/group',
				attributes: {},
				innerBlocks: [ { clientId: 'p', name: 'core/paragraph' } ],
			},
			{
				clientId: 'g2',
				name: 'core/group',
				innerBlocks: [ heading ],
			},
		];

		expect( findHeadingBlock( blocks ) ).toBe( heading );
	} );

	it( 'gives null when no block is bound to the calendar heading source', () => {
		expect(
			findHeadingBlock( [
				{ clientId: 'h', name: 'core/heading', attributes: {} },
				{
					clientId: 'g',
					name: 'core/group',
					innerBlocks: [ boundHeading( 'x', 'gatherpress/calendar-day' ) ],
				},
			] ),
		).toBeNull();
		expect( findHeadingBlock() ).toBeNull();
	} );
} );

describe( 'getCalendarBlockName', () => {
	it( 'names a one-month calendar when no arguments are given', () => {
		expect( getCalendarBlockName() ).toBe( 'Month Calendar' );
	} );

	it( 'names each view type without a number for one unit', () => {
		expect( getCalendarBlockName( 'week', 1 ) ).toBe( 'Week Calendar' );
		expect( getCalendarBlockName( 'day', 1 ) ).toBe( 'Day Calendar' );
	} );

	it( 'puts the unit count first when there is more than one unit', () => {
		expect( getCalendarBlockName( 'week', 3 ) ).toBe( '3 Week Calendar' );
		expect( getCalendarBlockName( 'month', '2' ) ).toBe( '2 Month Calendar' );
	} );

	it( 'uses the month label for an unknown view type', () => {
		expect( getCalendarBlockName( 'year', 2 ) ).toBe( '2 Month Calendar' );
	} );

	it( 'counts one unit when the unit count is zero or not a number', () => {
		expect( getCalendarBlockName( 'day', 0 ) ).toBe( 'Day Calendar' );
		expect( getCalendarBlockName( 'day', 'many' ) ).toBe( 'Day Calendar' );
	} );

	it( 'uses the block name that it gets', () => {
		expect( getCalendarBlockName( 'day', 2, 'Grid' ) ).toBe( '2 Day Grid' );
	} );
} );

describe( 'getPaginationContainerName', () => {
	it( 'names a one-month pagination container when no arguments are given', () => {
		expect( getPaginationContainerName() ).toBe( 'Month Pagination' );
	} );

	it( 'includes the view type and the unit count', () => {
		expect( getPaginationContainerName( 'day', 2 ) ).toBe(
			'2 Day Pagination',
		);
		expect( getPaginationContainerName( 'week', 1 ) ).toBe(
			'Week Pagination',
		);
	} );
} );

describe( 'getPaginationLabel', () => {
	it( 'labels the next month when no arguments are given', () => {
		expect( getPaginationLabel() ).toBe( 'Next Month' );
	} );

	it( 'gives a singular label without a number for one unit', () => {
		expect( getPaginationLabel( 'previous', 'week', 1 ) ).toBe(
			'Previous Week',
		);
		expect( getPaginationLabel( 'next', 'day', 1 ) ).toBe( 'Next Day' );
	} );

	it( 'gives a plural label with the number for more than one unit', () => {
		expect( getPaginationLabel( 'next', 'day', 3 ) ).toBe( 'Next 3 Days' );
		expect( getPaginationLabel( 'previous', 'month', '2' ) ).toBe(
			'Previous 2 Months',
		);
		expect( getPaginationLabel( 'previous', 'week', 4 ) ).toBe(
			'Previous 4 Weeks',
		);
	} );

	it( 'falls back to next and month for unknown values', () => {
		expect( getPaginationLabel( 'sideways', 'year', 2 ) ).toBe(
			'Next 2 Months',
		);
	} );

	it( 'counts one unit when the unit count is zero or not a number', () => {
		expect( getPaginationLabel( 'previous', 'day', 0 ) ).toBe(
			'Previous Day',
		);
		expect( getPaginationLabel( 'previous', 'day', 'many' ) ).toBe(
			'Previous Day',
		);
	} );

	it( 'gives every direction and view type its own label', () => {
		const labels = [ 'previous', 'next' ].flatMap( ( direction ) =>
			[ 'month', 'week', 'day' ].flatMap( ( viewType ) => [
				getPaginationLabel( direction, viewType, 1 ),
				getPaginationLabel( direction, viewType, 2 ),
			] ),
		);

		expect( labels ).toEqual( [
			'Previous Month',
			'Previous 2 Months',
			'Previous Week',
			'Previous 2 Weeks',
			'Previous Day',
			'Previous 2 Days',
			'Next Month',
			'Next 2 Months',
			'Next Week',
			'Next 2 Weeks',
			'Next Day',
			'Next 2 Days',
		] );
	} );
} );
