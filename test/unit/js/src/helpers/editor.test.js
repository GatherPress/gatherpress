/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';

/**
 * WordPress dependencies
 */
import { dispatch, select } from '@wordpress/data';

// Mock WordPress modules before importing internal dependencies.
jest.mock( '@wordpress/data' );
jest.mock( '@wordpress/core-data', () => ( {
	store: {},
} ) );

/**
 * Internal dependencies
 */
import {
	enableSave,
	getCurrentContextualPostId,
	getEditorDocument,
	getStartOfWeek,
	hasValidBlockContext,
	isInFSETemplate,
} from '@src/helpers/editor';

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

	describe( 'getCurrentContextualPostId', () => {
		beforeEach( () => {
			jest.clearAllMocks();
		} );

		it( 'returns provided postId when given', () => {
			const result = getCurrentContextualPostId( 123 );

			expect( result ).toBe( 123 );
		} );

		it( 'returns postId from editor when no postId provided', () => {
			select.mockReturnValue( {
				getCurrentPostId: jest.fn().mockReturnValue( 456 ),
			} );

			const result = getCurrentContextualPostId();

			expect( select ).toHaveBeenCalledWith( 'core/editor' );
			expect( result ).toBe( 456 );
		} );

		it( 'returns postId from editor when null is provided', () => {
			select.mockReturnValue( {
				getCurrentPostId: jest.fn().mockReturnValue( 789 ),
			} );

			const result = getCurrentContextualPostId( null );

			expect( result ).toBe( 789 );
		} );

		it( 'returns 0 when postId is 0 (falsy but valid)', () => {
			select.mockReturnValue( {
				getCurrentPostId: jest.fn().mockReturnValue( 999 ),
			} );

			// 0 is falsy, so it falls back to getCurrentPostId.
			const result = getCurrentContextualPostId( 0 );

			expect( result ).toBe( 999 );
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

	describe( 'hasValidBlockContext', () => {
		beforeEach( () => {
			jest.clearAllMocks();
		} );

		it( 'returns true when in FSE template (wp_template)', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'wp_template' ),
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: false,
				postType: 'post',
				support: 'gatherpress-event-date',
				hasData: false,
			} );

			expect( result ).toBe( true );
		} );

		it( 'returns true when in FSE template (wp_template_part)', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'wp_template_part' ),
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: false,
				postType: 'post',
				support: 'gatherpress-event-date',
				hasData: false,
			} );

			expect( result ).toBe( true );
		} );

		it( 'returns true when in Query Loop with matching post type and hasData', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: jest.fn().mockReturnValue( 'post' ) };
				}
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) =>
							'gatherpress_event' === slug
								? { supports: { 'gatherpress-event-date': true } }
								: { supports: {} },
					};
				}
				return {};
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: true,
				postType: 'gatherpress_event',
				support: 'gatherpress-event-date',
				hasData: true,
			} );

			expect( result ).toBe( true );
		} );

		it( 'returns false when in Query Loop with matching post type but no hasData', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: jest.fn().mockReturnValue( 'post' ) };
				}
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) =>
							'gatherpress_event' === slug
								? { supports: { 'gatherpress-event-date': true } }
								: { supports: {} },
					};
				}
				return {};
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: true,
				postType: 'gatherpress_event',
				support: 'gatherpress-event-date',
				hasData: false,
			} );

			expect( result ).toBe( false );
		} );

		it( 'returns false when in Query Loop with non-matching post type', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: jest.fn().mockReturnValue( 'post' ) };
				}
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) =>
							'gatherpress_event' === slug
								? { supports: { 'gatherpress-event-date': true } }
								: { supports: {} },
					};
				}
				return {};
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: true,
				postType: 'post',
				support: 'gatherpress-event-date',
				hasData: true,
			} );

			expect( result ).toBe( false );
		} );

		it( 'returns true when editing directly with hasData', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_event' ),
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: false,
				postType: 'gatherpress_event',
				support: 'gatherpress-event-date',
				hasData: true,
			} );

			expect( result ).toBe( true );
		} );

		it( 'returns false when editing directly without hasData', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_event' ),
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: false,
				postType: 'gatherpress_event',
				support: 'gatherpress-event-date',
				hasData: false,
			} );

			expect( result ).toBe( false );
		} );

		it( 'defaults hasData to false when not provided', () => {
			select.mockReturnValue( {
				getCurrentPostType: jest.fn().mockReturnValue( 'gatherpress_event' ),
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: false,
				postType: 'gatherpress_event',
				support: 'gatherpress-event-date',
			} );

			expect( result ).toBe( false );
		} );

		it( 'works with venue support', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: jest.fn().mockReturnValue( 'post' ) };
				}
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) =>
							'gatherpress_event' === slug
								? { supports: { 'gatherpress-venue': true } }
								: { supports: {} },
					};
				}
				return {};
			} );

			const result = hasValidBlockContext( {
				isDescendentOfQueryLoop: true,
				postType: 'gatherpress_event',
				support: 'gatherpress-venue',
				hasData: true,
			} );

			expect( result ).toBe( true );
		} );

		describe( 'with reactive hasSupport', () => {
			beforeEach( () => {
				// `hasSupport` is the canonical input. When provided, the helper
				// must not fall back to the non-reactive `isPostTypeSupporting`
				// path — otherwise blocks would still race the post-type cache.
				select.mockReturnValue( {
					getCurrentPostType: jest.fn().mockReturnValue( 'post' ),
				} );
			} );

			it( 'returns true in Query Loop when hasSupport is true and hasData is true', () => {
				const result = hasValidBlockContext( {
					isDescendentOfQueryLoop: true,
					hasSupport: true,
					hasData: true,
				} );

				expect( result ).toBe( true );
			} );

			it( 'returns false in Query Loop when hasSupport is false', () => {
				const result = hasValidBlockContext( {
					isDescendentOfQueryLoop: true,
					hasSupport: false,
					hasData: true,
				} );

				expect( result ).toBe( false );
			} );

			it( 'returns false in Query Loop when hasSupport is true but no data', () => {
				const result = hasValidBlockContext( {
					isDescendentOfQueryLoop: true,
					hasSupport: true,
					hasData: false,
				} );

				expect( result ).toBe( false );
			} );

			it( 'prefers hasSupport over the legacy postType + support fallback', () => {
				// If hasSupport is provided, `isPostTypeSupporting` must not be
				// consulted — otherwise the non-reactive race re-emerges.
				const getPostType = jest.fn();
				select.mockImplementation( ( store ) => {
					if ( 'core/editor' === store ) {
						return { getCurrentPostType: jest.fn().mockReturnValue( 'post' ) };
					}
					if ( 'core' === store ) {
						return { getPostType };
					}
					return {};
				} );

				const result = hasValidBlockContext( {
					isDescendentOfQueryLoop: true,
					hasSupport: true,
					postType: 'gatherpress_event',
					support: 'gatherpress-event-date',
					hasData: true,
				} );

				expect( result ).toBe( true );
				expect( getPostType ).not.toHaveBeenCalled();
			} );
		} );
	} );
} );
