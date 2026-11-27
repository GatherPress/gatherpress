/**
 * External dependencies.
 */
import {
	afterAll,
	afterEach,
	beforeAll,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';
import { select } from '@wordpress/data';
import { getSettings, setSettings } from '@wordpress/date';

/**
 * Internal dependencies.
 */
import {
	calculateDateQuery,
	calculateDateRange,
	calculatePostSpanUnits,
	calculateTargetDate,
	formatCalendarDate,
	formatDate,
	formatHeading,
	getSiteToday,
} from '@src/blocks/calendar/utils/date-utils';

// The weekend days and the date separator come from the editor settings.
jest.mock( '@wordpress/data', () => ( { select: jest.fn() } ) );
jest.mock( '@wordpress/core-data', () => ( { store: 'core' } ) );

/**
 * Give the editor the GatherPress settings that the calendar reads.
 *
 * @param {Object} gatherpress GatherPress editor settings.
 */
const setEditorSettings = ( gatherpress ) => {
	select.mockImplementation( ( storeName ) =>
		'core/editor' === storeName
			? { getEditorSettings: () => ( { gatherpress } ) }
			: undefined,
	);
};

/**
 * Set "now" to Thursday, October 22, 2026, at noon UTC.
 *
 * "Today" comes from the site time zone, which is UTC in the test settings.
 */
const setToday = () => {
	jest.useFakeTimers().setSystemTime( new Date( Date.UTC( 2026, 9, 22, 12 ) ) );
};

/**
 * Give the site a UTC offset.
 *
 * @param {number} offset UTC offset in hours.
 */
const setSiteOffset = ( offset ) => {
	const settings = getSettings();

	setSettings( {
		...settings,
		timezone: { ...settings.timezone, offset, string: '' },
	} );
};

/**
 * Give the site a UTC offset 13 hours from the machine's offset.
 *
 * dateI18n() shows a browser-local date in such a site time zone as another
 * day. The calendar dates must keep their day.
 */
const setSiteFarFromMachine = () => {
	const machineOffset = -new Date( 2026, 9, 22, 12 ).getTimezoneOffset() / 60;

	setSiteOffset( 0 <= machineOffset ? machineOffset - 13 : machineOffset + 13 );
};

const originalDateSettings = getSettings();

afterAll( () => {
	setSettings( originalDateSettings );
} );

beforeEach( () => {
	// No editor settings: Saturday and Sunday are the weekend days, and the
	// separator is the default en dash.
	select.mockReset();
} );

afterEach( () => {
	jest.useRealTimers();
} );

describe( 'formatDate', () => {
	it( 'pads the month and the day to two digits', () => {
		expect( formatDate( new Date( 2026, 0, 5 ) ) ).toBe( '2026-01-05' );
		expect( formatDate( new Date( 2026, 11, 31 ) ) ).toBe( '2026-12-31' );
	} );

	it( 'uses the local calendar day, also at the first and last minute of the day', () => {
		expect( formatDate( new Date( 2026, 9, 1, 0, 0, 0 ) ) ).toBe(
			'2026-10-01',
		);
		expect( formatDate( new Date( 2026, 11, 31, 23, 59, 59 ) ) ).toBe(
			'2026-12-31',
		);
	} );
} );

describe( 'calculateTargetDate', () => {
	it( 'reads a full selected date at noon for a day view', () => {
		const target = calculateTargetDate( {
			viewType: 'day',
			selectedDate: '2026-10-22',
		} );

		expect( formatDate( target ) ).toBe( '2026-10-22' );
		expect( target.getHours() ).toBe( 12 );
		expect( target.getMinutes() ).toBe( 0 );
	} );

	it( 'moves a full selected date to the first of its month in a month view', () => {
		const target = calculateTargetDate( { selectedDate: '2026-10-22' } );

		expect( formatDate( target ) ).toBe( '2026-10-01' );
		expect( target.getHours() ).toBe( 12 );
	} );

	it( 'reads a selected year and month as the first day of that month', () => {
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'week',
					selectedDate: '2026-10',
				} ),
			),
		).toBe( '2026-10-01' );
	} );

	it( 'uses today when there is no selected date or it is not in a known format', () => {
		setToday();

		const today = calculateTargetDate( { viewType: 'day' } );

		expect( formatDate( today ) ).toBe( '2026-10-22' );
		expect( today.getHours() ).toBe( 12 );
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'day',
					selectedDate: '2026-1-5',
				} ),
			),
		).toBe( '2026-10-22' );
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'day',
					selectedDate: '10/22/2026',
				} ),
			),
		).toBe( '2026-10-22' );
	} );

	it( 'uses the first of this month when called without options', () => {
		setToday();

		expect( formatDate( calculateTargetDate() ) ).toBe( '2026-10-01' );
	} );

	it( 'moves a week view by whole weeks', () => {
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'week',
					selectedDate: '2026-10-22',
					dateModifier: -2,
				} ),
			),
		).toBe( '2026-10-08' );
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'week',
					selectedDate: '2026-10-22',
					dateModifier: 1,
				} ),
			),
		).toBe( '2026-10-29' );
	} );

	it( 'moves a day view by days, across the end of a month', () => {
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'day',
					selectedDate: '2026-10-31',
					dateModifier: 1,
				} ),
			),
		).toBe( '2026-11-01' );
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'day',
					selectedDate: '2026-10-31',
					dateModifier: -31,
				} ),
			),
		).toBe( '2026-09-30' );
	} );

	it( 'moves a month view by months, across the end of a year', () => {
		expect(
			formatDate(
				calculateTargetDate( {
					selectedDate: '2026-11-15',
					dateModifier: 2,
				} ),
			),
		).toBe( '2027-01-01' );
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'month',
					selectedDate: '2026-02-15',
					dateModifier: -3,
				} ),
			),
		).toBe( '2025-11-01' );
	} );

	it( 'moves another view type by months from the first of the month, so a long month does not skip the next one', () => {
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'list',
					selectedDate: '2026-01-31',
				} ),
			),
		).toBe( '2026-01-31' );
		// From January 31, one month later without the move to the first
		// would be March 3.
		expect(
			formatDate(
				calculateTargetDate( {
					viewType: 'list',
					selectedDate: '2026-01-31',
					dateModifier: 1,
				} ),
			),
		).toBe( '2026-02-01' );
	} );
} );

