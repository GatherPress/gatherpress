/**
 * External dependencies
 */
import { describe, expect, jest, it, beforeEach } from '@jest/globals';
import { renderHook } from '@testing-library/react';

/**
 * Mock WordPress modules before imports.
 */
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

jest.mock( '@wordpress/element', () => ( {
	useMemo: jest.fn( ( fn ) => fn() ),
} ) );

jest.mock( '@wordpress/html-entities', () => ( {
	decodeEntities: jest.fn( ( str ) => str ),
} ) );

/**
 * Import mocked modules to access mock functions.
 */
import { select, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	isVenuePostType,
	getVenuePostType,
	getVenueTaxonomy,
	useVenuePostFromTermId,
	useVenueTermFromPostId,
	GetVenuePostFromEventId,
	getVenueTitle,
	useVenueOptions,
	usePopularVenues,
	useVenueTaxonomyIds,
	findVenuePostById,
} from '@src/helpers/venue';

/**
 * Coverage for getVenuePostType.
 */
describe( 'getVenuePostType', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns the mapped venue post type when the editor config provides one', () => {
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					config: {
						venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
					},
				},
			} ),
		} );
		expect( getVenuePostType( 'gatherpress_event' ) ).toBe(
			'gatherpress_venue'
		);
	} );

	it( 'falls back to the default when the resolved post type has no mapping', () => {
		// Drives the `venuePostTypeMap[ key ] ?? DEFAULT_VENUE_POST_TYPE` branch:
		// the map exists but doesn't contain the requested post type.
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					config: {
						venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
					},
				},
			} ),
		} );
		expect( getVenuePostType( 'unknown_event_type' ) ).toBe(
			'gatherpress_venue'
		);
	} );

	it( 'falls back to an empty map when editor config is missing', () => {
		// Drives the `?? {}` branch: optional chain bottoms out when there is no
		// gatherpress config, then the lookup falls through to the default.
		select.mockReturnValue( {
			getEditorSettings: () => ( {} ),
		} );
		expect( getVenuePostType( 'gatherpress_event' ) ).toBe(
			'gatherpress_venue'
		);
	} );

	it( 'returns the default when called without an event post type argument', () => {
		// Exercises the `eventPostType = ''` default parameter path.
		select.mockReturnValue( {
			getEditorSettings: () => ( {
				gatherpress: {
					config: {
						venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
					},
				},
			} ),
		} );
		expect( getVenuePostType() ).toBe( 'gatherpress_venue' );
	} );
} );

/**
 * Coverage for getVenueTaxonomy.
 */
describe( 'getVenueTaxonomy', () => {
	it( 'returns _gatherpress_venue for default venue post type', () => {
		expect( getVenueTaxonomy() ).toBe( '_gatherpress_venue' );
	} );

	it( 'returns _gatherpress_venue when passed gatherpress_venue', () => {
		expect( getVenueTaxonomy( 'gatherpress_venue' ) ).toBe( '_gatherpress_venue' );
	} );

	it( 'returns _my_custom_venue for a custom venue post type', () => {
		expect( getVenueTaxonomy( 'my_custom_venue' ) ).toBe( '_my_custom_venue' );
	} );

	it( 'prepends underscore to any given post type slug', () => {
		expect( getVenueTaxonomy( 'gatherpress_location' ) ).toBe( '_gatherpress_location' );
	} );
} );

/**
 * Coverage for isVenuePostType.
 */
describe( 'isVenuePostType', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns false when select returns undefined', () => {
		select.mockReturnValue( undefined );
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns false when post type does not have gatherpress-venue-information support', () => {
		select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return {
					getPostType: () => ( {
						supports: { 'gatherpress-event-date': true },
					} ),
				};
			}
			return {};
		} );
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns true when post type has gatherpress-venue-information support', () => {
		select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_venue' };
			}
			if ( 'core' === store ) {
				return {
					getPostType: () => ( {
						supports: { 'gatherpress-venue-information': true },
					} ),
				};
			}
			return {};
		} );
		expect( isVenuePostType() ).toBe( true );
	} );

	it( 'returns true for a custom venue post type with gatherpress-venue-information support', () => {
		select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'my_custom_venue' };
			}
			if ( 'core' === store ) {
				return {
					getPostType: () => ( {
						supports: { 'gatherpress-venue-information': true },
					} ),
				};
			}
			return {};
		} );
		expect( isVenuePostType() ).toBe( true );
	} );
} );

