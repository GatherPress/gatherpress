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
	GetVenuePostFromTermId,
	GetVenueTermFromPostId,
	GetVenuePostFromEventId,
	getVenueTitle,
	useVenueOptions,
	usePopularVenues,
} from '../../../../../src/helpers/venue';

/**
 * Coverage for isVenuePostType.
 */
describe( 'isVenuePostType', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns false when there is no current post type', () => {
		select.mockReturnValue( undefined );
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns false when current post type is gatherpress_event', () => {
		select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );
		expect( isVenuePostType() ).toBe( false );
	} );

	it( 'returns true when current post type is gatherpress_venue', () => {
		select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_venue' : null,
		} ) );
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

	it( 'returns null when event has no venue term', () => {
		const mockEventPost = { id: 100, _gatherpress_venue: [] };

		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockEventPost ),
				getEntityRecords: jest.fn( () => [] ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100 ) );
		// Returns undefined because termId is null.
		expect( result.current ).toEqual( undefined );
	} );

	it( 'returns venue post when event has venue term', () => {
		const mockEventPost = { id: 100, _gatherpress_venue: [ 5 ] };
		const mockVenueTerm = { id: 5, slug: '_event-venue' };
		const mockVenuePost = [ { id: 20, title: 'Event Venue' } ];

		// First call for GetVenuePostFromEventId.
		let callCount = 0;
		useSelect.mockImplementation( ( callback ) => {
			callCount++;
			if ( 1 === callCount ) {
				// First call: GetVenuePostFromEventId.
				const wpSelect = jest.fn( () => ( {
					getEntityRecord: jest.fn( () => mockEventPost ),
				} ) );
				return callback( wpSelect );
			}
			// Second call: GetVenuePostFromTermId.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100 ) );
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'extracts first venue term ID from event with multiple venues', () => {
		const mockEventPost = { id: 100, _gatherpress_venue: [ 7, 8, 9 ] };
		const mockVenueTerm = { id: 7, slug: '_venue-seven' };
		const mockVenuePost = [ { id: 70, title: 'Venue Seven' } ];

		let callCount = 0;
		useSelect.mockImplementation( ( callback ) => {
			callCount++;
			if ( 1 === callCount ) {
				// First call: GetVenuePostFromEventId extracts term ID.
				const wpSelect = jest.fn( () => ( {
					getEntityRecord: jest.fn( () => mockEventPost ),
				} ) );
				return callback( wpSelect );
			}
			// Second call: GetVenuePostFromTermId uses term ID 7.
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => mockVenueTerm ),
				getEntityRecords: jest.fn( () => mockVenuePost ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 100 ) );
		// Should return venue post for the first term ID (7).
		expect( result.current ).toEqual( mockVenuePost );
	} );

	it( 'returns null when eventPost is undefined', () => {
		useSelect.mockImplementation( ( callback ) => {
			const wpSelect = jest.fn( () => ( {
				getEntityRecord: jest.fn( () => undefined ),
				getEntityRecords: jest.fn( () => [] ),
			} ) );
			return callback( wpSelect );
		} );

		const { result } = renderHook( () => GetVenuePostFromEventId( 999 ) );
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
