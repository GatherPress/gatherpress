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
import { DAY_NUMBER } from '@src/blocks/calendar/constants';
// Importing the module registers the variation as a side effect.
import '@src/blocks/calendar/variations/bound-blocks';

describe( 'core/paragraph Calendar Day Number variation', () => {
	// Read the calls before any test can clear them.
	const calls = [ ...registerBlockVariation.mock.calls ];
	const [ [ blockName, variation ] ] = calls;
	const isActive = ( blockAttributes ) =>
		variation.isActive( blockAttributes, variation.attributes );

	it( 'registers one variation of core/paragraph when the module loads', () => {
		expect( calls ).toHaveLength( 1 );
		expect( blockName ).toBe( 'core/paragraph' );
		expect( variation.name ).toBe( 'gatherpress-calendar-day-number' );
	} );

	it( 'shows the variation in the inserter, in the GatherPress category', () => {
		expect( variation.title ).toBe( 'Calendar Day Number' );
		expect( variation.description ).toBe(
			'Displays the day number inside a calendar day cell.',
		);
		expect( variation.category ).toBe( 'gatherpress' );
		expect( variation.keywords ).toEqual( [ 'day', 'number', 'calendar' ] );
		expect( variation.scope ).toEqual( [ 'inserter' ] );
	} );

	it( 'binds the paragraph content to the calendar day source', () => {
		expect( variation.attributes.className ).toBe(
			'gatherpress-calendar__day-number',
		);
		expect( variation.attributes.metadata ).toEqual( {
			bindings: {
				content: { source: 'gatherpress/calendar-day' },
			},
			name: 'Day Number',
		} );
		expect( variation.attributes.placeholder ).toBe( 'DD' );
		expect( variation.attributes.content ).toBe( 'DD' );
	} );

	it( 'inserts the same paragraph that the calendar day template uses', () => {
		expect( DAY_NUMBER[ 0 ] ).toBe( blockName );
		expect( variation.attributes ).toEqual( DAY_NUMBER[ 1 ] );
	} );

	describe( 'isActive', () => {
		it( 'is active for a paragraph with the day number class', () => {
			expect(
				isActive( { className: 'gatherpress-calendar__day-number' } ),
			).toBe( true );
		} );

		it( 'is active when the day number class is one of several classes', () => {
			expect(
				isActive( {
					className:
						'has-small-font-size gatherpress-calendar__day-number is-style-bold',
				} ),
			).toBe( true );
		} );

		it( 'is active for the day number paragraph of the calendar day template', () => {
			expect( isActive( DAY_NUMBER[ 1 ] ) ).toBe( true );
		} );

		it( 'is not active for a paragraph with other classes', () => {
			expect( isActive( { className: 'has-small-font-size' } ) ).toBe(
				false,
			);
		} );

		it( 'is not active when a class only starts with the day number class', () => {
			expect(
				isActive( {
					className: 'gatherpress-calendar__day-number-label',
				} ),
			).toBe( false );
		} );

		it( 'is not active for a paragraph with no class', () => {
			expect( isActive( { content: 'DD' } ) ).toBe( false );
			expect( isActive( { className: '' } ) ).toBe( false );
		} );

		it( 'is not active when the block has no attributes', () => {
			expect( isActive( undefined ) ).toBe( false );
		} );
	} );
} );
