/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

// Mock @wordpress/blocks so createBlock returns a deterministic shape we can assert on.
jest.mock( '@wordpress/blocks', () => ( {
	createBlock: jest.fn( ( name, attributes ) => ( { name, attributes } ) ),
} ) );

/**
 * WordPress dependencies.
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import transforms from '@src/blocks/event-date/transforms';

describe( 'event-date transforms', () => {
	beforeEach( () => {
		createBlock.mockClear();
	} );

	it( 'exports a from array with a single core/post-date entry', () => {
		expect( Array.isArray( transforms.from ) ).toBe( true );
		expect( transforms.from ).toHaveLength( 1 );

		const [ rule ] = transforms.from;
		expect( rule.type ).toBe( 'block' );
		expect( rule.blocks ).toEqual( [ 'core/post-date' ] );
		expect( typeof rule.transform ).toBe( 'function' );
	} );

	it( 'maps core/post-date format and textAlign onto the new block', () => {
		const result = transforms.from[ 0 ].transform( {
			format: 'F j, Y',
			textAlign: 'center',
		} );

		expect( createBlock ).toHaveBeenCalledWith(
			'gatherpress/event-date',
			{
				displayType: 'start',
				startDateFormat: 'F j, Y',
				textAlign: 'center',
			}
		);
		expect( result ).toEqual( {
			name: 'gatherpress/event-date',
			attributes: {
				displayType: 'start',
				startDateFormat: 'F j, Y',
				textAlign: 'center',
			},
		} );
	} );

	it( 'omits startDateFormat when format is empty so the block falls back to defaults', () => {
		transforms.from[ 0 ].transform( { format: '' } );

		expect( createBlock ).toHaveBeenCalledWith(
			'gatherpress/event-date',
			{ displayType: 'start' }
		);
	} );

	it( 'omits textAlign when not provided', () => {
		transforms.from[ 0 ].transform( { format: 'Y-m-d' } );

		expect( createBlock ).toHaveBeenCalledWith(
			'gatherpress/event-date',
			{
				displayType: 'start',
				startDateFormat: 'Y-m-d',
			}
		);
	} );

	it( 'handles a bare core/post-date with no attributes', () => {
		transforms.from[ 0 ].transform( {} );

		expect( createBlock ).toHaveBeenCalledWith(
			'gatherpress/event-date',
			{ displayType: 'start' }
		);
	} );
} );
