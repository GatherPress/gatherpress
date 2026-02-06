/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';

// Mock WordPress modules before importing internal dependencies.
jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/core-data', () => ( {
	store: {},
} ) );

/**
 * Internal dependencies.
 */
import {
	enableSave,
	isGatherPressPostType,
	getEditorDocument,
	getStartOfWeek,
	isInFSETemplate,
} from '../../../../../src/helpers/editor';

describe( 'Editor helper functions', () => {
	describe( 'enableSave', () => {
		it( 'calls editPost with non-existing meta key', () => {
			const mockEditPost = jest.fn();
			dispatch.mockReturnValue( {
				editPost: mockEditPost,
			} );

			enableSave();

			expect( dispatch ).toHaveBeenCalledWith( 'core/editor' );
			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { _non_existing_meta: true },
			} );
		} );

		it( 'handles when dispatch returns undefined', () => {
			dispatch.mockReturnValue( undefined );

			// Should not throw an error.
			expect( () => enableSave() ).not.toThrow();
		} );

		it( 'handles when dispatch returns null', () => {
			dispatch.mockReturnValue( null );

			// Should not throw an error.
			expect( () => enableSave() ).not.toThrow();
		} );
	} );

	describe( 'isGatherPressPostType', () => {
		beforeEach( () => {
			jest.clearAllMocks();
		} );

		it( 'returns true when post type is gatherpress_event', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_event' ),
			} );

			expect( isGatherPressPostType() ).toBe( true );
		} );

		it( 'returns true when post type is gatherpress_venue', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_venue' ),
			} );

			expect( isGatherPressPostType() ).toBe( true );
		} );

		it( 'returns false when post type is post', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'post' ),
			} );

			expect( isGatherPressPostType() ).toBe( false );
		} );

		it( 'returns false when post type is page', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'page' ),
			} );

			expect( isGatherPressPostType() ).toBe( false );
		} );

		it( 'returns false when post type is wp_template', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'wp_template' ),
			} );

			expect( isGatherPressPostType() ).toBe( false );
		} );

		it( 'returns false when select returns undefined', () => {
			select.mockReturnValue( undefined );

			expect( isGatherPressPostType() ).toBe( false );
		} );

		it( 'returns false when select returns null', () => {
			select.mockReturnValue( null );

			expect( isGatherPressPostType() ).toBe( false );
		} );

		it( 'returns false when getCurrentPostType returns undefined', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( undefined ),
			} );

			expect( isGatherPressPostType() ).toBe( false );
		} );
	} );

	describe( 'getEditorDocument', () => {
		let querySelectorSpy;

		beforeEach( () => {
			// Spy on the querySelector method.
			querySelectorSpy = jest.spyOn( global.document, 'querySelector' );
		} );

		afterEach( () => {
			// Restore the original querySelector.
			querySelectorSpy.mockRestore();
		} );

		it( 'returns iframe contentDocument when editor-canvas iframe exists', () => {
			const mockContentDocument = { testProperty: 'iframe document' };
			const mockIframe = {
				contentDocument: mockContentDocument,
			};

			querySelectorSpy.mockReturnValue( mockIframe );

			const result = getEditorDocument();

			// Verify it returned the iframe's contentDocument.
			expect( result.testProperty ).toBe( 'iframe document' );
		} );

		it( 'returns global document when no iframe exists', () => {
			querySelectorSpy.mockReturnValue( null );

			const result = getEditorDocument();

			expect( result ).toBe( global.document );
		} );

		it( 'returns global document when iframe exists but has no contentDocument', () => {
			const mockIframe = {
				contentDocument: null,
			};

			querySelectorSpy.mockReturnValue( mockIframe );

			const result = getEditorDocument();

			expect( result ).toBe( global.document );
		} );

		it( 'returns global document when iframe has empty contentDocument property', () => {
			const mockIframe = {};

			querySelectorSpy.mockReturnValue( mockIframe );

			const result = getEditorDocument();

			expect( result ).toBe( global.document );
		} );

		it( 'returns global document when querySelector returns undefined', () => {
			querySelectorSpy.mockReturnValue( undefined );

			const result = getEditorDocument();

			expect( result ).toBe( global.document );
		} );
	} );

	describe( 'getStartOfWeek', () => {
		beforeEach( () => {
			jest.clearAllMocks();
		} );

		it( 'returns start_of_week from site settings', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( {
					start_of_week: 1, // Monday
				} ),
			} );

			expect( getStartOfWeek() ).toBe( 1 );
		} );

		it( 'returns 0 (Sunday) when start_of_week is 0', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( {
					start_of_week: 0, // Sunday
				} ),
			} );

			expect( getStartOfWeek() ).toBe( 0 );
		} );

		it( 'returns 0 when site is undefined', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( undefined ),
			} );

			expect( getStartOfWeek() ).toBe( 0 );
		} );

		it( 'returns 0 when site is null', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( null ),
			} );

			expect( getStartOfWeek() ).toBe( 0 );
		} );

		it( 'returns 0 when start_of_week property is missing', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( {} ),
			} );

			expect( getStartOfWeek() ).toBe( 0 );
		} );

		it( 'returns 6 when start_of_week is 6 (Saturday)', () => {
			select.mockReturnValue( {
				getSite: jest.fn().mockReturnValue( {
					start_of_week: 6, // Saturday
				} ),
			} );

			expect( getStartOfWeek() ).toBe( 6 );
		} );
	} );

	describe( 'isInFSETemplate', () => {
		beforeEach( () => {
			jest.clearAllMocks();
		} );

		it( 'returns true when post type is wp_template', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'wp_template' ),
			} );

			expect( isInFSETemplate() ).toBe( true );
		} );

		it( 'returns true when post type is wp_template_part', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'wp_template_part' ),
			} );

			expect( isInFSETemplate() ).toBe( true );
		} );

		it( 'returns false when post type is post', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'post' ),
			} );

			expect( isInFSETemplate() ).toBe( false );
		} );

		it( 'returns false when post type is page', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'page' ),
			} );

			expect( isInFSETemplate() ).toBe( false );
		} );

		it( 'returns false when post type is gatherpress_event', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_event' ),
			} );

			expect( isInFSETemplate() ).toBe( false );
		} );

		it( 'returns false when select returns undefined', () => {
			select.mockReturnValue( undefined );

			expect( isInFSETemplate() ).toBe( false );
		} );

		it( 'returns false when select returns null', () => {
			select.mockReturnValue( null );

			expect( isInFSETemplate() ).toBe( false );
		} );

		it( 'returns false when getCurrentPostType returns undefined', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( undefined ),
			} );

			expect( isInFSETemplate() ).toBe( false );
		} );
	} );
} );
