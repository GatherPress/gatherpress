/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

jest.mock( '@wordpress/blocks', () => ( {
	createBlock: jest.fn( ( name, attributes ) => ( {
		name,
		attributes,
	} ) ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	isPostTypeSupporting: jest.fn(),
} ) );

/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { isPostTypeSupporting } from '@src/helpers/event';
import transforms from '@src/blocks/event-date/transforms';

describe( 'gatherpress/event-date transforms', () => {
	beforeEach( () => {
		createBlock.mockClear();
		select.mockReset();
		isPostTypeSupporting.mockReset();
	} );

	const [ postDateTransform ] = transforms.from;

	it( 'declares a block-type transform from core/post-date', () => {
		expect( postDateTransform.type ).toBe( 'block' );
		expect( postDateTransform.blocks ).toEqual( [ 'core/post-date' ] );
	} );

	describe( 'isMatch', () => {
		it( 'returns true on a post type that supports gatherpress-event-date', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => 'gatherpress_event' };
				}
				return {};
			} );
			isPostTypeSupporting.mockReturnValue( true );

			expect( postDateTransform.isMatch() ).toBe( true );
			expect( isPostTypeSupporting ).toHaveBeenCalledWith(
				'gatherpress-event-date',
				'gatherpress_event'
			);
		} );

		it( 'returns false on a post type that does not support gatherpress-event-date', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => 'post' };
				}
				return {};
			} );
			isPostTypeSupporting.mockReturnValue( false );

			expect( postDateTransform.isMatch() ).toBe( false );
		} );

		it( 'returns false when no post type can be resolved', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => undefined };
				}
				return {};
			} );

			expect( postDateTransform.isMatch() ).toBe( false );
			expect( isPostTypeSupporting ).not.toHaveBeenCalled();
		} );

		it( 'returns false when the core/editor store is unavailable', () => {
			select.mockReturnValue( undefined );

			expect( postDateTransform.isMatch() ).toBe( false );
			expect( isPostTypeSupporting ).not.toHaveBeenCalled();
		} );
	} );

	describe( 'transform', () => {
		it( 'creates a gatherpress/event-date block carrying the source format on both start and end', () => {
			const result = postDateTransform.transform( {
				format: 'F j, Y g:i a',
			} );

			expect( createBlock ).toHaveBeenCalledWith(
				'gatherpress/event-date',
				{
					startDateFormat: 'F j, Y g:i a',
					endDateFormat: 'F j, Y g:i a',
				}
			);
			expect( result ).toEqual( {
				name: 'gatherpress/event-date',
				attributes: {
					startDateFormat: 'F j, Y g:i a',
					endDateFormat: 'F j, Y g:i a',
				},
			} );
		} );

		it( 'defaults missing format to an empty string', () => {
			postDateTransform.transform( {} );

			expect( createBlock ).toHaveBeenCalledWith(
				'gatherpress/event-date',
				{
					startDateFormat: '',
					endDateFormat: '',
				}
			);
		} );
	} );
} );
