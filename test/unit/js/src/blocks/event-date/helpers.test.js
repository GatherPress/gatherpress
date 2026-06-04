/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

jest.mock( '@src/helpers/event', () => ( {
	isEventPostType: jest.fn(),
	findEventPostById: jest.fn(),
} ) );

/**
 * Internal dependencies
 */
import { isEventPostType, findEventPostById } from '@src/helpers/event';
import { resolveEventDateData } from '@src/blocks/event-date/helpers';

/**
 * Regression coverage for #1733 — event-date block uses the live datetime
 * store for all blocks in an editor session, including query-loop instances
 * that should show their own contextual post's dates.
 *
 * resolveEventDateData is the selector body extracted from the edit.js
 * useSelect call so the routing logic can be tested without rendering the
 * full block component.
 */
describe( 'resolveEventDateData', () => {
	let mockDatetimeStore;
	let mockCoreStore;
	let mockSelect;

	beforeEach( () => {
		isEventPostType.mockReset();
		findEventPostById.mockReset();

		mockDatetimeStore = {
			getDateTimeStart: jest.fn( () => '2025-01-15 09:00:00' ),
			getDateTimeEnd: jest.fn( () => '2025-01-15 11:00:00' ),
			getTimezone: jest.fn( () => 'America/New_York' ),
		};

		mockCoreStore = {
			getPostType: jest.fn( () => ( {
				supports: { 'gatherpress-event-date': true },
			} ) ),
			hasFinishedResolution: jest.fn( () => true ),
			getEntityRecord: jest.fn( () => ( {
				status: 'publish',
				meta: {
					gatherpress_datetime_start: '2025-06-10 14:00:00',
					gatherpress_datetime_end: '2025-06-10 16:00:00',
					gatherpress_timezone: 'America/Chicago',
				},
			} ) ),
		};

		mockSelect = jest.fn( ( store ) => {
			if ( 'gatherpress/datetime' === store ) {
				return mockDatetimeStore;
			}
			if ( 'core' === store ) {
				return mockCoreStore;
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: () => 'gatherpress_event' };
			}
			return {};
		} );
	} );

	describe( 'direct event editing (no query loop)', () => {
		it( 'reads from gatherpress/datetime store when editing a standalone event block', () => {
			isEventPostType.mockReturnValue( true );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event' },
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-01-15 09:00:00' );
			expect( result.dateTimeEnd ).toBe( '2025-01-15 11:00:00' );
			expect( result.timezone ).toBe( 'America/New_York' );
		} );

		it( 'reads from gatherpress/datetime store when context.queryId is undefined', () => {
			isEventPostType.mockReturnValue( true );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event', queryId: undefined },
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-01-15 09:00:00' );
		} );
	} );

	describe( 'inside a query loop (context.queryId is a number)', () => {
		it( 'fetches from entity record instead of datetime store when queryId is 0', () => {
			isEventPostType.mockReturnValue( true );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event', queryId: 0 },
				42,
				false
			);

			// Bug: the datetime store IS called before the fix — test will fail here.
			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-06-10 14:00:00' );
			expect( result.timezone ).toBe( 'America/Chicago' );
		} );

		it( 'fetches from entity record instead of datetime store when queryId is a positive integer', () => {
			isEventPostType.mockReturnValue( true );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event', queryId: 1 },
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-06-10 14:00:00' );
		} );

		it( 'returns isValidEvent false for an unpublished queried post', () => {
			isEventPostType.mockReturnValue( true );
			mockCoreStore.getEntityRecord.mockReturnValue( {
				status: 'draft',
				meta: {
					gatherpress_datetime_start: '2025-06-10 14:00:00',
					gatherpress_datetime_end: '2025-06-10 16:00:00',
					gatherpress_timezone: 'UTC',
				},
			} );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event', queryId: 0 },
				42,
				false
			);

			expect( result.isValidEvent ).toBe( false );
		} );
	} );

	describe( 'edge cases', () => {
		it( 'returns isValidEvent false when postId is null', () => {
			isEventPostType.mockReturnValue( true );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event' },
				null,
				false
			);

			expect( result ).toEqual( { isValidEvent: false } );
			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
		} );

		it( 'returns isValidEvent false when post type does not support event-date', () => {
			isEventPostType.mockReturnValue( false );
			mockCoreStore.getPostType.mockReturnValue( {
				supports: {},
			} );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'post', queryId: 0 },
				42,
				false
			);

			expect( result ).toEqual( { isValidEvent: false } );
		} );

		it( 'returns isLoading true while entity record resolution is pending', () => {
			isEventPostType.mockReturnValue( false );
			mockCoreStore.hasFinishedResolution.mockReturnValue( false );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'gatherpress_event', queryId: 1 },
				42,
				false
			);

			expect( result ).toEqual( { isLoading: true, isValidEvent: false } );
		} );

		it( 'resolves postId override target from non-event host when outside a query loop', () => {
			isEventPostType.mockReturnValue( false );
			mockCoreStore.getPostType.mockReturnValue( { supports: {} } );
			findEventPostById.mockReturnValue( {
				meta: {
					gatherpress_datetime_start: '2025-08-01 10:00:00',
					gatherpress_datetime_end: '2025-08-01 12:00:00',
					gatherpress_timezone: 'Europe/London',
				},
			} );

			const result = resolveEventDateData(
				mockSelect,
				{ postType: 'page' },
				99,
				true
			);

			expect( findEventPostById ).toHaveBeenCalledWith( mockSelect, 99 );
			expect( result.dateTimeStart ).toBe( '2025-08-01 10:00:00' );
			expect( result.isValidEvent ).toBe( true );
		} );
	} );
} );
