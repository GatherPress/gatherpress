/**
 * External dependencies.
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';
import { getSettings, setSettings } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import {
	buildConsecutiveDays,
	buildConsecutiveWeeks,
	buildWeeks,
	calculateTimedEventTracks,
	resolveEndDateStr,
	createDayEntry,
	determineSpanType,
	findAvailableSlot,
	generateCalendar,
	getDefaultActiveDate,
	getWeekNumber,
	groupPostsByDate,
	resolvePostDateSpan,
	toHour,
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

/**
 * Build an all-day event post entity record.
 *
 * @param {number} id       Post ID.
 * @param {string} start    Start datetime (YYYY-MM-DD HH:MM:SS).
 * @param {string} end      End datetime (YYYY-MM-DD HH:MM:SS).
 * @param {*}      isAllDay Value of the gatherpress_is_all_day meta, kept as given.
 *
 * @return {Object} Post entity record.
 */
const allDayEvent = ( id, start, end, isAllDay ) => {
	const post = event( id, start, end );
	post.meta.gatherpress_is_all_day = isAllDay;

	return post;
};

/**
 * Give the day numbers of each week, with null for an empty day.
 *
 * @param {Array[]} weeks Weeks of day entries.
 *
 * @return {Array[]} Day numbers.
 */
const dayNumbers = ( weeks ) =>
	weeks.map( ( week ) =>
		week.map( ( day ) => ( day.isEmpty ? null : day.day ) ),
	);

/**
 * Give the YYYY-MM-DD date of each day entry of each week.
 *
 * @param {Array[]} weeks Weeks of day entries.
 *
 * @return {Array[]} Dates.
 */
const weekDates = ( weeks ) =>
	weeks.map( ( week ) => week.map( ( day ) => day.date ) );

/**
 * Set "today" to 2026-10-15 in the site time zone.
 *
 * The site time zone of the test date settings is UTC, so noon UTC is
 * 2026-10-15 on every machine.
 *
 * @return {void}
 */
const setTodayToOctober15 = () => {
	jest.useFakeTimers();
	jest.setSystemTime( new Date( Date.UTC( 2026, 9, 15, 12, 0, 0 ) ) );
};

const originalDateSettings = getSettings();

/**
 * Give the site a UTC offset 13 hours from the machine's offset.
 *
 * dateI18n() shows a browser-local date in such a site time zone as another
 * day. The calendar dates must keep their day.
 *
 * @return {void}
 */
const setSiteFarFromMachine = () => {
	const machineOffset = -new Date( 2026, 9, 16, 12 ).getTimezoneOffset() / 60;

	setSettings( {
		...originalDateSettings,
		timezone: {
			...originalDateSettings.timezone,
			offset: 0 <= machineOffset ? machineOffset - 13 : machineOffset + 13,
			string: '',
		},
	} );
};

afterEach( () => {
	setSettings( originalDateSettings );
} );

