/**
 * External dependencies.
 */
import { expect, test, jest, describe, beforeEach } from '@jest/globals';
import 'moment-timezone';

/**
 * Internal dependencies.
 */
import {
	convertPHPToMomentFormat,
	dateTimeLabelFormat,
	dateTimeOffset,
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDateTimeEnd,
	getDateTimeOffset,
	getDateTimeStart,
	getTimezone,
	getUtcOffset,
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForDisplay,
	maybeConvertUtcOffsetForSelect,
	updateDateTimeEnd,
	updateDateTimeStart,
	validateDateTimeStart,
} from '../../../../../src/helpers/datetime';

/**
 * Coverage for dateTimeLabelFormat.
 */
test( 'dateTimeLabelFormat returns correct format', () => {
	global.GatherPress = {
		settings: {
			dateFormat: 'F j, Y',
			timeFormat: 'g:i a',
		},
	};

	expect( dateTimeLabelFormat() ).toBe( 'MMMM D, YYYY h:mm a' );
} );

/**
 * Coverage for getTimeZone.
 */
test( 'getTimeZone returns set timezone', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: 'America/New_York',
			},
		},
	};

	expect( getTimezone() ).toBe( 'America/New_York' );
} );

test( 'getTimeZone returns GMT when timezone is not set', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: '',
			},
		},
	};

	expect( getTimezone() ).toBe( 'GMT' );
} );

/**
 * Coverage for getUtcOffset.
 */
test( 'getUtcOffset returns empty when not GMT', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: 'America/New_York',
			},
		},
	};

	expect( getUtcOffset() ).toBe( '' );
} );

test( 'getUtcOffset returns offset in proper display format', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: '+02:00',
			},
		},
	};

	expect( getUtcOffset() ).toBe( '+0200' );
} );

/**
 * Coverage for maybeConvertUtcOffsetForDisplay.
 */
test( 'maybeConvertUtcOffsetForDisplay converts offset correctly for display', () => {
	const offset = '+01:00';

	expect( maybeConvertUtcOffsetForDisplay( offset ) ).toBe( '+0100' );
} );

test( 'maybeConvertUtcOffsetForDisplay does not convert with empty argument', () => {
	expect( maybeConvertUtcOffsetForDisplay() ).toBe( '' );
} );

/**
 * Coverage for maybeConvertUtcOffsetForDatabase.
 */
test( 'maybeConvertUtcOffsetForDatabase converts UTC+9.5 to correct format', () => {
	const offset = 'UTC+9.5';

	expect( maybeConvertUtcOffsetForDatabase( offset ) ).toBe( '+09:30' );
} );

test( 'maybeConvertUtcOffsetForDatabase does not convert UTC', () => {
	const offset = 'UTC';

	expect( maybeConvertUtcOffsetForDatabase( offset ) ).toBe( 'UTC' );
} );

test( 'maybeConvertUtcOffsetForDatabase converts UTC-1.75 to correct format', () => {
	const offset = 'UTC-1.75';

	expect( maybeConvertUtcOffsetForDatabase( offset ) ).toBe( '-01:45' );
} );

test( 'maybeConvertUtcOffsetForDatabase converts UTC-1.75 to correct format', () => {
	const offset = 'UTC-1.75';

	expect( maybeConvertUtcOffsetForDatabase( offset ) ).toBe( '-01:45' );
} );

test( 'maybeConvertUtcOffsetForDatabase converts UTC+12 to correct format', () => {
	const offset = 'UTC+12';

	expect( maybeConvertUtcOffsetForDatabase( offset ) ).toBe( '+12:00' );
} );

test( 'maybeConvertUtcOffsetForDatabase does not convert default empty argument', () => {
	expect( maybeConvertUtcOffsetForDatabase() ).toBe( '' );
} );

/**
 * Coverage for maybeConvertUtcOffsetForSelect.
 */
test( 'maybeConvertUtcOffsetForSelect converts +04:30 to correct format', () => {
	const offset = '+04:30';

	expect( maybeConvertUtcOffsetForSelect( offset ) ).toBe( 'UTC+4.5' );
} );

test( 'maybeConvertUtcOffsetForSelect converts +00:00 to correct format', () => {
	const offset = '+00:00';

	expect( maybeConvertUtcOffsetForSelect( offset ) ).toBe( 'UTC+0' );
} );

