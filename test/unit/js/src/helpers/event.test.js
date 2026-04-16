/**
 * External dependencies.
 */
import { describe, expect, jest, it, beforeEach } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies.
 */
import { dispatch } from '@wordpress/data';

// Mock WordPress modules before importing internal dependencies.
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn().mockReturnValue( {
		removeNotice: jest.fn(),
		createNotice: jest.fn(),
	} ),
} ) );
jest.mock( '@wordpress/core-data', () => ( {
	store: {},
} ) );

/**
 * Internal dependencies.
 */
import {
	hasEventPast,
	hasEventPastNotice,
	isPostTypeSupporting,
	isEventPostType,
	hasValidEventId,
	getEventMeta,
	hasOnlineEventTerm,
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

	it( 'returns false when postType argument is not an event', () => {
		const postId = 456;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		// PostType argument is 'post', not an event.
		expect( hasValidEventId( postId, 'post' ) ).toBe( false );
	} );

	it( 'returns false when postType argument is page', () => {
		const postId = 789;

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => {
			if ( 'core/editor' === store ) {
				return {
					getCurrentPostId: () => 999, // Different from postId.
					getCurrentPostType: () => 'gatherpress_event',
				};
			}
			if ( 'core' === store ) {
				return { getPostType: mockGetPostType };
			}
			return {};
		} );

		// PostType argument is 'page', not an event.
		expect( hasValidEventId( postId, 'page' ) ).toBe( false );
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