describe( 'resolveEndDateStr', () => {
	it( 'ends an event that ends at 00:00 on a later day on the day before', () => {
		expect(
			resolveEndDateStr( '2026-10-16 22:00:00', '2026-10-17 00:00:00' ),
		).toBe( '2026-10-16' );
		expect(
			resolveEndDateStr( '2026-10-30 10:00:00', '2026-11-01 00:00:00' ),
		).toBe( '2026-10-31' );
	} );

	it( 'keeps the end day of an event that ends after 00:00', () => {
		expect(
			resolveEndDateStr( '2026-10-16 22:00:00', '2026-10-17 00:01:00' ),
		).toBe( '2026-10-17' );
	} );

	it( 'keeps an end at 00:00 on the start day', () => {
		expect(
			resolveEndDateStr( '2026-10-16 00:00:00', '2026-10-16 00:00:00' ),
		).toBe( '2026-10-16' );
	} );

	it( 'treats no end, or an end before the start, as the start day', () => {
		expect( resolveEndDateStr( '2026-10-16 10:00:00' ) ).toBe( '2026-10-16' );
		expect( resolveEndDateStr( '2026-10-16 10:00:00', '' ) ).toBe(
			'2026-10-16',
		);
		expect(
			resolveEndDateStr( '2026-10-16 10:00:00', '2026-10-14 00:00:00' ),
		).toBe( '2026-10-16' );
	} );
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

	it( 'puts the shorter of two timed events with the same start in the first column', () => {
		const grouped = groupPostsByDate(
			[
				event( 1, '2026-10-16 09:00:00', '2026-10-16 11:00:00' ),
				event( 2, '2026-10-16 09:00:00', '2026-10-16 10:00:00' ),
			],
			{ enabled: true },
			null,
			EVENT_TYPES,
		);

		expect(
			grouped[ '2026-10-16' ].map( ( entry ) => [
				entry.id,
				entry.overlapIndex,
				entry.overlapTotal,
			] ),
		).toEqual( [
			[ 1, 1, 2 ],
			[ 2, 0, 2 ],
		] );
	} );

	it( 'gives no dates when there are no posts', () => {
		expect( groupPostsByDate() ).toEqual( {} );
		expect( groupPostsByDate( null ) ).toEqual( {} );
		expect( groupPostsByDate( [] ) ).toEqual( {} );
	} );

	it( 'leaves out posts that have no date', () => {
		const grouped = groupPostsByDate(
			[
				{ id: 1, type: 'gatherpress_event', meta: {} },
				{ id: 2, type: 'post' },
				event( 3, '2026-10-16 10:00:00', '2026-10-16 11:00:00' ),
			],
			{},
			october,
			EVENT_TYPES,
		);

		expect( Object.keys( grouped ) ).toEqual( [ '2026-10-16' ] );
		expect( grouped[ '2026-10-16' ].map( ( entry ) => entry.id ) ).toEqual( [
			3,
		] );
	} );

	it( 'marks the first, middle and last day of a multi-day event', () => {
		const grouped = groupPostsByDate(
			[ event( 1, '2026-10-16 10:00:00', '2026-10-18 16:00:00' ) ],
			{},
			october,
			EVENT_TYPES,
		);

		expect(
			[ '2026-10-16', '2026-10-17', '2026-10-18' ].map( ( date ) => {
				const { span, dayIndex, totalDays, slot, isTimed } =
					grouped[ date ][ 0 ];

				return [ span, dayIndex, totalDays, slot, isTimed ];
			} ),
		).toEqual( [
			[ 'start', 1, 3, 0, false ],
			[ 'middle', 2, 3, 0, false ],
			[ 'end', 3, 3, 0, false ],
		] );
	} );

	it( 'places longer events in the top rows and stacks overlapping events below them', () => {
		const grouped = groupPostsByDate(
			[
				event( 1, '2026-10-17 10:00:00', '2026-10-17 11:00:00' ),
				event( 4, '2026-10-17 09:00:00', '2026-10-18 17:00:00' ),
				event( 2, '2026-10-16 09:00:00', '2026-10-17 17:00:00' ),
				event( 3, '2026-10-15 09:00:00', '2026-10-18 17:00:00' ),
			],
			{},
			null,
			EVENT_TYPES,
		);
		const rows = ( date ) =>
			grouped[ date ].map( ( { id, slot, span, dayIndex } ) => [
				id,
				slot,
				span,
				dayIndex,
			] );

		expect( rows( '2026-10-15' ) ).toEqual( [ [ 3, 0, 'start', 1 ] ] );
		expect( rows( '2026-10-16' ) ).toEqual( [
			[ 3, 0, 'middle', 2 ],
			[ 2, 1, 'start', 1 ],
		] );
		expect( rows( '2026-10-17' ) ).toEqual( [
			[ 3, 0, 'middle', 3 ],
			[ 2, 1, 'end', 2 ],
			[ 4, 2, 'start', 1 ],
			[ 1, 3, 'single', 1 ],
		] );
		expect( rows( '2026-10-18' ) ).toEqual( [
			[ 3, 0, 'end', 4 ],
			[ 4, 2, 'end', 2 ],
		] );
	} );

	it( 'shows an all-day event as a ribbon and a timed event before the visible hours on the first track', () => {
		const grouped = groupPostsByDate(
			[
				event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' ),
				allDayEvent( 2, '2026-10-16 00:00:00', '2026-10-16 23:59:00', '1' ),
				event( 3, '2026-10-16 06:00:00', '2026-10-16 07:00:00' ),
			],
			{ enabled: true },
			october,
			EVENT_TYPES,
		);
		const [ ribbon, timed, early, ...others ] = grouped[ '2026-10-16' ];

		expect( others ).toEqual( [] );
		// 6:00 to 7:00 is before the 8:00 start, so it gets the first track, as on the server.
		expect( early ).toMatchObject( {
			id: 3,
			isTimed: true,
			timeRowStart: 1,
			timeRowEnd: 2,
		} );
		expect( ribbon ).toMatchObject( {
			id: 2,
			span: 'single',
			slot: 0,
			isAllDay: true,
			isTimed: false,
		} );
		expect( ribbon ).not.toHaveProperty( 'overlapIndex' );
		expect( timed ).toMatchObject( {
			id: 1,
			isAllDay: false,
			isTimed: true,
			timeRowStart: 5,
			timeRowEnd: 9,
			overlapIndex: 0,
			overlapTotal: 1,
		} );
	} );
} );

