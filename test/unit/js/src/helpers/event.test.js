/**
 * External dependencies
 */
import { describe, expect, jest, it, beforeEach } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies
 */
import { dispatch } from '@wordpress/data';

// Mock WordPress modules before importing internal dependencies.
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	useSelect: jest.fn( ( cb ) => cb( jest.requireMock( '@wordpress/data' ).select ) ),
	dispatch: jest.fn().mockReturnValue( {
		removeNotice: jest.fn(),
		createNotice: jest.fn(),
	} ),
} ) );
jest.mock( '@wordpress/core-data', () => ( {
	store: {},
} ) );

/**
 * Internal dependencies
 */
import {
	hasEventPast,
	hasEventPastNotice,
	isPostTypeSupporting,
	usePostTypeSupports,
	isEventPostType,
	hasValidEventId,
	findEventPostById,
	getEventMeta,
	hasOnlineEventTerm,
	isPerEventRsvpMode,
	isRsvpEnabledForEvent,
	isOpenRsvpEnabled,
} from '@src/helpers/event';
import { dateTimeDatabaseFormat } from '@src/helpers/datetime';

/**
 * Helper to create a mock getPostType function.
 * Returns supports.event_date: true for gatherpress_event, false for others.
 *
 * @param {string} slug The post type slug.
 * @return {Object|null} The post type object with supports.
 */
function mockGetPostType( slug ) {
	if ( 'gatherpress_event' === slug ) {
		return {
			supports: {
				'gatherpress-event-date': true,
				'gatherpress-rsvp': true,
			},
		};
	}
	return { supports: {} };
}

/**
 * Coverage for isPostTypeSupporting.
 */
describe( 'isPostTypeSupporting', () => {
	it( 'returns true when post type supports the given feature', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isPostTypeSupporting( 'gatherpress-event-date' ) ).toBe( true );
		expect( isPostTypeSupporting( 'gatherpress-rsvp' ) ).toBe( true );
	} );

	it( 'returns false when post type does not support the given feature', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'post',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isPostTypeSupporting( 'gatherpress-event-date' ) ).toBe( false );
		expect( isPostTypeSupporting( 'gatherpress-rsvp' ) ).toBe( false );
	} );

	it( 'returns true when postType argument supports the feature', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isPostTypeSupporting( 'gatherpress-rsvp', 'gatherpress_event' ) ).toBe( true );
		expect( isPostTypeSupporting( 'gatherpress-rsvp', 'post' ) ).toBe( false );
	} );

	it( 'returns false when select returns undefined', () => {
		require( '@wordpress/data' ).select.mockReturnValue( undefined );

		expect( isPostTypeSupporting( 'gatherpress-rsvp' ) ).toBe( false );
	} );
} );

/**
 * Coverage for usePostTypeSupports — the reactive variant of isPostTypeSupporting.
 */
describe( 'usePostTypeSupports', () => {
	it( 'returns true when the resolved post type has the support', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect(
			usePostTypeSupports( 'gatherpress-event-date', 'gatherpress_event' )
		).toBe( true );
	} );

	it( 'returns false when the resolved post type lacks the support', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect(
			usePostTypeSupports( 'gatherpress-event-date', 'post' )
		).toBe( false );
	} );

	it( 'falls back to the editor post type when no postType is given', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( usePostTypeSupports( 'gatherpress-rsvp' ) ).toBe( true );
	} );

	it( 'returns false when no post type can be resolved', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => undefined };
			}
			return {};
		} );

		expect( usePostTypeSupports( 'gatherpress-event-date' ) ).toBe( false );
	} );

	it( 'subscribes via useSelect so the support gate is reactive', () => {
		// Confirms the hook delegates to useSelect — the whole reason the hook
		// exists, since the non-reactive sibling leaves blocks dimmed when the
		// post-type definition isn't cached on first render.
		const { useSelect } = require( '@wordpress/data' );
		useSelect.mockClear();
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		usePostTypeSupports( 'gatherpress-event-date', 'gatherpress_event' );

		expect( useSelect ).toHaveBeenCalledTimes( 1 );
	} );

	it( 're-evaluates when getPostType resolves later', () => {
		// Simulates the actual race the hook is fixing: on first render the
		// post-type definition isn't cached yet (returns undefined), then
		// resolves on a subsequent invocation. The hook must reflect the new
		// value rather than caching the false negative.
		const { select } = require( '@wordpress/data' );
		let resolved = false;
		select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: ( slug ) => {
						if ( ! resolved ) {
							return undefined;
						}
						return mockGetPostType( slug );
					},
				};
			}
			return {};
		} );

		expect(
			usePostTypeSupports( 'gatherpress-event-date', 'gatherpress_event' )
		).toBe( false );

		resolved = true;

		expect(
			usePostTypeSupports( 'gatherpress-event-date', 'gatherpress_event' )
		).toBe( true );
	} );
} );

