/**
 * External dependencies.
 */
import { renderHook } from '@testing-library/react';
import { describe, expect, it, jest, afterEach } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { useIsBlockOrDescendantSelected } from '../../../../../../src/blocks/dropdown/helpers';

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