describe( 'determineSpanType', () => {
	it( 'gives single for an event of one day', () => {
		expect( determineSpanType( 1, 1 ) ).toBe( 'single' );
		expect( determineSpanType( 1, 0 ) ).toBe( 'single' );
	} );

	it( 'gives start, middle and end for the days of a longer event', () => {
		expect( determineSpanType( 1, 3 ) ).toBe( 'start' );
		expect( determineSpanType( 2, 3 ) ).toBe( 'middle' );
		expect( determineSpanType( 3, 3 ) ).toBe( 'end' );
	} );
} );

describe( 'resolvePostDateSpan', () => {
	it( 'gives null for a post that has no date', () => {
		expect(
			resolvePostDateSpan(
				{ id: 1, type: 'gatherpress_event', meta: {} },
				null,
				EVENT_TYPES,
			),
		).toBeNull();
		expect( resolvePostDateSpan( { id: 2, type: 'post' } ) ).toBeNull();
	} );

	it( 'starts the span at noon of the start date', () => {
		const span = resolvePostDateSpan(
			event( 1, '2026-10-16 23:30:00', '2026-10-17 01:00:00' ),
			null,
			EVENT_TYPES,
		);

		expect( span ).toMatchObject( {
			startDateStr: '2026-10-16',
			totalDays: 2,
			firstDay: 0,
			shownDays: 2,
			isAllDay: false,
		} );
		expect( span.startObj ).toEqual( new Date( 2026, 9, 16, 12, 0, 0 ) );
	} );

	it( 'ignores an end date before the start date', () => {
		expect(
			resolvePostDateSpan(
				event( 1, '2026-10-16 10:00:00', '2026-10-14 10:00:00' ),
				null,
				EVENT_TYPES,
			),
		).toMatchObject( { startDateStr: '2026-10-16', totalDays: 1, shownDays: 1 } );
	} );

	it( 'keeps an event that ends at 00:00 on the next day on one day, as the server does', () => {
		expect(
			resolvePostDateSpan(
				event( 1, '2026-10-16 22:00:00', '2026-10-17 00:00:00' ),
				null,
				EVENT_TYPES,
			),
		).toMatchObject( { startDateStr: '2026-10-16', totalDays: 1, shownDays: 1 } );
	} );

	it( 'places a post on the day of its site-local date, wherever the site is', () => {
		setSiteFarFromMachine();

		expect(
			resolvePostDateSpan( {
				id: 1,
				type: 'post',
				date: '2026-10-05T23:30:00',
			} ),
		).toMatchObject( { startDateStr: '2026-10-05', totalDays: 1 } );
		expect(
			resolvePostDateSpan( {
				id: 2,
				type: 'post',
				date: '2026-10-05T00:30:00',
			} ).startDateStr,
		).toBe( '2026-10-05' );
	} );

	it( 'gives the part of the event inside the visible range', () => {
		const post = event( 1, '2026-10-30 10:00:00', '2026-11-03 16:00:00' );

		expect(
			resolvePostDateSpan(
				post,
				{ startDate: '2026-10-01', endDate: '2026-10-31' },
				EVENT_TYPES,
			),
		).toMatchObject( { totalDays: 5, firstDay: 0, shownDays: 2 } );
		expect(
			resolvePostDateSpan(
				post,
				{ startDate: '2026-11-01', endDate: '2026-11-30' },
				EVENT_TYPES,
			),
		).toMatchObject( { totalDays: 5, firstDay: 2, shownDays: 3 } );
	} );

	it( 'ignores a range without both dates', () => {
		expect(
			resolvePostDateSpan(
				event( 1, '2026-09-01 10:00:00', '2026-09-02 10:00:00' ),
				{ startDate: '2026-10-01' },
				EVENT_TYPES,
			),
		).toMatchObject( { totalDays: 2, firstDay: 0, shownDays: 2 } );
	} );

	it( 'places at most 366 days of a long event', () => {
		expect(
			resolvePostDateSpan(
				event( 1, '2025-01-01 10:00:00', '2026-12-31 16:00:00' ),
				null,
				EVENT_TYPES,
			),
		).toMatchObject( { totalDays: 730, firstDay: 0, shownDays: 366 } );
	} );

	it( 'reads the all-day meta in each form the REST API can give', () => {
		const isAllDay = ( value ) =>
			resolvePostDateSpan(
				allDayEvent( 1, '2026-10-16 00:00:00', '2026-10-16 23:59:00', value ),
				null,
				EVENT_TYPES,
			).isAllDay;

		expect( [ true, '1', 1, 'true' ].map( isAllDay ) ).toEqual( [
			true,
			true,
			true,
			true,
		] );
		expect( [ false, '0', 0, '', undefined ].map( isAllDay ) ).toEqual( [
			false,
			false,
			false,
			false,
			false,
		] );
	} );
} );