describe( 'calculatePostSpanUnits', () => {
	it( 'counts one unit when a date is missing', () => {
		expect( calculatePostSpanUnits( 'month', '', '2026-10-22' ) ).toBe( 1 );
		expect( calculatePostSpanUnits( 'day', '2026-10-22', undefined ) ).toBe(
			1,
		);
		expect( calculatePostSpanUnits( 'week', null, null ) ).toBe( 1 );
	} );

	it( 'counts the days of a day view, from 1 to 7', () => {
		expect(
			calculatePostSpanUnits( 'day', '2026-10-20', '2026-10-22' ),
		).toBe( 3 );
		expect(
			calculatePostSpanUnits( 'day', '2026-10-22', '2026-10-22' ),
		).toBe( 1 );
		expect(
			calculatePostSpanUnits( 'day', '2026-10-22', '2026-10-20' ),
		).toBe( 1 );
		expect(
			calculatePostSpanUnits( 'day', '2026-10-01', '2026-10-20' ),
		).toBe( 7 );
	} );

	it( 'counts a day with a daylight saving time change as one day', () => {
		expect(
			calculatePostSpanUnits( 'day', '2026-03-07', '2026-03-09' ),
		).toBe( 3 );
		expect(
			calculatePostSpanUnits( 'day', '2026-10-31', '2026-11-02' ),
		).toBe( 3 );
	} );

	it( 'counts the weeks that the dates touch, with Sunday as the default start of the week', () => {
		// Thursday to Saturday.
		expect(
			calculatePostSpanUnits( 'week', '2026-10-22', '2026-10-24' ),
		).toBe( 1 );
		// Saturday to Sunday.
		expect(
			calculatePostSpanUnits( 'week', '2026-10-24', '2026-10-25' ),
		).toBe( 2 );
		expect(
			calculatePostSpanUnits( 'week', '2026-10-24', '2026-10-25', 0 ),
		).toBe( 2 );
		expect(
			calculatePostSpanUnits( 'week', '2026-10-22', '2026-11-05' ),
		).toBe( 3 );
	} );

	it( 'counts the weeks from the given start of the week', () => {
		// Saturday and Sunday are in one week that starts on Monday.
		expect(
			calculatePostSpanUnits( 'week', '2026-10-24', '2026-10-25', 1 ),
		).toBe( 1 );
		// Sunday to Monday.
		expect(
			calculatePostSpanUnits( 'week', '2026-10-25', '2026-10-26', 1 ),
		).toBe( 2 );
	} );

	it( 'counts from 1 to 5 weeks', () => {
		expect(
			calculatePostSpanUnits( 'week', '2026-10-01', '2026-12-31' ),
		).toBe( 5 );
		expect(
			calculatePostSpanUnits( 'week', '2026-10-22', '2026-10-01' ),
		).toBe( 1 );
	} );

	it( 'counts the months that the dates touch, across the end of a year', () => {
		expect(
			calculatePostSpanUnits( 'month', '2026-10-01', '2026-10-31' ),
		).toBe( 1 );
		expect(
			calculatePostSpanUnits( 'month', '2026-10-22', '2026-12-01' ),
		).toBe( 3 );
		expect(
			calculatePostSpanUnits( 'month', '2026-11-30', '2027-02-01' ),
		).toBe( 4 );
	} );

	it( 'counts from 1 to 12 months', () => {
		expect(
			calculatePostSpanUnits( 'month', '2026-01-01', '2027-06-01' ),
		).toBe( 12 );
		expect(
			calculatePostSpanUnits( 'month', '2026-10-22', '2026-08-01' ),
		).toBe( 1 );
	} );

	it( 'counts months for another view type', () => {
		expect(
			calculatePostSpanUnits( 'list', '2026-10-22', '2026-12-01' ),
		).toBe( 3 );
	} );
} );