/**
 * Coverage for isEventPostType.
 */
describe( 'isEventPostType', () => {
	it( 'returns true when post type is gatherpress_event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType() ).toBe( true );
	} );

	it( 'returns false when post type is post', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'post',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType() ).toBe( false );
	} );

	it( 'returns false when post type is page', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'page',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType() ).toBe( false );
	} );

	it( 'returns false when select returns undefined', () => {
		require( '@wordpress/data' ).select.mockReturnValue( undefined );

		expect( isEventPostType() ).toBe( false );
	} );

	it( 'returns false when getCurrentPostType returns undefined', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => undefined,
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType() ).toBe( false );
	} );

	it( 'returns true when postType argument is gatherpress_event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType( 'gatherpress_event' ) ).toBe( true );
	} );

	it( 'returns false when postType argument is post', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType( 'post' ) ).toBe( false );
	} );

	it( 'returns false when postType argument is page', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( isEventPostType( 'page' ) ).toBe( false );
	} );
} );

/**
 * Coverage for hasValidEventId.
 */
describe( 'hasValidEventId', () => {
	it( 'returns true when no postId and current post is event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasValidEventId() ).toBe( true );
	} );

	it( 'returns false when no postId and current post is not event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'post',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasValidEventId() ).toBe( false );
	} );

	it( 'returns true when postId matches current post being edited', () => {
		const postId = 123;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => postId,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( postType, postTypeName, id ) => {
						if ( 'gatherpress_event' === postTypeName && postId === id ) {
							return {
								id: postId,
								status: 'draft', // Even draft is valid if it's the current post.
							};
						}
						return null;
					},
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( true );
	} );

	it( 'returns true when postId points to published event', () => {
		const postId = 456;
		const currentPostId = 999; // Different from postId.

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => currentPostId,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( postType, postTypeName, id ) => {
						if ( 'gatherpress_event' === postTypeName && postId === id ) {
							return {
								id: postId,
								status: 'publish',
							};
						}
						return null;
					},
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( true );
	} );

	it( 'returns false when postId points to draft event (not current post)', () => {
		const postId = 789;
		const currentPostId = 999; // Different from postId.

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => currentPostId,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( postType, postTypeName, id ) => {
						if ( 'gatherpress_event' === postTypeName && postId === id ) {
							return {
								id: postId,
								status: 'draft',
							};
						}
						return null;
					},
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( false );
	} );

	it( 'returns false when postId points to non-existent post', () => {
		const postId = 111;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: () => null,
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( false );
	} );

	it( 'returns false when postId provided but post is undefined', () => {
		const postId = 222;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: () => undefined,
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( false );
	} );

	it( 'returns false when postId points to private event (not current post)', () => {
		const postId = 333;
		const currentPostId = 999; // Different from postId.

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => currentPostId,
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( postType, postTypeName, id ) => {
						if ( 'gatherpress_event' === postTypeName && postId === id ) {
							return {
								id: postId,
								status: 'private',
							};
						}
						return null;
					},
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( false );
	} );

	it( 'returns true when postId is null and current post is event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasValidEventId( null ) ).toBe( true );
	} );

	it( 'returns false when postId matches current post but current post is not an event', () => {
		const postId = 123;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => postId,
					getCurrentPostType: () => 'post', // Not an event.
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasValidEventId( postId ) ).toBe( false );
	} );

	it( 'returns false when non-event postType hint is given but the editor host is event-supporting and the override target does not exist', () => {
		const postId = 456;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					// Editor host is event-supporting, so the lookup falls
					// back to that type. Return null to simulate a missing
					// post for the override ID.
					getEntityRecord: () => null,
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId, 'post' ) ).toBe( false );
	} );

	it( 'returns false when postType hint is page and the editor host event lookup returns nothing', () => {
		const postId = 789;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: () => null,
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId, 'page' ) ).toBe( false );
	} );

	it( 'returns false when both postType hint and editor host are non-event and registry scan finds nothing', () => {
		const postId = 458;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999,
					getCurrentPostType: () => 'page',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					// No getPostTypes — registry not loaded.
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId, 'page' ) ).toBe( false );
	} );

	it( 'uses the postType hint when it is event-supporting (Query Loop fast path)', () => {
		const postId = 460;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					// Editor host is non-event — only the hint is event-supporting.
					getCurrentPostType: () => 'page',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( kind, postTypeName, id ) => {
						if (
							'gatherpress_event' === postTypeName &&
							postId === id
						) {
							return { id: postId, status: 'publish' };
						}
						return null;
					},
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId, 'gatherpress_event' ) ).toBe( true );
	} );

	it( 'returns true when host postType is non-event but override resolves to a published event', () => {
		const postId = 456;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					getCurrentPostType: () => 'page',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					// Registry includes one event-supporting post type.
					getPostTypes: () => [
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
						{ slug: 'page', supports: {} },
					],
					getEntityRecords: ( kind, postTypeName, query ) => {
						if (
							'gatherpress_event' === postTypeName &&
							query?.include?.[ 0 ] === postId
						) {
							return [ { id: postId, status: 'publish' } ];
						}
						return [];
					},
				};
			}
			return {};
		} );

		// postType hint is 'page', but the override target is a real event.
		expect( hasValidEventId( postId, 'page' ) ).toBe( true );
	} );

	it( 'returns false when override target is found but not published', () => {
		const postId = 457;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999,
					getCurrentPostType: () => 'page',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getPostTypes: () => [
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: () => [
						{ id: postId, status: 'draft' },
					],
				};
			}
			return {};
		} );

		expect( hasValidEventId( postId, 'page' ) ).toBe( false );
	} );

	it( 'accepts a useSelect-style select callback as the first argument and uses it for reactive reads', () => {
		const postId = 460;
		// Custom select function that doesn't touch the global @wordpress/data
		// mock — proves the back-compat shim picks up `selectFunc` from the
		// first argument when it's callable.
		const selectFunc = ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Not the override.
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: ( kind, postTypeName, id ) =>
						'gatherpress_event' === postTypeName && postId === id
							? { id: postId, status: 'publish' }
							: null,
				};
			}
			return {};
		};

		expect( hasValidEventId( selectFunc, postId, 'gatherpress_event' ) ).toBe(
			true
		);
	} );

	it( 'returns event-supporting status of the editor host when called with a select callback and no postId', () => {
		const selectFunc = ( store ) => {
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		};

		expect( hasValidEventId( selectFunc ) ).toBe( true );
	} );
} );