describe( 'toHour', () => {
	it( 'keeps 0 and numeric strings, and uses the fallback otherwise', () => {
		expect( toHour( 0, 8 ) ).toBe( 0 );
		expect( toHour( '6', 8 ) ).toBe( 6 );
		expect( toHour( '', 8 ) ).toBe( 8 );
		expect( toHour( null, 8 ) ).toBe( 8 );
		expect( toHour( undefined, 20 ) ).toBe( 20 );
		expect( toHour( 'noon', 20 ) ).toBe( 20 );
	} );
} );

describe( 'calculateTimedEventTracks', () => {
	it( 'counts the tracks from a start hour of 0, as the server does', () => {
		const grid = { enabled: true, startHour: 0, endHour: 24, quarterMins: 15 };

		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 37, timeRowEnd: 41 } );
		expect(
			calculateTimedEventTracks(
				event( 2, '2026-10-16 03:00:00', '2026-10-16 04:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 13, timeRowEnd: 17 } );
	} );

	it( 'gives null when the time grid is off', () => {
		const post = event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' );

		expect( calculateTimedEventTracks( post, {}, EVENT_TYPES ) ).toBeNull();
		expect(
			calculateTimedEventTracks( post, undefined, EVENT_TYPES ),
		).toBeNull();
	} );

	it( 'gives null for an event that has no start', () => {
		expect(
			calculateTimedEventTracks(
				{ id: 1, type: 'gatherpress_event', meta: {} },
				{ enabled: true },
				EVENT_TYPES,
			),
		).toBeNull();
	} );

	it( 'uses 8:00 to 20:00 in quarters of 15 minutes by default', () => {
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' ),
				{ enabled: true },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 5, timeRowEnd: 9 } );
	} );

	it( 'gives an event without an end one hour', () => {
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 09:00:00' ),
				{ enabled: true },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 5, timeRowEnd: 9 } );
		expect(
			calculateTimedEventTracks(
				{ id: 2, type: 'post', date: '2026-10-16T09:00:00' },
				{ enabled: true },
			),
		).toEqual( { timeRowStart: 5, timeRowEnd: 9 } );
	} );

	it( 'puts an event outside the visible hours on the nearest edge, as the server does', () => {
		// 48 tracks, from line 1 (8:00) to line 49 (20:00).
		const grid = { enabled: true, startHour: 8, endHour: 20 };

		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 06:00:00', '2026-10-16 08:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 1, timeRowEnd: 2 } );
		expect(
			calculateTimedEventTracks(
				event( 2, '2026-10-16 20:00:00', '2026-10-16 21:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 48, timeRowEnd: 49 } );
		expect(
			calculateTimedEventTracks(
				event( 3, '2026-10-16 21:00:00', '2026-10-16 22:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 48, timeRowEnd: 49 } );
	} );

	it( 'counts the steps from the first hour of the grid', () => {
		// 3-hour rows give 45-minute steps: from 8:00 they are at 8:45 and 9:30.
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 08:00:00', '2026-10-16 08:50:00' ),
				{ enabled: true, startHour: 8, endHour: 20, quarterMins: 45 },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 1, timeRowEnd: 3 } );
	} );

	it( 'cuts an event to the visible hours', () => {
		const grid = { enabled: true, startHour: 8, endHour: 20 };

		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 07:00:00', '2026-10-16 09:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 1, timeRowEnd: 5 } );
		expect(
			calculateTimedEventTracks(
				event( 2, '2026-10-16 19:00:00', '2026-10-16 22:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 45, timeRowEnd: 49 } );
	} );

	it( 'gives an event that ends at or before its start one hour, as the server does', () => {
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 09:00:00', '2026-10-16 09:00:00' ),
				{ enabled: true },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 5, timeRowEnd: 9 } );
		expect(
			calculateTimedEventTracks(
				event( 2, '2026-10-16 09:00:00', '2026-10-16 08:00:00' ),
				{ enabled: true },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 5, timeRowEnd: 9 } );
	} );

	it( 'runs a one-day event that ends on the next day to the end of its start day', () => {
		const grid = { enabled: true, startHour: 8, endHour: 24 };

		// Ends at 00:00 on the next day: 22:00 is track 57, 24:00 is track 65.
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 22:00:00', '2026-10-17 00:00:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 57, timeRowEnd: 65 } );

		// No end: one hour from 23:30 is 00:30 on the next day.
		expect(
			calculateTimedEventTracks(
				event( 2, '2026-10-16 23:30:00' ),
				grid,
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 63, timeRowEnd: 65 } );
	} );

	it( 'rounds the start down and the end up to whole tracks', () => {
		expect(
			calculateTimedEventTracks(
				event( 1, '2026-10-16 09:10:00', '2026-10-16 09:20:00' ),
				{ enabled: true, startHour: '8', endHour: '20', quarterMins: '30' },
				EVENT_TYPES,
			),
		).toEqual( { timeRowStart: 3, timeRowEnd: 4 } );
	} );
} );