test( 'maybeConvertUtcOffsetForSelect converts -01:15 to correct format', () => {
	const offset = '-01:15';

	expect( maybeConvertUtcOffsetForSelect( offset ) ).toBe( 'UTC-1.25' );
} );

test( 'maybeConvertUtcOffsetForSelect does not convert non-pattern', () => {
	const offset = 'UTC';

	expect( maybeConvertUtcOffsetForSelect( offset ) ).toBe( 'UTC' );
} );

test( 'maybeConvertUtcOffsetForSelect does not convert non-pattern (default empty argument)', () => {
	expect( maybeConvertUtcOffsetForSelect() ).toBe( '' );
} );

/**
 * Coverage for getDateTimeStart.
 */
test( 'getDateTimeStart converts format of date/time start from global', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_start: '2023-12-28 12:26:00',
			},
		},
	};

	expect( getDateTimeStart() ).toBe( '2023-12-28 12:26:00' );
} );

test( 'getDateTimeStart converts format of date/time start from default', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_start: '',
			},
		},
	};

	expect( getDateTimeStart() ).toBe( defaultDateTimeStart );
} );

/**
 * Coverage for getDateTimeEnd.
 */
test( 'getDateTimeEnd converts format of date/time end from global', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_end: '2023-12-28 12:26:00',
			},
		},
	};

	expect( getDateTimeEnd() ).toBe( '2023-12-28 12:26:00' );
} );

test( 'getDateTimeEnd converts format of date/time end from default', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_end: '',
			},
		},
	};

	expect( getDateTimeEnd() ).toBe( defaultDateTimeEnd );
} );

/**
 * Coverage for updateDateTimeStart.
 */
test( 'updateDateTimeStart with second argument', () => {
	const date = '2023-12-29 12:26:00';
	const setDateTimeStart = ( arg ) => {
		return arg;
	};

	updateDateTimeStart( date, setDateTimeStart );

	expect( global.GatherPress.eventDetails.dateTime.datetime_start ).toBe( date );
} );

test( 'updateDateTimeStart without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeStart( date );

	expect( global.GatherPress.eventDetails.dateTime.datetime_start ).toBe( date );
} );

/**
 * Coverage for updateDateTimeEnd.
 */
test( 'updateDateTimeEnd with second argument', () => {
	const date = '2023-12-29 12:26:00';
	const setDateTimeEnd = ( arg ) => {
		return arg;
	};

	updateDateTimeEnd( date, setDateTimeEnd );

	expect( global.GatherPress.eventDetails.dateTime.datetime_end ).toBe( date );
} );

test( 'updateDateTimeEnd without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeEnd( date );

	expect( global.GatherPress.eventDetails.dateTime.datetime_end ).toBe( date );
} );

/**
 * Coverage for convertPHPToMomentFormat.
 */
test( 'convertPHPToMomentFormat returns correct date format', () => {
	const format = convertPHPToMomentFormat( 'F j, Y' );

	expect( format ).toBe( 'MMMM D, YYYY' );
} );

test( 'convertPHPToMomentFormat returns correct time format', () => {
	const format = convertPHPToMomentFormat( 'g:i a' );

	expect( format ).toBe( 'h:mm a' );
} );

test( 'convertPHPToMomentFormat returns correct format that contains escaped chars, like ES or DE needs', () => {
	const format = convertPHPToMomentFormat( 'G:i \\U\\h\\r' ); // "20 Uhr" is german for "8 o'clock" (in the evening).

	expect( format ).toBe( 'H:mm \\U\\h\\r' );
} );

/**
 * Coverage for relative mode (duration) functionality.
 */