/**
 * Coverage for getVenueTitle.
 */
describe( 'getVenueTitle', () => {
	it( 'returns venue name for taxonomy kind', () => {
		const venue = { name: 'Community Center' };
		expect( getVenueTitle( venue, 'taxonomy' ) ).toBe( 'Community Center' );
	} );

	it( 'returns venue title.rendered for postType kind', () => {
		const venue = { title: { rendered: 'Downtown Hall' } };
		expect( getVenueTitle( venue, 'postType' ) ).toBe( 'Downtown Hall' );
	} );

	it( 'returns loading text for unknown kind', () => {
		const venue = { name: 'Test', title: { rendered: 'Test' } };
		expect( getVenueTitle( venue, 'unknown' ) ).toBe( '&hellip;loading' );
	} );

	it( 'returns loading text when kind is undefined', () => {
		const venue = { name: 'Test' };
		expect( getVenueTitle( venue, undefined ) ).toBe( '&hellip;loading' );
	} );

	it( 'returns loading text when kind is null', () => {
		const venue = { name: 'Test' };
		expect( getVenueTitle( venue, null ) ).toBe( '&hellip;loading' );
	} );
} );

/**
 * Coverage for useVenuePostFromTermId.
 */
describe( 'useVenuePostFromTermId', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns undefined when termId is null and skips the entity lookup entirely', () => {
		const wpSelect = jest.fn( () => ( {
			getEntityRecord: jest.fn(),
			getEntityRecords: jest.fn(),
		} ) );

		useSelect.mockImplementation( ( callback ) => callback( wpSelect ) );

		const { result } = renderHook( () => useVenuePostFromTermId( null ) );

		// Both arms of the inner useSelect callback now return the same
		// `{ venuePost }` shape, so destructuring still yields undefined.
		// The early return also short-circuits before any store call —
		// `wpSelect` is never invoked when there is no term to resolve.
		expect( result.current ).toBeUndefined();
		expect( wpSelect ).not.toHaveBeenCalled();
	} );

	it( 'does not throw when the venue term resolves without a slug', () => {
		// Guards the `venueTerm?.slug?.replace( /^_/, '' )` chain — without
		// the inner optional chain, a term object lacking a `slug` would
		// throw `TypeError: Cannot read properties of undefined (reading 'replace')`.
		const mockVenueTerm = { id: 1 };
		let capturedSlug;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( ( _t, _p, query ) => {
					capturedSlug = query.slug;
					return [];
				} ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		expect( () =>
			renderHook( () => useVenuePostFromTermId( 1 ) )
		).not.toThrow();
		expect( capturedSlug ).toBeUndefined();
	} );

	it( 'does not throw while the venue term entity is still loading', () => {
		// Pre-resolution `getEntityRecord` returns null/undefined; the outer
		// optional chain on `venueTerm` is what guards this case.
		let capturedSlug;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords: jest.fn( ( _t, _p, query ) => {
					capturedSlug = query.slug;
					return [];
				} ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		expect( () =>
			renderHook( () => useVenuePostFromTermId( 1 ) )
		).not.toThrow();
		expect( capturedSlug ).toBeUndefined();
	} );

	it( 'returns venue post when term is found', () => {
		const mockVenueTerm = { id: 1, slug: '_test-venue' };
		const mockVenuePost = [ { id: 10, title: 'Test Venue' } ];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => useVenuePostFromTermId( 1 ) );
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'strips leading underscore from term slug', () => {
		const mockVenueTerm = { id: 1, slug: '_my-venue' };
		let capturedSlug = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( ( type, postType, query ) => {
					capturedSlug = query.slug;
					return [];
				} ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => useVenuePostFromTermId( 1 ) );
		expect( capturedSlug ).toBe( 'my-venue' );
	} );

	it( 'handles term without leading underscore', () => {
		const mockVenueTerm = { id: 1, slug: 'venue-no-underscore' };
		let capturedSlug = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( ( type, postType, query ) => {
					capturedSlug = query.slug;
					return [];
				} ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => useVenuePostFromTermId( 1 ) );
		expect( capturedSlug ).toBe( 'venue-no-underscore' );
	} );
} );