describe( 'findAvailableSlot', () => {
	it( 'gives slot 0 when no date has a used slot', () => {
		expect( findAvailableSlot( [ '2026-10-16' ], {} ) ).toBe( 0 );
	} );

	it( 'gives the lowest slot that is free on every date', () => {
		const occupied = {
			'2026-10-16': new Set( [ 2 ] ),
			'2026-10-17': new Set( [ 0, 1 ] ),
		};

		expect(
			findAvailableSlot( [ '2026-10-16', '2026-10-17' ], occupied ),
		).toBe( 3 );
		expect( findAvailableSlot( [ '2026-10-16' ], occupied ) ).toBe( 0 );
	} );
} );

describe( 'createDayEntry', () => {
	it( 'describes the day and gives its posts', () => {
		const posts = [ { id: 1 } ];

		expect(
			createDayEntry(
				new Date( 2026, 9, 17 ),
				{ '2026-10-17': posts },
				'2026-10-16',
			),
		).toEqual( {
			day: 17,
			date: '2026-10-17',
			posts,
			isEmpty: false,
			isToday: false,
			isPast: false,
			isFuture: true,
			dayOfWeek: 6,
			weekday: 'saturday',
			isWeekend: true,
		} );
	} );

	it( 'marks today and past days, and gives no posts without a map', () => {
		expect(
			createDayEntry( new Date( 2026, 9, 14 ), undefined, '2026-10-14' ),
		).toMatchObject( {
			posts: [],
			isToday: true,
			isPast: false,
			isFuture: false,
			weekday: 'wednesday',
			isWeekend: false,
		} );
		expect(
			createDayEntry( new Date( 2026, 9, 14 ), {}, '2026-10-20' ),
		).toMatchObject( { isToday: false, isPast: true, isFuture: false } );
	} );
} );

