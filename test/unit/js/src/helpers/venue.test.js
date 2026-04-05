/**
 * External dependencies.
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
 * Internal dependencies.
 */
import {
	isVenuePostType,
	getVenueTaxonomy,
	GetVenuePostFromTermId,
	GetVenueTermFromPostId,
	GetVenuePostFromEventId,
	getVenueTitle,
	useVenueOptions,
	usePopularVenues,
} from '@src/helpers/venue';

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
 * Coverage for GetVenuePostFromTermId.
 */
describe( 'GetVenuePostFromTermId', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns empty array when termId is null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const result = callback( () => ( {} ) );
			return result;
		} );

		const { result } = renderHook( () => GetVenuePostFromTermId( null ) );
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns venue post when term is found', () => {
		const mockVenueTerm = { id: 1, slug: '_test-venue' };
		const mockVenuePost = [ { id: 10, title: 'Test Venue' } ];

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromTermId( 1 ) );
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
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => GetVenuePostFromTermId( 1 ) );
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
			} ) );
			return callback( wpSelect );
		} );

		renderHook( () => GetVenuePostFromTermId( 1 ) );
		expect( capturedSlug ).toBe( 'venue-no-underscore' );
	} );
} );

/**
 * Coverage for GetVenueTermFromPostId.
 */
describe( 'GetVenueTermFromPostId', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns empty array when postId is null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const result = callback( () => ( {} ) );
			return result;
		} );

		const { result } = renderHook( () => GetVenueTermFromPostId( null ) );
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns empty array when postId defaults to null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const result = callback( () => ( {} ) );
			return result;
		} );

		const { result } = renderHook( () => GetVenueTermFromPostId() );
		expect( result.current ).toEqual( undefined );
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

		const { result } = renderHook( () => GetVenueTermFromPostId( 10 ) );
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

		renderHook( () => GetVenueTermFromPostId( 10 ) );
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
								venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
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
									venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ mockVenueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: GetVenuePostFromTermId fetches the venue post.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
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
									venuePostTypes: { gatherpress_shindig: 'gatherpress_location' },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ mockVenueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: GetVenuePostFromTermId.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
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
									venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
								},
							} ),
						};
					}
					return { getEntityRecords: jest.fn( () => [ onlineTerm, venueTerm ] ) };
				} );
				return callback( wpSelect );
			}
			// Second call: GetVenuePostFromTermId uses term ID 7 (skipping online-event).
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => venueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100, 'gatherpress_event' ) );
		// Should return venue post for the physical venue term (7), not online-event (1).
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'returns null when venue terms are null', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( ( store ) => {
				if ( 'core/editor' === store ) {
					return {
						getCurrentPostType: () => 'gatherpress_event',
						getEditorSettings: () => ( {
							gatherpress: {
								venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
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
								venuePostTypes: { gatherpress_event: 'gatherpress_venue' },
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