describe( 'calculateDateRange', () => {
	it( 'covers whole months in a month view', () => {
		const range = calculateDateRange( {
			selectedDate: '2026-11-15',
			unitCount: 2,
		} );

		expect( range ).toMatchObject( {
			startDate: '2026-11-01',
			endDate: '2026-12-31',
			year: 2026,
			month: 11,
			viewType: 'month',
			unitCount: 2,
		} );
		expect( formatDate( range.startDateObj ) ).toBe( '2026-11-01' );
		expect( formatDate( range.endDateObj ) ).toBe( '2026-12-31' );
		expect( range.startDateObj.getHours() ).toBe( 12 );
		expect( range.endDateObj.getHours() ).toBe( 12 );
		expect( range.rawWeekStart ).toBe( range.startDateObj );
		expect( formatDate( range.targetDate ) ).toBe( '2026-11-01' );
	} );

	it( 'ends a month view on the last day of the last month, across the end of a year', () => {
		expect(
			calculateDateRange( {
				viewType: 'month',
				selectedDate: '2026-12-05',
				unitCount: 3,
			} ),
		).toMatchObject( {
			startDate: '2026-12-01',
			endDate: '2027-02-28',
			year: 2026,
			month: 12,
		} );
		expect(
			calculateDateRange( { selectedDate: '2028-02-10' } ).endDate,
		).toBe( '2028-02-29' );
	} );

	it( 'uses one unit for a missing, zero or negative unit count', () => {
		[ undefined, 0, -2 ].forEach( ( unitCount ) => {
			expect(
				calculateDateRange( { selectedDate: '2026-11-15', unitCount } ),
			).toMatchObject( {
				startDate: '2026-11-01',
				endDate: '2026-11-30',
				unitCount: 1,
			} );
		} );
	} );

	it( 'shows this month when called without options', () => {
		setToday();

		expect( calculateDateRange() ).toMatchObject( {
			startDate: '2026-10-01',
			endDate: '2026-10-31',
			year: 2026,
			month: 10,
			viewType: 'month',
			unitCount: 1,
		} );
	} );

	it( 'covers the given number of days from the selected day in a day view', () => {
		const range = calculateDateRange( {
			viewType: 'day',
			selectedDate: '2026-10-30',
			unitCount: 3,
		} );

		expect( range ).toMatchObject( {
			startDate: '2026-10-30',
			endDate: '2026-11-01',
			year: 2026,
			month: 10,
			viewType: 'day',
			unitCount: 3,
		} );
		expect( range.rawWeekStart ).toBe( range.startDateObj );
		// The range dates are copies, not the target date itself.
		expect( range.startDateObj ).not.toBe( range.targetDate );
		expect( formatDate( range.targetDate ) ).toBe( '2026-10-30' );
	} );

	it( 'covers the whole week of the selected day in a week view, from Sunday by default', () => {
		const range = calculateDateRange( {
			viewType: 'week',
			selectedDate: '2026-10-22',
		} );

		expect( range ).toMatchObject( {
			startDate: '2026-10-18',
			endDate: '2026-10-24',
			year: 2026,
			month: 10,
			viewType: 'week',
			unitCount: 1,
		} );
		expect( formatDate( range.rawWeekStart ) ).toBe( '2026-10-18' );
		expect( formatDate( range.targetDate ) ).toBe( '2026-10-22' );
	} );

	it( 'starts the weeks on the given day and covers the given number of weeks', () => {
		const range = calculateDateRange(
			{ viewType: 'week', selectedDate: '2026-10-22', unitCount: 2 },
			1,
		);

		expect( range.startDate ).toBe( '2026-10-19' );
		expect( range.endDate ).toBe( '2026-11-01' );
		expect( formatDate( range.rawWeekStart ) ).toBe( '2026-10-19' );
	} );

	it( 'shows the weekends unless they are turned off', () => {
		[ undefined, true, 'true' ].forEach( ( showWeekends ) => {
			expect(
				calculateDateRange( {
					viewType: 'week',
					selectedDate: '2026-10-22',
					showWeekends,
				} ),
			).toMatchObject( {
				startDate: '2026-10-18',
				endDate: '2026-10-24',
			} );
		} );
	} );

	it( 'starts and ends a week view on the first and last weekday when weekends are hidden', () => {
		[ false, 'false' ].forEach( ( showWeekends ) => {
			const range = calculateDateRange( {
				viewType: 'week',
				selectedDate: '2026-10-22',
				showWeekends,
			} );

			expect( range.startDate ).toBe( '2026-10-19' );
			expect( range.endDate ).toBe( '2026-10-23' );
			// The grid still starts on the first day of the week.
			expect( formatDate( range.rawWeekStart ) ).toBe( '2026-10-18' );
		} );
	} );

	it( 'hides the weekend days from the editor settings', () => {
		// Friday and Saturday.
		setEditorSettings( { weekendDays: [ 5, 6 ] } );

		expect(
			calculateDateRange( {
				viewType: 'week',
				selectedDate: '2026-10-22',
				showWeekends: false,
			} ),
		).toMatchObject( {
			startDate: '2026-10-18',
			endDate: '2026-10-22',
		} );
	} );

	it( 'shows the full weeks when weekends are hidden but every day is a weekend day', () => {
		setEditorSettings( { weekendDays: [ 0, 1, 2, 3, 4, 5, 6 ] } );

		const range = calculateDateRange( {
			viewType: 'week',
			selectedDate: '2026-10-22',
			showWeekends: false,
			unitCount: 2,
		} );

		expect( range.startDate ).toBe( '2026-10-18' );
		expect( range.endDate ).toBe( '2026-10-31' );
		expect( range.startDateObj ).toBe( range.rawWeekStart );
	} );
} );