/**
 * Coverage for useVenueTermFromPostId.
 */
describe( 'useVenueTermFromPostId', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns undefined when postId is null and skips the entity lookup entirely', () => {
		const wpSelect = jest.fn( () => ( {
			getEntityRecord: jest.fn(),
			getEntityRecords: jest.fn(),
		} ) );

		useSelect.mockImplementation( ( callback ) => callback( wpSelect ) );

		const { result } = renderHook( () => useVenueTermFromPostId( null ) );

		// Both arms of the inner useSelect callback now return the same
		// `{ venueTerm }` shape, so destructuring still yields undefined and
		// no entity-store lookups fire when there is no post to resolve.
		expect( result.current ).toBeUndefined();
		expect( wpSelect ).not.toHaveBeenCalled();
	} );

	it( 'returns undefined when postId defaults to null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const result = callback( () => ( {} ) );
			return result;
		} );

		const { result } = renderHook( () => useVenueTermFromPostId() );
		expect( result.current ).toBeUndefined();
	} );

	it( 'returns undefined while the venue post entity is still loading', () => {
		// Pre-resolution `getEntityRecord` returns null; the `! venuePost?.slug`
		// guard short-circuits before the slug-prefix and term query so we
		// never dispatch an `_undefined` lookup or throw on `null.slug`.
		const getEntityRecords = jest.fn();

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords,
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => useVenueTermFromPostId( 10 ) );

		expect( result.current ).toBeUndefined();
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );

	it( 'returns undefined when the venue post resolves without a slug', () => {
		// Same guard exercised by a hydrated-but-malformed record (no `slug`).
		const getEntityRecords = jest.fn();

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => ( { id: 10 } ) ),
				getEntityRecords,
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => useVenueTermFromPostId( 10 ) );

		expect( result.current ).toBeUndefined();
		expect( getEntityRecords ).not.toHaveBeenCalled();
	} );

	it( 'returns venue term when post is found', () => {
		const mockVenuePost = { id: 10, slug: 'test-venue' };
		const mockVenueTerm = [ { id: 1, name: 'Test Venue' } ];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenuePost ),
				getEntityRecords: jest.fn( () => mockVenueTerm ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => useVenueTermFromPostId( 10 ) );
		expect( result.current ).toEqual( mockVenueTerm );
	} );

	it( 'prefixes slug with underscore for term query', () => {
		const mockVenuePost = { id: 10, slug: 'my-venue' };
		let capturedSlug = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenuePost ),
				getEntityRecords: jest.fn( ( type, taxonomy, query ) => {
					capturedSlug = query.slug;
					return [];
				} ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => useVenueTermFromPostId( 10 ) );
		expect( capturedSlug ).toBe( '_my-venue' );
	} );
} );

/**
 * Coverage for GetVenuePostFromEventId.
 */
