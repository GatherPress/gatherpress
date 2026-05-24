/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';

/**
 * WordPress dependencies
 */
import { dispatch, select } from '@wordpress/data';

// Mock WordPress modules before importing internal dependencies.
// `useSelect` is invoked synchronously so reactive helpers like
// `usePostTypeLabel` can be tested without a renderer.
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn(),
	useSelect: jest.fn( ( cb ) => cb( jest.requireMock( '@wordpress/data' ).select ) ),
} ) );
jest.mock( '@wordpress/core-data', () => ( {
	store: {},
} ) );

/**
 * Internal dependencies
 */
import {
	__postTypeLabelCache,
	enableSave,
	getCurrentContextualPostId,
	getEditorDocument,
	getPostTypeLabel,
	getStartOfWeek,
	hasValidBlockContext,
	isInFSETemplate,
	usePostTypeLabel,
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

	/**
	 * Helper to create a mock getPostType function that returns labels.
	 *
	 * @param {string} slug The post type slug.
	 *
	 * @return {Object|null} The post type object with labels.
	 */
	function mockGetPostTypeWithLabels( slug ) {
		if ( 'gatherpress_event' === slug ) {
			return {
				labels: {
					name: 'Events',
					singular_name: 'Event',
					add_new_item: 'Add New Event',
				},
			};
		}
		if ( 'gatherpress_venue' === slug ) {
			return {
				labels: {
					name: 'Venues',
					singular_name: 'Venue',
				},
			};
		}
		return null;
	}

	describe( 'getPostTypeLabel', () => {
		beforeEach( () => {
			// Clear the module-level label cache so each test starts from a
			// clean slate — without this, an earlier test that resolves
			// `gatherpress_event::name` would short-circuit later tests that
			// mock a different label for the same key.
			__postTypeLabelCache.clear();
		} );

		it( 'returns the resolved label for the given key and post type', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
			expect( getPostTypeLabel( 'singular_name', 'gatherpress_event' ) ).toBe( 'Event' );
			expect( getPostTypeLabel( 'add_new_item', 'gatherpress_event' ) ).toBe( 'Add New Event' );
		} );

		it( 'falls back to the editor post type when no postType is given', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => 'gatherpress_event' };
				}
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'singular_name' ) ).toBe( 'Event' );
		} );

		it( 'returns the fallback when the post type is unknown', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'name', 'unknown_type', 'Default' ) ).toBe( 'Default' );
		} );

		it( 'returns the fallback when the requested label key is missing', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'add_new_item', 'gatherpress_venue', 'Add' ) ).toBe( 'Add' );
		} );

		it( 'returns the fallback when no post type can be resolved', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => undefined };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'name', null, 'Fallback' ) ).toBe( 'Fallback' );
		} );

		it( 'returns an empty string by default when unresolvable', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => undefined };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'name' ) ).toBe( '' );
		} );

		it( 'serves the cached label without re-reading from the core store (issue #1646)', () => {
			const getPostType = jest.fn( mockGetPostTypeWithLabels );
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType };
				}
				return {};
			} );

			expect( getPostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
			expect( getPostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
			expect( getPostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );

			expect( getPostType ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not cache a fallback so the next call can pick up a later resolution', () => {
			let resolved = false;
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) => {
							if ( ! resolved ) {
								return undefined;
							}
							return mockGetPostTypeWithLabels( slug );
						},
					};
				}
				return {};
			} );

			expect(
				getPostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Default' );
			expect( __postTypeLabelCache.has( 'gatherpress_event::name' ) ).toBe( false );

			resolved = true;

			expect(
				getPostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Events' );
			expect( __postTypeLabelCache.get( 'gatherpress_event::name' ) ).toBe( 'Events' );
		} );

		it( 'shares the cache with usePostTypeLabel (one resolves, the other hits)', () => {
			const getPostType = jest.fn( mockGetPostTypeWithLabels );
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType };
				}
				return {};
			} );

			// usePostTypeLabel populates the cache first.
			expect( usePostTypeLabel( 'singular_name', 'gatherpress_event' ) ).toBe( 'Event' );
			expect( getPostType ).toHaveBeenCalledTimes( 1 );

			// getPostTypeLabel for the same key/post type hits the shared cache.
			expect( getPostTypeLabel( 'singular_name', 'gatherpress_event' ) ).toBe( 'Event' );
			expect( getPostType ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'usePostTypeLabel', () => {
		beforeEach( () => {
			__postTypeLabelCache.clear();
		} );

		it( 'returns the resolved label for the given key and post type', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
		} );

		it( 'falls back to the editor post type when no postType is given', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => 'gatherpress_event' };
				}
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'singular_name' ) ).toBe( 'Event' );
		} );

		it( 'returns the fallback when the post type is unknown', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'name', 'unknown_type', 'Default' ) ).toBe( 'Default' );
		} );

		it( 'returns the fallback when no post type can be resolved', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => undefined };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'name', null, 'Fallback' ) ).toBe( 'Fallback' );
		} );

		it( 'returns an empty string by default when unresolvable', () => {
			select.mockImplementation( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => undefined };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'name' ) ).toBe( '' );
		} );

		it( 'subscribes via useSelect so the label is reactive', () => {
			// Confirms the hook delegates to useSelect — without subscription
			// a label that resolves on a later render (post-type registry
			// hydration) would not propagate to the component.
			const { useSelect } = require( '@wordpress/data' );
			useSelect.mockClear();
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType: mockGetPostTypeWithLabels };
				}
				return {};
			} );

			usePostTypeLabel( 'name', 'gatherpress_event' );

			expect( useSelect ).toHaveBeenCalledTimes( 1 );
		} );

		it( 're-evaluates when getPostType resolves later', () => {
			// Simulates the actual race the hook is fixing: on first render
			// the post-type definition isn't cached yet (returns undefined),
			// then resolves on a subsequent invocation. The hook must reflect
			// the new value rather than caching the fallback.
			let resolved = false;
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) => {
							if ( ! resolved ) {
								return undefined;
							}
							return mockGetPostTypeWithLabels( slug );
						},
					};
				}
				return {};
			} );

			expect(
				usePostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Default' );

			resolved = true;

			expect(
				usePostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Events' );
		} );

		it( 'serves the cached label without re-reading from the core store (issue #1646)', () => {
			// First call populates the cache from the live store, subsequent
			// calls hit the cache and never invoke `select('core').getPostType`
			// again — that's the optimization that keeps the per-call work
			// O(1) even though `useSelect` invokes the selector body many
			// times during editor init.
			const getPostType = jest.fn( mockGetPostTypeWithLabels );
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return { getPostType };
				}
				return {};
			} );

			expect( usePostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
			expect( usePostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );
			expect( usePostTypeLabel( 'name', 'gatherpress_event' ) ).toBe( 'Events' );

			expect( getPostType ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not cache a fallback so the next call can pick up a later resolution', () => {
			// Falsy labels are not cached so callers don't get stuck on the
			// fallback when the core store hydrates the post type after the
			// first render.
			let resolved = false;
			select.mockImplementation( ( store ) => {
				if ( 'core' === store ) {
					return {
						getPostType: ( slug ) => {
							if ( ! resolved ) {
								return undefined;
							}
							return mockGetPostTypeWithLabels( slug );
						},
					};
				}
				return {};
			} );

			expect(
				usePostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Default' );

			expect( __postTypeLabelCache.has( 'gatherpress_event::name' ) ).toBe( false );

			resolved = true;

			expect(
				usePostTypeLabel( 'name', 'gatherpress_event', 'Default' )
			).toBe( 'Events' );

			expect( __postTypeLabelCache.get( 'gatherpress_event::name' ) ).toBe( 'Events' );
		} );
	} );
} );