describe( 'calculateDateQuery', () => {
	it( 'returns only the fields that the REST query uses', () => {
		expect(
			calculateDateQuery(
				{ viewType: 'week', selectedDate: '2026-10-22', unitCount: 2 },
				1,
			),
		).toEqual( {
			year: 2026,
			month: 10,
			startDate: '2026-10-19',
			endDate: '2026-11-01',
			viewType: 'week',
			unitCount: 2,
		} );
	} );

	it( 'queries this month when called without options', () => {
		setToday();

		expect( calculateDateQuery() ).toEqual( {
			year: 2026,
			month: 10,
			startDate: '2026-10-01',
			endDate: '2026-10-31',
			viewType: 'month',
			unitCount: 1,
		} );
	} );

	it( 'starts the weeks on Sunday by default', () => {
		setToday();

		expect( calculateDateQuery( { viewType: 'week' } ) ).toMatchObject( {
			startDate: '2026-10-18',
			endDate: '2026-10-24',
		} );
	} );
} );

describe( 'formatCalendarDate', () => {
	afterEach( () => {
		setSettings( originalDateSettings );
	} );

	it( 'keeps the day when the site is 13 hours from the browser', () => {
		setSiteFarFromMachine();

		expect(
			formatCalendarDate( 'D, F j, Y', new Date( 2026, 9, 16, 12 ) ),
		).toBe( 'Fri, October 16, 2026' );
		expect(
			formatCalendarDate( 'D, F j, Y', new Date( 2026, 9, 16, 0, 0 ) ),
		).toBe( 'Fri, October 16, 2026' );
	} );
} );

