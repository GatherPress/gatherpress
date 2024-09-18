/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';
import 'moment-timezone';

/**
 * Internal dependencies.
 */
import {
	convertPHPToMomentFormat,
	dateTimeLabelFormat,
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDateTimeEnd,
	getDateTimeStart,
	getTimezone,
	getUtcOffset,
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForDisplay,
	maybeConvertUtcOffsetForSelect,
	updateDateTimeEnd,
	updateDateTimeStart,
} from '../../../../../src/helpers/datetime';

/**
 * Coverage for dateTimeLabelFormat.
 */
test('dateTimeLabelFormat returns correct format', () => {
	global.GatherPress = {
		settings: {
			dateFormat: 'F j, Y',
			timeFormat: 'g:i a',
		},
	};

	expect(dateTimeLabelFormat()).toBe('MMMM D, YYYY h:mm a');
});

/**
 * Coverage for getTimeZone.
 */
test('getTimeZone returns set timezone', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: 'America/New_York',
			},
		},
	};

	expect(getTimezone()).toBe('America/New_York');
});

test('getTimeZone returns GMT when timezone is not set', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: '',
			},
		},
	};

	expect(getTimezone()).toBe('GMT');
});

/**
 * Coverage for getUtcOffset.
 */
test('getUtcOffset returns empty when not GMT', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: 'America/New_York',
			},
		},
	};

	expect(getUtcOffset()).toBe('');
});

test('getUtcOffset returns offset in proper display format', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				timezone: '+02:00',
			},
		},
	};

	expect(getUtcOffset()).toBe('+0200');
});

/**
 * Coverage for maybeConvertUtcOffsetForDisplay.
 */
test('maybeConvertUtcOffsetForDisplay converts offset correctly for display', () => {
	const offset = '+01:00';

	expect(maybeConvertUtcOffsetForDisplay(offset)).toBe('+0100');
});

test('maybeConvertUtcOffsetForDisplay does not convert with empty argument', () => {
	expect(maybeConvertUtcOffsetForDisplay()).toBe('');
});

/**
 * Coverage for maybeConvertUtcOffsetForDatabase.
 */
test('maybeConvertUtcOffsetForDatabase converts UTC+9.5 to correct format', () => {
	const offset = 'UTC+9.5';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('+09:30');
});

test('maybeConvertUtcOffsetForDatabase does not convert UTC', () => {
	const offset = 'UTC';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('UTC');
});

test('maybeConvertUtcOffsetForDatabase converts UTC-1.75 to correct format', () => {
	const offset = 'UTC-1.75';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('-01:45');
});

test('maybeConvertUtcOffsetForDatabase converts UTC-1.75 to correct format', () => {
	const offset = 'UTC-1.75';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('-01:45');
});

test('maybeConvertUtcOffsetForDatabase converts UTC+12 to correct format', () => {
	const offset = 'UTC+12';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('+12:00');
});

test('maybeConvertUtcOffsetForDatabase does not convert default empty argument', () => {
	expect(maybeConvertUtcOffsetForDatabase()).toBe('');
});

/**
 * Coverage for maybeConvertUtcOffsetForSelect.
 */
test('maybeConvertUtcOffsetForSelect converts +04:30 to correct format', () => {
	const offset = '+04:30';

	expect(maybeConvertUtcOffsetForSelect(offset)).toBe('UTC+4.5');
});

test('maybeConvertUtcOffsetForSelect converts +00:00 to correct format', () => {
	const offset = '+00:00';

	expect(maybeConvertUtcOffsetForSelect(offset)).toBe('UTC+0');
});

test('maybeConvertUtcOffsetForSelect converts -01:15 to correct format', () => {
	const offset = '-01:15';

	expect(maybeConvertUtcOffsetForSelect(offset)).toBe('UTC-1.25');
});

test('maybeConvertUtcOffsetForSelect does not convert non-pattern', () => {
	const offset = 'UTC';

	expect(maybeConvertUtcOffsetForSelect(offset)).toBe('UTC');
});

test('maybeConvertUtcOffsetForSelect does not convert non-pattern (default empty argument)', () => {
	expect(maybeConvertUtcOffsetForSelect()).toBe('');
});

/**
 * Coverage for getDateTimeStart.
 */
test('getDateTimeStart converts format of date/time start from global', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_start: '2023-12-28 12:26:00',
			},
		},
	};

	expect(getDateTimeStart()).toBe('2023-12-28 12:26:00');
});

test('getDateTimeStart converts format of date/time start from default', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_start: '',
			},
		},
	};

	expect(getDateTimeStart()).toBe(defaultDateTimeStart);
});

/**
 * Coverage for getDateTimeEnd.
 */
test('getDateTimeEnd converts format of date/time end from global', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_end: '2023-12-28 12:26:00',
			},
		},
	};

	expect(getDateTimeEnd()).toBe('2023-12-28 12:26:00');
});

test('getDateTimeEnd converts format of date/time end from default', () => {
	global.GatherPress = {
		eventDetails: {
			dateTime: {
				datetime_end: '',
			},
		},
	};

	expect(getDateTimeEnd()).toBe(defaultDateTimeEnd);
});

/**
 * Coverage for updateDateTimeStart.
 */
test('updateDateTimeStart with second argument', () => {
	const date = '2023-12-29 12:26:00';
	const setDateTimeStart = (arg) => {
		return arg;
	};

	updateDateTimeStart(date, setDateTimeStart);

	expect(global.GatherPress.eventDetails.dateTime.datetime_start).toBe(date);
});

test('updateDateTimeStart without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeStart(date);

	expect(global.GatherPress.eventDetails.dateTime.datetime_start).toBe(date);
});

/**
 * Coverage for updateDateTimeEnd.
 */
test('updateDateTimeEnd with second argument', () => {
	const date = '2023-12-29 12:26:00';
	const setDateTimeEnd = (arg) => {
		return arg;
	};

	updateDateTimeEnd(date, setDateTimeEnd);

	expect(global.GatherPress.eventDetails.dateTime.datetime_end).toBe(date);
});

test('updateDateTimeEnd without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeEnd(date);

	expect(global.GatherPress.eventDetails.dateTime.datetime_end).toBe(date);
});

/**
 * Coverage for convertPHPToMomentFormat.
 */
test('convertPHPToMomentFormat returns correct date format', () => {
	const format = convertPHPToMomentFormat('F j, Y');

	expect(format).toBe('MMMM D, YYYY');
});

test('convertPHPToMomentFormat returns correct time format', () => {
	const format = convertPHPToMomentFormat('g:i a');

	expect(format).toBe('h:mm a');
});

test('convertPHPToMomentFormat returns correct format that contains escaped chars, like ES or DE needs', () => {
	const format = convertPHPToMomentFormat('G:i \\U\\h\\r'); // "20 Uhr" is german for "8 o'clock" (in the evening).

	expect(format).toBe('H:mm \\U\\h\\r');
});