/**
 * Coverage for findEventPostById.
 */
describe( 'findEventPostById', () => {
	it( 'returns null when postId is falsy', () => {
		const selectFunc = jest.fn();
		expect( findEventPostById( selectFunc, null ) ).toBeNull();
		expect( findEventPostById( selectFunc, 0 ) ).toBeNull();
		expect( selectFunc ).not.toHaveBeenCalled();
	} );

	it( 'returns null when getPostTypes is not loaded yet', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return { getPostTypes: () => undefined };
			}
			return {};
		};

		expect( findEventPostById( selectFunc, 123 ) ).toBeNull();
	} );

	it( 'returns null when getPostTypes selector is missing entirely', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {};
			}
			return {};
		};

		expect( findEventPostById( selectFunc, 123 ) ).toBeNull();
	} );

	it( 'returns the published event from the first event-supporting type that owns the ID', () => {
		const postId = 200;
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{ slug: 'page', supports: {} },
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: ( kind, postTypeName, query ) =>
						'gatherpress_event' === postTypeName &&
						query?.include?.[ 0 ] === postId
							? [ { id: postId, status: 'publish' } ]
							: [],
				};
			}
			return {};
		};

		const result = findEventPostById( selectFunc, postId );
		expect( result ).toEqual( { id: postId, status: 'publish' } );
	} );

	it( 'skips non-event-supporting post types when scanning', () => {
		const postId = 201;
		const calls = [];
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{ slug: 'page', supports: {} },
						{ slug: 'post', supports: {} },
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: ( kind, postTypeName ) => {
						calls.push( postTypeName );
						return 'gatherpress_event' === postTypeName
							? [ { id: postId, status: 'publish' } ]
							: [];
					},
				};
			}
			return {};
		};

		findEventPostById( selectFunc, postId );
		expect( calls ).toEqual( [ 'gatherpress_event' ] );
	} );

	it( 'returns null when no event-supporting type owns the ID', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: () => [],
				};
			}
			return {};
		};

		expect( findEventPostById( selectFunc, 999 ) ).toBeNull();
	} );

	it( 'returns null when the found post is not published', () => {
		const postId = 202;
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: () => [ { id: postId, status: 'draft' } ],
				};
			}
			return {};
		};

		expect( findEventPostById( selectFunc, postId ) ).toBeNull();
	} );

	it( 'returns null when getEntityRecords is still loading (returns non-array)', () => {
		const selectFunc = ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostTypes: () => [
						{
							slug: 'gatherpress_event',
							supports: { 'gatherpress-event-date': true },
						},
					],
					getEntityRecords: () => null,
				};
			}
			return {};
		};

		expect( findEventPostById( selectFunc, 123 ) ).toBeNull();
	} );
} );