describe( 'buildWeeks', () => {
	it( 'pads the first and last week of a month', () => {
		const weeks = buildWeeks( 2026, 10, 0, 31, {
			'2026-10-16': [ { id: 1 } ],
		} );

		expect( dayNumbers( weeks ) ).toEqual( [
			[ null, null, null, null, 1, 2, 3 ],
			[ 4, 5, 6, 7, 8, 9, 10 ],
			[ 11, 12, 13, 14, 15, 16, 17 ],
			[ 18, 19, 20, 21, 22, 23, 24 ],
			[ 25, 26, 27, 28, 29, 30, 31 ],
		] );
		expect( weeks[ 0 ][ 0 ] ).toEqual( { isEmpty: true, posts: [] } );
		expect( weeks[ 2 ][ 5 ] ).toMatchObject( {
			date: '2026-10-16',
			posts: [ { id: 1 } ],
		} );

		expect( dayNumbers( buildWeeks( 2026, 10, 1, 31 ) ).pop() ).toEqual( [
			26,
			27,
			28,
			29,
			30,
			31,
			null,
		] );
	} );

	it( 'leaves out the weekend days when weekends are hidden', () => {
		const expected = [
			[ null, null, null, 1, 2 ],
			[ 5, 6, 7, 8, 9 ],
			[ 12, 13, 14, 15, 16 ],
			[ 19, 20, 21, 22, 23 ],
			[ 26, 27, 28, 29, 30 ],
		];

		expect( dayNumbers( buildWeeks( 2026, 10, 1, 31, {}, false ) ) ).toEqual(
			expected,
		);
		expect( dayNumbers( buildWeeks( 2026, 10, 0, 31, {}, false ) ) ).toEqual(
			expected,
		);
	} );
} );

describe( 'buildConsecutiveWeeks', () => {
	it( 'builds full weeks from the start date', () => {
		const weeks = buildConsecutiveWeeks( new Date( 2026, 9, 25 ), 1 );

		expect( weekDates( weeks ) ).toEqual( [
			[
				'2026-10-25',
				'2026-10-26',
				'2026-10-27',
				'2026-10-28',
				'2026-10-29',
				'2026-10-30',
				'2026-10-31',
			],
		] );
	} );

	it( 'leaves out the weekend days when weekends are hidden', () => {
		const weeks = buildConsecutiveWeeks(
			new Date( 2026, 9, 26 ),
			2,
			{ '2026-11-04': [ { id: 1 } ] },
			false,
		);

		expect( weekDates( weeks ) ).toEqual( [
			[ '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30' ],
			[ '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06' ],
		] );
		expect( weeks[ 1 ][ 2 ].posts ).toEqual( [ { id: 1 } ] );
	} );
} );

