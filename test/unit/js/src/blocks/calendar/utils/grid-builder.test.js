/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	generateCalendar,
	groupPostsByDate,
} from '@src/blocks/calendar/utils/grid-builder';

// The post types that support gatherpress-event-date.
const EVENT_TYPES = [ 'gatherpress_event' ];

/**
 * Build an event post entity record.
 *
 * @param {number} id    Post ID.
 * @param {string} start Start datetime (YYYY-MM-DD HH:MM:SS).
 * @param {string} end   End datetime (YYYY-MM-DD HH:MM:SS).
 *
 * @return {Object} Post entity record.
 */
const event = ( id, start, end ) => ( {
	id,
	type: 'gatherpress_event',
	meta: {
		gatherpress_datetime_start: start,
		gatherpress_datetime_end: end,
	},
} );

describe( 'groupPostsByDate', () => {
	const october = { startDate: '2026-10-01', endDate: '2026-10-31' };

	it( 'places only the visible days of an event that started more than a year before', () => {
		const grouped = groupPostsByDate(
			[ event( 1, '2025-01-01 10:00:00', '2026-12-31 16:00:00' ) ],
			{},
			october,
			EVENT_TYPES,
		);
		const dates = Object.keys( grouped );

		expect( dates ).toHaveLength( 31 );
		expect( dates[ 0 ] ).toBe( '2026-10-01' );
		expect( dates[ 30 ] ).toBe( '2026-10-31' );
		expect( grouped[ '2026-10-01' ][ 0 ] ).toMatchObject( {
			span: 'middle',
			dayIndex: 639,
			totalDays: 730,
		} );
		expect( grouped[ '2026-10-31' ][ 0 ].dayIndex ).toBe( 669 );
	} );

	it( 'leaves out an event outside the visible range', () => {
		const grouped = groupPostsByDate(
			[ event( 1, '2026-09-01 10:00:00', '2026-09-02 16:00:00' ) ],
			{},
			october,
			EVENT_TYPES,
		);

		expect( grouped ).toEqual( {} );
	} );

	it( 'uses the event dates only for the given event post types', () => {
		const post = {
			...event( 1, '2026-10-16 10:00:00', '2026-10-16 11:00:00' ),
			date: '2026-10-05T10:00:00',
		};

		expect( Object.keys( groupPostsByDate( [ post ], {}, october, EVENT_TYPES ) ) ).toEqual( [
			'2026-10-16',
		] );
		expect( Object.keys( groupPostsByDate( [ post ], {}, october ) ) ).toEqual( [
			'2026-10-05',
		] );
	} );

	it( 'gives timed events that overlap through other events their own columns', () => {
		const grouped = groupPostsByDate(
			[
				event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' ),
				event( 2, '2026-10-16 09:30:00', '2026-10-16 10:30:00' ),
				event( 3, '2026-10-16 10:00:00', '2026-10-16 11:00:00' ),
				event( 4, '2026-10-16 10:15:00', '2026-10-16 11:00:00' ),
				event( 5, '2026-10-16 13:00:00', '2026-10-16 14:00:00' ),
			],
			{ enabled: true, startHour: 8, endHour: 20, quarterMins: 15 },
			null,
			EVENT_TYPES,
		);
		const columns = grouped[ '2026-10-16' ].map( ( entry ) => [
			entry.id,
			entry.overlapIndex,
			entry.overlapTotal,
		] );

		expect( columns ).toEqual( [
			[ 1, 0, 3 ],
			[ 2, 1, 3 ],
			[ 3, 0, 3 ],
			[ 4, 2, 3 ],
			[ 5, 0, 1 ],
		] );
	} );
} );

describe( 'generateCalendar', () => {
	it( 'builds one table per month of a month view', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'month',
			unitCount: 2,
			startDate: '2026-10-01',
			startDateObj: new Date( 2026, 9, 1 ),
		} );

		expect( calendar.viewType ).toBe( 'month' );
		expect( calendar.unitCount ).toBe( 2 );
		expect( calendar.units ).toHaveLength( 2 );
		expect( calendar.weeks ).toHaveLength( 5 );
	} );

	it( 'numbers the rows of a view with several weeks', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'week',
			unitCount: 2,
			startDate: '2026-10-26',
			rawWeekStart: new Date( 2026, 9, 26 ),
		} );

		expect( calendar.unitCount ).toBe( 2 );
		expect( calendar.weeks ).toHaveLength( 2 );
		expect( calendar.units[ 0 ].weekNumbers ).toEqual( [ 44, 45 ] );
	} );

	it( 'leaves out week numbers for a single week', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'week',
			unitCount: 1,
			startDate: '2026-10-26',
			rawWeekStart: new Date( 2026, 9, 26 ),
		} );

		expect( calendar.unitCount ).toBe( 1 );
		expect( calendar.units[ 0 ].weekNumbers ).toBeUndefined();
	} );

	it( 'builds one row of days for a day view', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'day',
			unitCount: 3,
			startDate: '2026-10-15',
			startDateObj: new Date( 2026, 9, 15 ),
		} );

		expect( calendar.unitCount ).toBe( 3 );
		expect( calendar.weeks ).toHaveLength( 1 );
		expect( calendar.weeks[ 0 ] ).toHaveLength( 3 );
		expect( calendar.dayNames ).toHaveLength( 3 );
	} );
} );