/**
 * Coverage for hasEventPast.
 */
describe( 'hasEventPast', () => {
	it( 'returns true', () => {
		const pastEnd = moment()
			.subtract( 1, 'days' )
			.format( dateTimeDatabaseFormat );

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'gatherpress/datetime' === store ) {
				return {
					getDateTimeEnd: () => pastEnd,
					getTimezone: () => 'America/New_York',
				};
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasEventPast() ).toBe( true );
	} );

	it( 'returns false', () => {
		const futureEnd = moment()
			.add( 1, 'days' )
			.format( dateTimeDatabaseFormat );

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'gatherpress/datetime' === store ) {
				return {
					getDateTimeEnd: () => futureEnd,
					getTimezone: () => 'America/New_York',
				};
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasEventPast() ).toBe( false );
	} );

	it( 'returns false when the stored end is undefined (?? fallback)', () => {
		// Drives the `?? ''` fallback on the optional-chained store call. With
		// getDateTimeEnd returning undefined, the helper falls back to '' which
		// moment() treats as Invalid Date — `now > NaN` is false, so the past
		// check resolves to false rather than throwing.
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'gatherpress/datetime' === store ) {
				return {
					getDateTimeEnd: () => undefined,
					getTimezone: () => 'America/New_York',
				};
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		expect( hasEventPast() ).toBe( false );
	} );
} );

/**
 * Coverage for hasEventPastNotice.
 */
describe( 'hasEventPastNotice', () => {
	it( 'no notice if not set', () => {
		hasEventPastNotice();

		expect( dispatch( 'core/notices' ).createNotice ).not.toHaveBeenCalled();
	} );

	it( 'notice is set', () => {
		const pastEnd = moment()
			.subtract( 1, 'days' )
			.format( dateTimeDatabaseFormat );

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'gatherpress/datetime' === store ) {
				return {
					getDateTimeEnd: () => pastEnd,
					getTimezone: () => 'America/New_York',
				};
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		hasEventPastNotice();

		expect( dispatch( 'core/notices' ).createNotice ).toHaveBeenCalledWith(
			'warning',
			'This event has already passed.',
			{
				id: 'gatherpress_event_past',
				isDismissible: false,
			},
		);
	} );
} );

/**
 * Coverage for getEventMeta.
 */