describe( 'buildConsecutiveDays', () => {
	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'builds one row of days across a month end and marks today', () => {
		setTodayToOctober15();

		const [ days ] = buildConsecutiveDays( new Date( 2026, 9, 14 ), 3 );

		expect(
			days.map( ( { date, isPast, isToday, isFuture } ) => [
				date,
				isPast,
				isToday,
				isFuture,
			] ),
		).toEqual( [
			[ '2026-10-14', true, false, false ],
			[ '2026-10-15', false, true, false ],
			[ '2026-10-16', false, false, true ],
		] );

		const [ monthEnd ] = buildConsecutiveDays( new Date( 2026, 9, 31 ), 2, {
			'2026-11-01': [ { id: 1 } ],
		} );

		expect( monthEnd.map( ( day ) => day.date ) ).toEqual( [
			'2026-10-31',
			'2026-11-01',
		] );
		expect( monthEnd[ 1 ].posts ).toEqual( [ { id: 1 } ] );
	} );
} );

describe( 'getWeekNumber', () => {
	it( 'gives 0 for a row without a dated day', () => {
		expect( getWeekNumber( [ { isEmpty: true, posts: [] } ], 1 ) ).toBe( 0 );
		expect( getWeekNumber( [] ) ).toBe( 0 );
	} );

	it( 'gives the ISO week that holds most of a row that starts on Sunday', () => {
		// Sunday 2026-10-25 is in ISO week 43, the rest of the row in week 44.
		const [ week ] = buildConsecutiveWeeks( new Date( 2026, 9, 25 ), 1 );
		const [ workWeek ] = buildConsecutiveWeeks(
			new Date( 2026, 9, 25 ),
			1,
			{},
			false,
		);

		expect( getWeekNumber( week, 0 ) ).toBe( 44 );
		expect( getWeekNumber( workWeek, 0 ) ).toBe( 44 );
	} );

	it( 'reads the week from the first dated day after empty days', () => {
		const weeks = buildWeeks( 2026, 10, 1, 31 );

		expect( getWeekNumber( weeks[ 0 ], 1 ) ).toBe( 40 );
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
		expect( calendar.dayNames ).toEqual( [ 'Thu', 'Fri', 'Sat' ] );
	} );

	it( 'names the days of a day view by their calendar date, wherever the site is', () => {
		setSiteFarFromMachine();

		const calendar = generateCalendar( [], 1, {
			viewType: 'day',
			unitCount: 2,
			startDate: '2026-10-16',
			startDateObj: new Date( 2026, 9, 16, 12 ),
		} );

		expect( calendar.dayNames ).toEqual( [ 'Fri', 'Sat' ] );
	} );

	it( 'reads the start date string at local noon when the range has no date objects', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'day',
			unitCount: 1,
			startDate: '2026-10-16',
		} );

		expect( calendar.weeks[ 0 ][ 0 ].date ).toBe( '2026-10-16' );
		expect( calendar.dayNames ).toEqual( [ 'Fri' ] );
	} );

	it( 'shows one month when the range gives no view type or count', () => {
		const calendar = generateCalendar( [], 0, {
			startDateObj: new Date( 2026, 9, 1 ),
		} );

		expect( calendar ).toMatchObject( {
			viewType: 'month',
			unitCount: 1,
			numRibbonSlots: 0,
		} );
		expect( calendar.units ).toHaveLength( 1 );
		expect( calendar.dayNames ).toEqual( [
			'Sun',
			'Mon',
			'Tue',
			'Wed',
			'Thu',
			'Fri',
			'Sat',
		] );
	} );

	it( 'numbers the weeks of each month across a year end', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'month',
			unitCount: 3,
			startDateObj: new Date( 2026, 10, 1 ),
		} );
		const firstDates = calendar.units.map(
			( unit ) => unit.weeks.flat().find( ( day ) => ! day.isEmpty ).date,
		);

		expect( firstDates ).toEqual( [ '2026-11-01', '2026-12-01', '2027-01-01' ] );
		expect( calendar.units.map( ( unit ) => unit.monthOffset ) ).toEqual( [
			0, 1, 2,
		] );
		expect( calendar.units[ 0 ].weekNumbers ).toEqual( [
			44, 45, 46, 47, 48, 49,
		] );
		// 2026 has 53 ISO weeks, so 2027-01-01 is in week 53.
		expect( calendar.units[ 2 ].weekNumbers ).toEqual( [ 53, 1, 2, 3, 4 ] );
	} );

	it( 'starts a week view at the range start without a raw week start', () => {
		const calendar = generateCalendar( [], 1, {
			viewType: 'week',
			startDateObj: new Date( 2026, 9, 26 ),
		} );

		expect( calendar.weeks[ 0 ][ 0 ].date ).toBe( '2026-10-26' );
		expect( calendar.dayNames[ 0 ] ).toBe( 'Mon' );
		expect( calendar.numRibbonSlots ).toBe( 0 );
	} );

	it( 'counts the ribbon rows above the time grid of a day view', () => {
		const calendar = generateCalendar(
			[
				event( 1, '2026-10-16 09:00:00', '2026-10-17 17:00:00' ),
				event( 2, '2026-10-16 10:00:00', '2026-10-17 12:00:00' ),
				event( 3, '2026-10-16 13:00:00', '2026-10-16 14:00:00' ),
			],
			1,
			{
				viewType: 'day',
				unitCount: 2,
				startDate: '2026-10-16',
				endDate: '2026-10-17',
				startDateObj: new Date( 2026, 9, 16 ),
			},
			true,
			{ enabled: true },
			EVENT_TYPES,
		);

		expect( calendar.numRibbonSlots ).toBe( 2 );
		expect( calendar.units[ 0 ].numRibbonSlots ).toBe( 2 );
		expect(
			calendar.weeks[ 0 ][ 0 ].posts.map( ( { id, isTimed } ) => [
				id,
				isTimed,
			] ),
		).toEqual( [
			[ 1, false ],
			[ 3, true ],
			[ 2, false ],
		] );
	} );

	it( 'counts only the ribbon events of a week view time grid', () => {
		const range = {
			viewType: 'week',
			unitCount: 1,
			startDate: '2026-10-12',
			endDate: '2026-10-18',
			rawWeekStart: new Date( 2026, 9, 12 ),
		};
		const timed = event( 1, '2026-10-16 09:00:00', '2026-10-16 10:00:00' );
		const allDay = allDayEvent(
			2,
			'2026-10-14 00:00:00',
			'2026-10-14 23:59:00',
			true,
		);

		expect(
			generateCalendar( [ timed ], 1, range, true, { enabled: true }, EVENT_TYPES )
				.numRibbonSlots,
		).toBe( 0 );
		expect(
			generateCalendar(
				[ timed, allDay ],
				1,
				range,
				true,
				{ enabled: true },
				EVENT_TYPES,
			).numRibbonSlots,
		).toBe( 1 );
		expect(
			generateCalendar( [ timed, allDay ], 1, range, true, {}, EVENT_TYPES )
				.numRibbonSlots,
		).toBe( 0 );
	} );
} );