describe( 'GetVenuePostFromEventId', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns null when event has no venue terms', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						getEditorSettings: () => ( {
							gatherpress: {
								config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
							},
						} ),
					};
				}
				return {
					getEntityRecords: jest.fn( () => [] ),
				};
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100, 'gatherpress_event' ) );
		// Returns undefined because termId is null.
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns venue post when event has a venue term', () => {
		const mockVenueTerm = { id: 5, slug: '_event-venue' };
		const mockVenuePost = [ { id: 20, title: 'Event Venue' } ];

		let callCount = 0;
		useSelect.mockImplementation( ( callback ) => {
			callCount++;
			if ( 1 === callCount ) {
				// First call: GetVenuePostFromEventId queries taxonomy terms directly.
				const wpSelect = jest.fn( ( store ) => {
					if ( 'core/editor' === store ) {
						return {
							getCurrentPostType: () => 'gatherpress_event',
							getEditorSettings: () => ( {
								gatherpress: {
									config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ mockVenueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: useVenuePostFromTermId fetches the venue post.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100, 'gatherpress_event' ) );
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'works with a custom event post type mapped to a custom venue post type', () => {
		const mockVenueTerm = { id: 9, slug: '_custom-venue' };
		const mockVenuePost = [ { id: 90, title: 'Custom Venue' } ];

		let callCount = 0;
		useSelect.mockImplementation( ( callback ) => {
			callCount++;
			if ( 1 === callCount ) {
				// First call: GetVenuePostFromEventId queries _gatherpress_location taxonomy.
				const wpSelect = jest.fn( ( store ) => {
					if ( 'core/editor' === store ) {
						return {
							getCurrentPostType: () => 'gatherpress_shindig',
							getEditorSettings: () => ( {
								gatherpress: {
									config: { venuePostTypes: { gatherpress_shindig: 'gatherpress_location' } },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ mockVenueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: useVenuePostFromTermId.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 200, 'gatherpress_shindig' ) );
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'skips the online-event term and uses the physical venue term', () => {
		const onlineTerm = { id: 1, slug: 'online-event' };
		const venueTerm = { id: 7, slug: '_venue-seven' };
		const mockVenuePost = [ { id: 70, title: 'Venue Seven' } ];

		let callCount = 0;
		useSelect.mockImplementation( ( callback ) => {
			callCount++;
			if ( 1 === callCount ) {
				// First call: returns both online-event and a physical venue term.
				const wpSelect = jest.fn( ( store ) => {
					if ( 'core/editor' === store ) {
						return {
							getCurrentPostType: () => 'gatherpress_event',
							getEditorSettings: () => ( {
								gatherpress: {
									config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ onlineTerm, venueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: useVenuePostFromTermId uses term ID 7 (skipping online-event).
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => venueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
				getPostType: jest.fn( () => ( {
					supports: {},
				} ) ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100, 'gatherpress_event' ) );
		// Should return venue post for the physical venue term (7), not online-event (1).
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'skips API call and returns undefined when eventId is zero', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						getEditorSettings: () => ( {
							gatherpress: {
								config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
							},
						} ),
					};
				}
				return { getEntityRecords: jest.fn( () => [] ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 0 ) );
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns null when venue terms are null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						getEditorSettings: () => ( {
							gatherpress: {
								config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
							},
						} ),
					};
				}
				return {
					getEntityRecords: jest.fn( () => null ),
				};
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 999, 'gatherpress_event' ) );
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns null when no postType and no editor post type available', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => null,
						getEditorSettings: () => ( {} ),
					};
				}
				return { getEntityRecords: jest.fn( () => [] ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100 ) );
		// Returns undefined because resolvedPostType is null — early return.
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns null when eventId is null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						getEditorSettings: () => ( {
							gatherpress: {
								config: { venuePostTypes: { gatherpress_event: 'gatherpress_venue' } },
							},
						} ),
					};
				}
				return { getEntityRecords: jest.fn( () => [] ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( null, 'gatherpress_event' ) );
		// Returns undefined because eventId is null — early return.
		expect( result.current ).toEqual( undefined );
	} );

	it( 'falls back to an empty venue-post-type map when editor config is missing', () => {
		// Drives the `?? {}` fallback on venuePostTypes. With no gatherpress config
		// at all, the lookup `venuePostTypeMap[ resolvedPostType ]` becomes undefined
		// and `?? DEFAULT_VENUE_POST_TYPE` then resolves to the default — both
		// fallback branches are exercised in one pass.
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						// No `gatherpress.config.venuePostTypes` at all.
						getEditorSettings: () => ( {} ),
					};
				}
				return { getEntityRecords: jest.fn( () => [] ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			GetVenuePostFromEventId( 100, 'gatherpress_event' )
		);
		// No venue terms returned, so result is undefined; the value we care
		// about is that the helper traversed the fallback paths without throwing.
		expect( result.current ).toEqual( undefined );
	} );

	it( 'uses the default venue post type when the map has no entry for the resolved post type', () => {
		// Drives just the `venuePostTypeMap[ key ] ?? DEFAULT_VENUE_POST_TYPE`
		// fallback: a non-empty map that doesn't include the current post type.
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'unknown_event_type',
						getEditorSettings: () => ( {
							gatherpress: {
								config: {
									venuePostTypes: {
										// Map present but does not contain `unknown_event_type`.
										gatherpress_event: 'gatherpress_venue',
									},
								},
							},
						} ),
					};
				}
				return { getEntityRecords: jest.fn( () => [] ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			GetVenuePostFromEventId( 100, 'unknown_event_type' )
		);
		expect( result.current ).toEqual( undefined );
	} );
} );

/**
 * Coverage for useVenueOptions.
 */
describe( 'useVenueOptions', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns empty venueOptions when no venues found', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords: jest.fn( () => null ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueOptions( '', null, 'taxonomy', '_gatherpress_venue' )
		);
		expect( result.current.venueOptions ).toEqual( [] );
	} );

	it( 'returns venue options formatted for combobox', () => {
		const mockVenues = [
			{ id: 1, name: 'Venue One' },
			{ id: 2, name: 'Venue Two' },
		];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords: jest.fn( () => mockVenues ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueOptions( '', null, 'taxonomy', '_gatherpress_venue' )
		);

		expect( result.current.venueOptions ).toEqual( [
			{ value: 1, label: 'Venue One' },
			{ value: 2, label: 'Venue Two' },
		] );
	} );

	it( 'prepends current venue if not in fetched list', () => {
		const mockVenue = { id: 99, name: 'Current Venue' };
		const mockVenues = [
			{ id: 1, name: 'Venue One' },
			{ id: 2, name: 'Venue Two' },
		];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenue ),
				getEntityRecords: jest.fn( () => mockVenues ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueOptions( '', 99, 'taxonomy', '_gatherpress_venue' )
		);

		expect( result.current.venueOptions ).toEqual( [
			{ value: 99, label: 'Current Venue' },
			{ value: 1, label: 'Venue One' },
			{ value: 2, label: 'Venue Two' },
		] );
	} );

	it( 'does not duplicate current venue if already in list', () => {
		const mockVenue = { id: 1, name: 'Venue One' };
		const mockVenues = [
			{ id: 1, name: 'Venue One' },
			{ id: 2, name: 'Venue Two' },
		];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenue ),
				getEntityRecords: jest.fn( () => mockVenues ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueOptions( '', 1, 'taxonomy', '_gatherpress_venue' )
		);

		expect( result.current.venueOptions ).toEqual( [
			{ value: 1, label: 'Venue One' },
			{ value: 2, label: 'Venue Two' },
		] );
	} );

	it( 'handles postType kind with rendered title', () => {
		const mockVenues = [
			{ id: 1, title: { rendered: 'Post Venue One' } },
			{ id: 2, title: { rendered: 'Post Venue Two' } },
		];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords: jest.fn( () => mockVenues ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueOptions( '', null, 'postType', 'gatherpress_venue' )
		);

		expect( result.current.venueOptions ).toEqual( [
			{ value: 1, label: 'Post Venue One' },
			{ value: 2, label: 'Post Venue Two' },
		] );
	} );

	it( 'passes search parameter to query', () => {
		let capturedQuery = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => null ),
				getEntityRecords: jest.fn( ( kind, name, query ) => {
					capturedQuery = query;
					return [];
				} ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () =>
			useVenueOptions(
				'search term',
				null,
				'taxonomy',
				'_gatherpress_venue'
			)
		);

		expect( capturedQuery.search ).toBe( 'search term' );
		expect( capturedQuery.per_page ).toBe( 10 );
		expect( capturedQuery.orderby ).toBe( 'id' );
		expect( capturedQuery.order ).toBe( 'desc' );
	} );

	it( 'uses default taxonomy kind when not specified', () => {
		let capturedKind = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( ( kind ) => {
					capturedKind = kind;
					return null;
				} ),
				getEntityRecords: jest.fn( () => [] ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => useVenueOptions( '', null ) );

		expect( capturedKind ).toBe( 'taxonomy' );
	} );
} );

/**
 * Coverage for usePopularVenues.
 */
describe( 'usePopularVenues', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns empty array when no venues found', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecords: jest.fn( () => null ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => usePopularVenues() );
		expect( result.current ).toEqual( [] );
	} );

	it( 'returns popular venues ordered by count', () => {
		const mockVenues = [
			{ id: 1, name: 'Popular Venue', count: 10 },
			{ id: 2, name: 'Less Popular', count: 5 },
			{ id: 3, name: 'Least Popular', count: 2 },
		];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecords: jest.fn( () => mockVenues ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => usePopularVenues() );
		expect( result.current ).toEqual( mockVenues );
	} );

	it( 'uses default limit of 3', () => {
		let capturedQuery = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecords: jest.fn( ( type, taxonomy, query ) => {
					capturedQuery = query;
					return [];
				} ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => usePopularVenues() );

		// Fetches limit + 1 to account for filtering out online-event term.
		expect( capturedQuery.per_page ).toBe( 4 );
		expect( capturedQuery.orderby ).toBe( 'count' );
		expect( capturedQuery.order ).toBe( 'desc' );
		expect( capturedQuery.hide_empty ).toBe( true );
	} );

	it( 'respects custom limit parameter', () => {
		let capturedQuery = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecords: jest.fn( ( type, taxonomy, query ) => {
					capturedQuery = query;
					return [];
				} ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => usePopularVenues( 5 ) );

		// Fetches limit + 1 to account for filtering out online-event term.
		expect( capturedQuery.per_page ).toBe( 6 );
	} );

	it( 'queries the correct taxonomy', () => {
		let capturedTaxonomy = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecords: jest.fn( ( type, taxonomy ) => {
					capturedTaxonomy = taxonomy;
					return [];
				} ),
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => usePopularVenues() );

		expect( capturedTaxonomy ).toBe( '_gatherpress_venue' );
	} );
} );

/**
 * Coverage for useVenueTaxonomyIds.
 */
describe( 'useVenueTaxonomyIds', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns undefined when skip is true', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEditedPostAttribute: jest.fn(),
				getEntityRecords: jest.fn(),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueTaxonomyIds( '_gatherpress_venue', 42, true )
		);
		expect( result.current ).toBeUndefined();
	} );

	it( 'returns editor attribute when it is an array', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getEditedPostAttribute: jest.fn( () => [ 1, 2, 3 ] ) };
				}
				return { getEntityRecords: jest.fn() };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueTaxonomyIds( '_gatherpress_venue', 42 )
		);
		expect( result.current ).toEqual( [ 1, 2, 3 ] );
	} );

	it( 'returns undefined when editor attribute is not an array and postId is absent', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getEditedPostAttribute: jest.fn( () => undefined ) };
				}
				return { getEntityRecords: jest.fn() };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueTaxonomyIds( '_gatherpress_venue', null )
		);
		expect( result.current ).toBeUndefined();
	} );

	it( 'falls back to getEntityRecords and maps term IDs when editor attribute is missing', () => {
		const mockTerms = [ { id: 10 }, { id: 20 } ];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getEditedPostAttribute: jest.fn( () => undefined ) };
				}
				return {
					getEntityRecords: jest.fn( () => mockTerms ),
				};
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueTaxonomyIds( '_gatherpress_venue', 42 )
		);
		expect( result.current ).toEqual( [ 10, 20 ] );
	} );

	it( 'returns undefined when getEntityRecords has not resolved yet', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getEditedPostAttribute: jest.fn( () => undefined ) };
				}
				return { getEntityRecords: jest.fn( () => null ) };
			} );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () =>
			useVenueTaxonomyIds( '_gatherpress_venue', 42 )
		);
		expect( result.current ).toBeUndefined();
	} );

	it( 'uses context=view in the fallback query', () => {
		let capturedQuery = null;

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return { getEditedPostAttribute: jest.fn( () => undefined ) };
				}
				return {
					getEntityRecords: jest.fn( ( kind, taxonomy, query ) => {
						capturedQuery = query;
						return [];
					} ),
				};
			} );
			return callback( wpSelect );
		} );

		renderHook( () => useVenueTaxonomyIds( '_gatherpress_venue', 42 ) );

		expect( capturedQuery ).toMatchObject( { context: 'view', post: 42 } );
	} );
} );