describe( 'getEventMeta', () => {
	let mockSelect;

	beforeEach( () => {
		// Create mock select function.
		mockSelect = jest.fn( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: jest.fn(),
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn(),
					getEditedPostAttribute: jest.fn(),
				};
			}
			return {};
		} );
	} );

	it( 'should return defaults when no post is an event', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'post' ),
					getEditedPostAttribute: jest.fn(),
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, null, {} );

		expect( result ).toEqual( {
			maxGuestLimit: 0,
			enableRsvp: true,
			enableAnonymousRsvp: false,
		} );
	} );

	it( 'should get live editor data when current post is an event (no override)', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'gatherpress_event' ),
					getEditedPostAttribute: jest.fn( ( attr ) => {
						if ( 'meta' === attr ) {
							return {
								gatherpress_max_guest_limit: 5,
								gatherpress_enable_anonymous_rsvp: true,
							};
						}
						return null;
					} ),
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, null, {} );

		expect( result ).toEqual( {
			maxGuestLimit: 5,
			enableRsvp: true,
			enableAnonymousRsvp: true,
		} );
	} );

	it( 'should get live editor data when postId from context matches current post', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'gatherpress_event' ),
					getEditedPostAttribute: jest.fn( ( attr ) => {
						if ( 'meta' === attr ) {
							return {
								gatherpress_max_guest_limit: 10,
								gatherpress_enable_anonymous_rsvp: false,
							};
						}
						return null;
					} ),
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		// PostId from context, but no explicit override in attributes.
		const result = getEventMeta( mockSelect, 123, {} );

		expect( result ).toEqual( {
			maxGuestLimit: 10,
			enableRsvp: true,
			enableAnonymousRsvp: false,
		} );
	} );

	it( 'should get saved data when attributes.postId is explicitly set (override)', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: jest.fn( ( postType, slug, postId ) => {
						if ( 'gatherpress_event' === slug && 456 === postId ) {
							return {
								meta: {
									gatherpress_max_guest_limit: 20,
									gatherpress_enable_anonymous_rsvp: true,
								},
							};
						}
						return null;
					} ),
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'gatherpress_event' ),
					getEditedPostAttribute: jest.fn(),
				};
			}
			return {};
		} );

		// Explicit override via attributes.postId.
		const result = getEventMeta( mockSelect, 456, { postId: 456 } );

		expect( result ).toEqual( {
			maxGuestLimit: 20,
			enableRsvp: true,
			enableAnonymousRsvp: true,
		} );
	} );

	it( 'should handle missing meta gracefully', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: jest.fn( () => ( {
						meta: {},
					} ) ),
				};
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, 789, { postId: 789 } );

		expect( result ).toEqual( {
			maxGuestLimit: 0,
			enableRsvp: true,
			enableAnonymousRsvp: false,
		} );
	} );

	it( 'should handle null post gracefully', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecord: jest.fn( () => null ),
				};
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, 999, { postId: 999 } );

		expect( result ).toEqual( {
			maxGuestLimit: 0,
			enableRsvp: true,
			enableAnonymousRsvp: false,
		} );
	} );

	it( 'should convert truthy values to boolean for enableAnonymousRsvp', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'gatherpress_event' ),
					getEditedPostAttribute: jest.fn( ( attr ) => {
						if ( 'meta' === attr ) {
							return {
								gatherpress_max_guest_limit: 3,
								gatherpress_enable_anonymous_rsvp: 1, // Truthy number.
							};
						}
						return null;
					} ),
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, null, {} );

		expect( result.enableAnonymousRsvp ).toBe( true );
	} );

	it( 'should convert falsy values to boolean for enableAnonymousRsvp', () => {
		mockSelect.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: jest.fn( () => 'gatherpress_event' ),
					getEditedPostAttribute: jest.fn( ( attr ) => {
						if ( 'meta' === attr ) {
							return {
								gatherpress_max_guest_limit: 3,
								gatherpress_enable_anonymous_rsvp: 0, // Falsy number.
							};
						}
						return null;
					} ),
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		const result = getEventMeta( mockSelect, null, {} );

		expect( result.enableAnonymousRsvp ).toBe( false );
	} );
} );

/**
 * Coverage for hasOnlineEventTerm.
 */