describe( 'Relative mode duration tests', () => {
	beforeEach( () => {
		// Reset global state before each test.
		global.GatherPress = {
			eventDetails: {
				dateTime: {
					timezone: 'America/New_York',
					datetime_start: '2023-11-28 18:00:00',
					datetime_end: '2023-11-28 20:00:00',
				},
			},
		};
	} );

	test( 'dateTimeOffset calculates correct end time based on duration', () => {
		global.GatherPress.eventDetails.dateTime.datetime_start = '2023-11-26 18:00:00';
		const result = dateTimeOffset( 2 ); // 2 hours offset.
		expect( result ).toBe( '2023-11-26 20:00:00' );
	} );

	test( 'getDateTimeOffset returns correct duration when end matches offset', () => {
		// Start: 18:00, End: 20:00 = 2 hour duration.
		const duration = getDateTimeOffset();
		expect( duration ).toBe( 2 );
	} );

	test( 'getDateTimeOffset returns false when end does not match any duration option', () => {
		// Set end time to something that doesn't match standard durations.
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-28 22:30:00';
		const duration = getDateTimeOffset();
		expect( duration ).toBe( false );
	} );

	test( 'updateDateTimeStart maintains relative offset in relative mode', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Initial setup: 2-hour duration (18:00-20:00).
		const newStartDate = '2023-11-26 18:00:00';

		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		expect( setDateTimeStart ).toHaveBeenCalledWith( newStartDate );
		// Should maintain 2-hour offset.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-26 20:00:00' );
		expect( global.GatherPress.eventDetails.dateTime.datetime_start ).toBe( newStartDate );
	} );

	test( 'updateDateTimeStart does not update end time in absolute mode', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Set end time to not match any duration option (absolute mode).
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-29 22:30:00';

		const newStartDate = '2023-11-26 18:00:00';

		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		expect( setDateTimeStart ).toHaveBeenCalledWith( newStartDate );
		// End time should not be updated since we're in absolute mode.
		expect( setDateTimeEnd ).not.toHaveBeenCalled();
		expect( global.GatherPress.eventDetails.dateTime.datetime_start ).toBe( newStartDate );
	} );

	test( 'updateDateTimeStart validates when start >= end in absolute mode', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Set end time to not match any duration option (absolute mode).
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-26 17:00:00';

		// Try to set start after end.
		const newStartDate = '2023-11-26 18:00:00';

		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		expect( setDateTimeStart ).toHaveBeenCalledWith( newStartDate );
		// Should update end to be 2 hours after start due to validation.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-26 20:00:00' );
	} );

	test( 'validateDateTimeStart respects numeric duration in relative mode', () => {
		const setDateTimeEnd = jest.fn();

		// Test with 3-hour duration.
		validateDateTimeStart( '2023-11-30 18:00:00', setDateTimeEnd, 3 );

		// Should add 3 hours to the new start time.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-30 21:00:00' );
	} );

	test( 'validateDateTimeStart uses default 2 hours when duration is false', () => {
		const setDateTimeEnd = jest.fn();

		// Duration is false (absolute mode).
		validateDateTimeStart( '2023-11-30 18:00:00', setDateTimeEnd, false );

		// Should default to 2 hours.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-30 20:00:00' );
	} );

	test( 'validateDateTimeStart does not update end when start < end in absolute mode', () => {
		const setDateTimeEnd = jest.fn();

		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-30 22:00:00';

		// Start is before end, no validation needed.
		validateDateTimeStart( '2023-11-30 18:00:00', setDateTimeEnd, false );

		// Should not call setDateTimeEnd.
		expect( setDateTimeEnd ).not.toHaveBeenCalled();
	} );

	test( 'relative mode works with different duration values', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Test with 1 hour duration.
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-28 19:00:00';

		const newStartDate = '2023-11-26 18:00:00';
		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		// Should maintain 1-hour offset.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-26 19:00:00' );
	} );

	test( 'relative mode works with 1.5 hour duration', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Test with 1.5 hour duration.
		global.GatherPress.eventDetails.dateTime.datetime_start = '2023-11-28 18:00:00';
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-28 19:30:00';

		const newStartDate = '2023-11-26 18:00:00';
		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		// Should maintain 1.5-hour offset.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-26 19:30:00' );
	} );

	test( 'relative mode works with 3 hour duration', () => {
		const setDateTimeStart = jest.fn();
		const setDateTimeEnd = jest.fn();

		// Test with 3 hour duration.
		global.GatherPress.eventDetails.dateTime.datetime_start = '2023-11-28 18:00:00';
		global.GatherPress.eventDetails.dateTime.datetime_end = '2023-11-28 21:00:00';

		const newStartDate = '2023-11-26 18:00:00';
		updateDateTimeStart( newStartDate, setDateTimeStart, setDateTimeEnd );

		// Should maintain 3-hour offset.
		expect( setDateTimeEnd ).toHaveBeenCalledWith( '2023-11-26 21:00:00' );
	} );
} );
