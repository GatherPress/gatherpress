/**
 * External dependencies.
 */
import { describe, expect, jest, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../../../../src/helpers/venue';

// Mock the @wordpress/data module
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
} ) );

// Mock the @wordpress/core-data module
jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

/**
 * Coverage for isVenuePostType.
 */
describe( 'isVenuePostType', () => {
	it( 'returns false when there is no current post type', () => {
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns false when current post type is gatherpress_event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns true when current post type is gatherpress_venue', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_venue' : null,
		} ) );
		expect( isVenuePostType() ).toBe( true );
	} );
} );