describe( 'hasOnlineEventTerm', () => {
	it( 'returns false when online-event term does not exist', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => null,
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'returns false when online-event term array is empty', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [],
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'returns false when postId provided but post not found', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 1 } ],
					getEntityRecord: () => null,
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( false );
	} );

	it( 'returns false when postId provided but post has no venue terms', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 1 } ],
					getEntityRecord: () => ( {
						id: 123,
						_gatherpress_venue: [],
					} ),
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( false );
	} );

	it( 'returns false when postId provided but venue terms undefined', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 1 } ],
					getEntityRecord: () => ( {
						id: 123,
					} ),
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( false );
	} );

	it( 'returns true when postId provided and has matching online-event term', () => {
		const onlineTermId = 42;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: onlineTermId } ],
					getEntityRecord: () => ( {
						id: 123,
						_gatherpress_venue: [ onlineTermId ],
					} ),
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( true );
	} );

	it( 'returns false when postId provided but term does not match', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ],
					getEntityRecord: () => ( {
						id: 123,
						_gatherpress_venue: [ 99 ], // Different term ID.
					} ),
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( false );
	} );

	it( 'returns false when no postId and current post is not event', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ],
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'post',
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'returns false when no postId, current post is event but no venue terms', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ],
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
					getEditedPostAttribute: () => [],
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'returns false when no postId, current post is event but venue terms undefined', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ],
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
					getEditedPostAttribute: () => undefined,
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'returns true when no postId, current post is event with online-event term', () => {
		const onlineTermId = 42;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: onlineTermId } ],
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
					getEditedPostAttribute: () => [ onlineTermId ],
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( true );
	} );

	it( 'returns false when no postId, current post is event but term does not match', () => {
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ],
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
					getEditedPostAttribute: () => [ 99 ], // Different term ID.
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( false );
	} );

	it( 'handles string comparison for term IDs correctly', () => {
		// Term IDs might be strings or numbers - verify comparison works.
		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: 42 } ], // Number.
				};
			}
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostType: () => 'gatherpress_event',
					getEditedPostAttribute: () => [ '42' ], // String.
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm() ).toBe( true );
	} );

	it( 'handles multiple venue terms including online-event', () => {
		const onlineTermId = 42;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: mockGetPostType,
					getEntityRecords: () => [ { id: onlineTermId } ],
					getEntityRecord: () => ( {
						id: 123,
						_gatherpress_venue: [ 10, onlineTermId, 20 ], // Multiple terms.
					} ),
				};
			}
			return {};
		} );

		expect( hasOnlineEventTerm( 123 ) ).toBe( true );
	} );
} );

/**
 * Coverage for isPerEventRsvpMode.
 */
describe( 'isPerEventRsvpMode', () => {
	it( 'returns true for per_event_on', () => {
		expect( isPerEventRsvpMode( 'per_event_on' ) ).toBe( true );
	} );

	it( 'returns true for per_event_off', () => {
		expect( isPerEventRsvpMode( 'per_event_off' ) ).toBe( true );
	} );

	it( 'returns false for all_on', () => {
		expect( isPerEventRsvpMode( 'all_on' ) ).toBe( false );
	} );

	it( 'returns false for disabled', () => {
		expect( isPerEventRsvpMode( 'disabled' ) ).toBe( false );
	} );
} );

/**
 * Coverage for isOpenRsvpEnabled.
 */
describe( 'isOpenRsvpEnabled', () => {
	it( 'returns true when enableOpenRsvp is true', () => {
		expect( isOpenRsvpEnabled( true ) ).toBe( true );
	} );

	it( 'returns false when enableOpenRsvp is false', () => {
		expect( isOpenRsvpEnabled( false ) ).toBe( false );
	} );

	it( 'returns false when enableOpenRsvp is undefined', () => {
		expect( isOpenRsvpEnabled( undefined ) ).toBe( false );
	} );

	it( 'returns false for truthy non-boolean values', () => {
		expect( isOpenRsvpEnabled( 1 ) ).toBe( false );
		expect( isOpenRsvpEnabled( 'true' ) ).toBe( false );
	} );
} );

/**
 * Coverage for isRsvpEnabledForEvent.
 */
describe( 'isRsvpEnabledForEvent', () => {
	it( 'returns true for all_on mode regardless of enableRsvp', () => {
		expect( isRsvpEnabledForEvent( 'all_on', true ) ).toBe( true );
		expect( isRsvpEnabledForEvent( 'all_on', false ) ).toBe( true );
	} );

	it( 'returns true for per_event_on when enableRsvp is true', () => {
		expect( isRsvpEnabledForEvent( 'per_event_on', true ) ).toBe( true );
	} );

	it( 'returns false for per_event_on when enableRsvp is false', () => {
		expect( isRsvpEnabledForEvent( 'per_event_on', false ) ).toBe( false );
	} );

	it( 'returns true for per_event_off when enableRsvp is true', () => {
		expect( isRsvpEnabledForEvent( 'per_event_off', true ) ).toBe( true );
	} );

	it( 'returns false for per_event_off when enableRsvp is false', () => {
		expect( isRsvpEnabledForEvent( 'per_event_off', false ) ).toBe( false );
	} );

	it( 'returns false for disabled mode regardless of enableRsvp', () => {
		expect( isRsvpEnabledForEvent( 'disabled', true ) ).toBe( false );
		expect( isRsvpEnabledForEvent( 'disabled', false ) ).toBe( false );
	} );
} );