/**
 * Coverage for findVenuePostById.
 */
describe( 'findVenuePostById', () => {
	it( 'returns null when postId is falsy', () => {
		const selectFunc = jest.fn();
		expect( findVenuePostById( selectFunc, null ) ).toBeNull();
		expect( findVenuePostById( selectFunc, 0 ) ).toBeNull();
		expect( selectFunc ).not.toHaveBeenCalled();
	} );

	it( 'returns null when getPostTypes is not loaded yet', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return { getPostTypes: () => undefined };
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, 123 ) ).toBeNull();
	} );

	it( 'returns null when getPostTypes selector is missing entirely', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {};
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, 123 ) ).toBeNull();
	} );

	it( 'returns the published venue from the first venue-supporting type that owns the ID', () => {
		const postId = 300;
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{ slug: 'page', supports: {} },
						{
							slug: 'gatherpress_venue',
							supports: { 'gatherpress-venue-information': true },
						},
					],
					getEntityRecords: ( kind, postTypeName, query ) =>
						'gatherpress_venue' === postTypeName &&
						query?.include?.[ 0 ] === postId
							? [ { id: postId, status: 'publish' } ]
							: [],
				};
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, postId ) ).toEqual( {
			id: postId,
			status: 'publish',
		} );
	} );

	it( 'skips non-venue-supporting post types when scanning', () => {
		const postId = 301;
		const calls = [];
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{ slug: 'page', supports: {} },
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
						{
							slug: 'gatherpress_venue',
							supports: { 'gatherpress-venue-information': true },
						},
					],
					getEntityRecords: ( kind, postTypeName ) => {
						calls.push( postTypeName );
						return 'gatherpress_venue' === postTypeName
							? [ { id: postId, status: 'publish' } ]
							: [];
					},
				};
			}
			return {};
		};

		findVenuePostById( selectFunc, postId );
		expect( calls ).toEqual( [ 'gatherpress_venue' ] );
	} );

	it( 'returns null when no venue-supporting type owns the ID', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_venue',
							supports: { 'gatherpress-venue-information': true },
						},
					],
					getEntityRecords: () => [],
				};
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, 999 ) ).toBeNull();
	} );

	it( 'returns null when the found post is not published', () => {
		const postId = 302;
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_venue',
							supports: { 'gatherpress-venue-information': true },
						},
					],
					getEntityRecords: () => [
						{ id: postId, status: 'draft' },
					],
				};
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, postId ) ).toBeNull();
	} );

	it( 'returns null when getEntityRecords is still loading (returns non-array)', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_venue',
							supports: { 'gatherpress-venue-information': true },
						},
					],
					getEntityRecords: () => null,
				};
			}
			return {};
		};

		expect( findVenuePostById( selectFunc, 123 ) ).toBeNull();
	} );
} );

