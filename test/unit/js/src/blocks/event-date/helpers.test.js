/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

jest.mock( '@src/helpers/event', () => ( {
	findEventPostById: jest.fn(),
} ) );

/**
 * Internal dependencies
 */
import { findEventPostById } from '@src/helpers/event';
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
			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				undefined,
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-01-15 09:00:00' );
			expect( result.dateTimeEnd ).toBe( '2025-01-15 11:00:00' );
			expect( result.timezone ).toBe( 'America/New_York' );
			expect( result.isValidEvent ).toBe( true );
			expect( result.isLoading ).toBe( false );
		} );

		it( 'reads from gatherpress/datetime store when contextQueryId is undefined', () => {
			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				undefined,
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-01-15 09:00:00' );
		} );
	} );

	describe( 'inside a query loop (contextQueryId is a number)', () => {
		it( 'fetches from entity record instead of datetime store when queryId is 0', () => {
			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				0,
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-06-10 14:00:00' );
			expect( result.timezone ).toBe( 'America/Chicago' );
		} );

		it( 'fetches from entity record instead of datetime store when queryId is a positive integer', () => {
			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				1,
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-06-10 14:00:00' );
		} );

		it( 'returns isValidEvent false for an unpublished queried post', () => {
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
				'gatherpress_event',
				0,
				42,
				false
			);

			expect( result.isValidEvent ).toBe( false );
		} );
	} );

	describe( 'edge cases', () => {
		it( 'returns isValidEvent false when postId is null', () => {
			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				undefined,
				null,
				false
			);

			expect( result.isValidEvent ).toBe( false );
			expect( result.isLoading ).toBe( false );
			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
		} );

		it( 'returns isValidEvent false when post type does not support event-date', () => {
			mockCoreStore.getPostType.mockReturnValue( {
				supports: {},
			} );

			const result = resolveEventDateData(
				mockSelect,
				'post',
				0,
				42,
				false
			);

			expect( result.isValidEvent ).toBe( false );
			expect( result.isLoading ).toBe( false );
		} );

		it( 'returns isValidEvent false and empty dates when entity record is null', () => {
			mockCoreStore.getEntityRecord.mockReturnValue( null );

			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				1,
				42,
				false
			);

			expect( result.isValidEvent ).toBe( false );
			expect( result.dateTimeStart ).toBeUndefined();
			expect( result.dateTimeEnd ).toBeUndefined();
			expect( result.timezone ).toBeUndefined();
			expect( result.isLoading ).toBe( false );
		} );

		it( 'returns isLoading true while entity record resolution is pending', () => {
			mockCoreStore.hasFinishedResolution.mockReturnValue( false );

			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				1,
				42,
				false
			);

			expect( result ).toEqual( {
				dateTimeStart: undefined,
				dateTimeEnd: undefined,
				timezone: undefined,
				isLoading: true,
				isValidEvent: false,
			} );
		} );

		it( 'resolves postId override target from non-event host when outside a query loop', () => {
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
				'page',
				undefined,
				99,
				true
			);

			expect( findEventPostById ).toHaveBeenCalledWith( mockSelect, 99 );
			expect( result.dateTimeStart ).toBe( '2025-08-01 10:00:00' );
			expect( result.isValidEvent ).toBe( true );
			expect( result.isLoading ).toBe( false );
		} );

		it( 'falls back to editor post type when contextPostType is null', () => {
			const result = resolveEventDateData(
				mockSelect,
				null,
				undefined,
				42,
				false
			);

			// Editor post type 'gatherpress_event' supports event-date, so
			// live-store path fires.
			expect( mockDatetimeStore.getDateTimeStart ).toHaveBeenCalled();
			expect( result.isValidEvent ).toBe( true );
		} );

		it( 'does not use datetime store in site editor even when contextPostType is an event type', () => {
			// In the site editor the editor document type is wp_template, not an
			// event. isDirectEditingEvent must check the editor type, not the
			// context type, so the live-store path is skipped correctly.
			mockSelect = jest.fn( ( store ) => {
				if ( 'gatherpress/datetime' === store ) {
					return mockDatetimeStore;
				}
				if ( 'core' === store ) {
					return {
						...mockCoreStore,
						getPostType: jest.fn( ( type ) => {
							if ( 'wp_template' === type ) {
								return { supports: {} };
							}
							return { supports: { 'gatherpress-event-date': true } };
						} ),
					};
				}
				if ( 'core/editor' === store ) {
					return { getCurrentPostType: () => 'wp_template' };
				}
				return {};
			} );

			const result = resolveEventDateData(
				mockSelect,
				'gatherpress_event',
				undefined,
				42,
				false
			);

			expect( mockDatetimeStore.getDateTimeStart ).not.toHaveBeenCalled();
			expect( result.dateTimeStart ).toBe( '2025-06-10 14:00:00' );
		} );
	} );
} );