describe( 'getDefaultActiveDate', () => {
	const october = {
		viewType: 'month',
		startDate: '2026-10-01',
		startDateObj: new Date( 2026, 9, 1 ),
	};

	beforeEach( () => {
		setTodayToOctober15();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'gives today when the calendar shows today', () => {
		expect( getDefaultActiveDate( generateCalendar( [], 1, october ) ) ).toBe(
			'2026-10-15',
		);
	} );

	it( 'gives the first day of the calendar when it does not show today', () => {
		const calendar = generateCalendar( [], 1, {
			...october,
			startDate: '2026-12-01',
			startDateObj: new Date( 2026, 11, 1 ),
		} );

		expect( getDefaultActiveDate( calendar ) ).toBe( '2026-12-01' );
	} );

	it( 'reads the weeks of a calendar without units', () => {
		expect(
			getDefaultActiveDate( {
				weeks: [
					[
						{ isEmpty: true, posts: [] },
						{ isEmpty: false, isToday: false, date: '2026-10-01' },
					],
				],
			} ),
		).toBe( '2026-10-01' );
	} );

	it( 'gives an empty string when the calendar has no dated day', () => {
		expect(
			getDefaultActiveDate( { weeks: [ [ { isEmpty: true, posts: [] } ] ] } ),
		).toBe( '' );
	} );
} );
