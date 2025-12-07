/**
 * External dependencies.
 */
import { describe, expect, jest, it } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies.
 */
import { dispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	hasEventPast,
	hasEventPastNotice,
	isEventPostType,
	hasValidEventId,
} from '../../../../../src/helpers/event';
import { dateTimeDatabaseFormat } from '../../../../../src/helpers/datetime';

// Mock the @wordpress/data module
jest.mock( '@wordpress/data', () => ( {
	select: jest.fn(),
	dispatch: jest.fn().mockReturnValue( {
		removeNotice: jest.fn(),
		createNotice: jest.fn(),
	} ),
} ) );

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
			return {};
		} );

		expect( isEventPostType() ).toBe( false );
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
				};
			}
			if ( 'core' === store ) {
				return {
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
				};
			}
			if ( 'core' === store ) {
				return {
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
				};
			}
			if ( 'core' === store ) {
				return {
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
				};
			}
			if ( 'core' === store ) {
				return {
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
				};
			}
			if ( 'core' === store ) {
				return {
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
				};
			}
			if ( 'core' === store ) {
				return {
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
			return {};
		} );

		expect( hasValidEventId( null ) ).toBe( true );
	} );
} );

/**
 * Coverage for hasEventPast.
 */
describe( 'hasEventPast', () => {
	it( 'returns true', () => {
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.subtract( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

		expect( hasEventPast() ).toBe( true );
	} );

	it( 'returns false', () => {
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.add( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

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
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					datetime_end: moment()
						.subtract( 1, 'days' )
						.format( dateTimeDatabaseFormat ),
					timezone: 'America/New_York',
				},
			},
		};

		require( '@wordpress/data' ).select.mockImplementation( ( store ) => ( {
			getCurrentPostType: () =>
				'core/editor' === store ? 'gatherpress_event' : null,
		} ) );

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
