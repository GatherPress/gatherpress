/**
 * External dependencies
 */
import { renderHook } from '@testing-library/react';
import { describe, expect, it, jest, afterEach } from '@jest/globals';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	getSelectedItemReset,
	useIsBlockOrDescendantSelected,
} from '@src/blocks/dropdown/helpers';

// Mock @wordpress/data.
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

describe( 'useIsBlockOrDescendantSelected', () => {
	const testClientId = 'test-block-id';

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'should return true when the block itself is selected', () => {
		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => testClientId,
						getBlockParents: () => [],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( true );
	} );

	it( 'should return true when a child block is selected', () => {
		const childBlockId = 'child-block-id';

		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => childBlockId,
						getBlockParents: () => [ testClientId, 'parent-id' ],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( true );
	} );

	it( 'should return false when a different block is selected', () => {
		const differentBlockId = 'different-block-id';

		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => differentBlockId,
						getBlockParents: () => [ 'other-parent-id' ],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( false );
	} );

	it( 'should return false when no block is selected', () => {
		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => null,
						getBlockParents: () => [],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( false );
	} );

	it( 'should return false when selected block is a sibling', () => {
		const siblingBlockId = 'sibling-block-id';

		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => siblingBlockId,
						getBlockParents: () => [ 'shared-parent-id' ],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( false );
	} );

	it( 'should return true when a deeply nested child is selected', () => {
		const deeplyNestedChildId = 'deeply-nested-child-id';

		useSelect.mockImplementation( ( mapSelect ) => {
			const mockSelect = ( store ) => {
				if ( 'core/block-editor' === store ) {
					return {
						getSelectedBlockClientId: () => deeplyNestedChildId,
						getBlockParents: () => [
							'immediate-parent',
							'grandparent',
							testClientId,
							'root-parent',
						],
					};
				}
				return {};
			};
			return mapSelect( mockSelect );
		} );

		const { result } = renderHook( () =>
			useIsBlockOrDescendantSelected( testClientId )
		);

		expect( result.current ).toBe( true );
	} );
} );

describe( 'getSelectedItemReset', () => {
	const items = ( count ) =>
		Array.from( { length: count }, ( _, index ) => ( {
			attributes: { text: `Item ${ index + 1 }` },
		} ) );

	it( 'returns null when select mode is disabled', () => {
		expect(
			getSelectedItemReset( false, items( 3 ), 5, 'Dropdown' )
		).toBeNull();
	} );

	it( 'returns null when the selected index still points at a valid item', () => {
		expect(
			getSelectedItemReset( true, items( 3 ), 2, 'Dropdown' )
		).toBeNull();
	} );

	it( 'falls back to the first item when the selected item was removed but others remain', () => {
		expect(
			getSelectedItemReset( true, items( 3 ), 4, 'Dropdown' )
		).toEqual( { selectedIndex: 0 } );
	} );

	it( 'falls back to the first item when the selected index is negative', () => {
		expect(
			getSelectedItemReset( true, items( 3 ), -1, 'Dropdown' )
		).toEqual( { selectedIndex: 0 } );
	} );

	it( 'switches select mode off and resets the label when all items are removed', () => {
		expect( getSelectedItemReset( true, [], 2, 'Dropdown' ) ).toEqual( {
			actAsSelect: false,
			selectedIndex: 0,
			label: 'Dropdown',
		} );
	} );

	it( 'treats a non-array innerBlocks value as an empty dropdown', () => {
		expect(
			getSelectedItemReset( true, undefined, 0, 'Dropdown' )
		).toEqual( {
			actAsSelect: false,
			selectedIndex: 0,
			label: 'Dropdown',
		} );
	} );
} );