describe( 'getSiteToday', () => {
	afterEach( () => {
		setSettings( originalDateSettings );
	} );

	it( 'gives today in the site time zone, at local noon', () => {
		// 05:00 UTC is 19:00 the day before at UTC-10.
		jest.useFakeTimers().setSystemTime( new Date( Date.UTC( 2026, 9, 22, 5 ) ) );
		setSiteOffset( -10 );

		const today = getSiteToday();

		expect( formatDate( today ) ).toBe( '2026-10-21' );
		expect( today.getHours() ).toBe( 12 );

		// 20:00 UTC is 06:00 the next day at UTC+10.
		jest.setSystemTime( new Date( Date.UTC( 2026, 9, 22, 20 ) ) );
		setSiteOffset( 10 );

		expect( formatDate( getSiteToday() ) ).toBe( '2026-10-23' );
	} );
} );

describe( 'formatHeading', () => {
	beforeAll( () => {
		// The headings must show the calendar dates, wherever the site is.
		setSiteFarFromMachine();
	} );

	afterAll( () => {
		setSettings( originalDateSettings );
	} );

	it( 'shows the full date for one day in a day view', () => {
		expect(
			formatHeading(
				'day',
				new Date( 2026, 9, 22 ),
				new Date( 2026, 9, 22 ),
			),
		).toBe( 'Thursday, October 22, 2026' );
	} );

	it( 'shows two times on the same day as that one day', () => {
		expect(
			formatHeading(
				'day',
				new Date( 2026, 9, 22, 0, 5 ),
				new Date( 2026, 9, 22, 23, 55 ),
			),
		).toBe( 'Thursday, October 22, 2026' );
	} );

	it( 'shows the month and year once for days in one month', () => {
		expect(
			formatHeading(
				'day',
				new Date( 2026, 9, 20 ),
				new Date( 2026, 9, 22 ),
			),
		).toBe( 'Oct 20 – 22, 2026' );
	} );

	it( 'shows both months and the year once for a week across two months', () => {
		expect(
			formatHeading(
				'week',
				new Date( 2026, 9, 25 ),
				new Date( 2026, 10, 7 ),
			),
		).toBe( 'Oct 25 – Nov 7, 2026' );
	} );

	it( 'shows both years for a week across the end of a year', () => {
		expect(
			formatHeading(
				'week',
				new Date( 2026, 11, 27 ),
				new Date( 2027, 0, 2 ),
			),
		).toBe( 'Dec 27, 2026 – Jan 2, 2027' );
	} );

	it( 'shows the month name and year for one month', () => {
		expect(
			formatHeading(
				'month',
				new Date( 2026, 9, 1 ),
				new Date( 2026, 9, 31 ),
			),
		).toBe( 'October 2026' );
	} );

	it( 'shows the year once for months in one year', () => {
		expect(
			formatHeading(
				'month',
				new Date( 2026, 9, 1 ),
				new Date( 2026, 11, 31 ),
			),
		).toBe( 'October – December 2026' );
	} );

	it( 'uses the months of the dates, whatever the time of day', () => {
		expect(
			formatHeading(
				'month',
				new Date( 2026, 9, 31, 23, 59 ),
				new Date( 2026, 10, 1, 0, 0 ),
			),
		).toBe( 'October – November 2026' );
	} );

	it( 'shows short months with their years for months across the end of a year', () => {
		expect(
			formatHeading(
				'month',
				new Date( 2026, 10, 1 ),
				new Date( 2027, 1, 28 ),
			),
		).toBe( 'Nov 2026 – Feb 2027' );
	} );

	it( 'uses the datetime separator from the GatherPress settings, without the spaces around it', () => {
		setEditorSettings( { config: { datetimeSeparator: '  to  ' } } );

		expect(
			formatHeading(
				'day',
				new Date( 2026, 9, 20 ),
				new Date( 2026, 9, 22 ),
			),
		).toBe( 'Oct 20 to 22, 2026' );
		expect(
			formatHeading(
				'month',
				new Date( 2026, 9, 1 ),
				new Date( 2026, 11, 31 ),
			),
		).toBe( 'October to December 2026' );
	} );
} );
