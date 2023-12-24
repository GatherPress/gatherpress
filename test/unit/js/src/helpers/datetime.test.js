/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getDateTimeEnd,
	getDateTimeStart,
	getTimeZone,
	getUtcOffset,
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForDisplay,
	maybeConvertUtcOffsetForSelect,
	updateDateTimeEnd,
	updateDateTimeStart,
} from '../../../../../src/helpers/datetime';

/**
 * Coverage for getTimeZone.
 */
test('getTimeZone returns set timezone', () => {
	global.GatherPress = {
		event_datetime: {
			timezone: 'America/New_York',
		},
	};

	expect(getTimeZone()).toBe('America/New_York');
});

test('getTimeZone returns GMT when timezone is not set', () => {
	global.GatherPress = {
		event_datetime: {
			timezone: '',
		},
	};

	expect(getTimeZone()).toBe('GMT');
});

/**
 * Coverage for getUtcOffset.
 */
test('getUtcOffset returns empty when not GMT', () => {
	global.GatherPress = {
		event_datetime: {
			timezone: 'America/New_York',
		},
	};

	expect(getUtcOffset()).toBe('');
});

test('getUtcOffset returns offset in proper display format', () => {
	global.GatherPress = {
		event_datetime: {
			timezone: '+02:00',
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
		event_datetime: {
			datetime_start: '2023-12-28 12:26:00',
		},
	};

	expect(getDateTimeStart()).toBe('2023-12-28T12:26:00');
});

test('getDateTimeStart converts format of date/time start from default', () => {
	global.GatherPress = {
		event_datetime: {
			datetime_start: '',
		},
	};

	expect(getDateTimeStart()).toBe(defaultDateTimeStart);
});

/**
 * Coverage for getDateTimeEnd.
 */
test('getDateTimeEnd converts format of date/time end from global', () => {
	global.GatherPress = {
		event_datetime: {
			datetime_end: '2023-12-28 12:26:00',
		},
	};

	expect(getDateTimeEnd()).toBe('2023-12-28T12:26:00');
});

test('getDateTimeEnd converts format of date/time end from default', () => {
	global.GatherPress = {
		event_datetime: {
			datetime_end: '',
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

	expect(global.GatherPress.event_datetime.datetime_start).toBe(date);
});

test('updateDateTimeStart without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeStart(date);

	expect(global.GatherPress.event_datetime.datetime_start).toBe(date);
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

	expect(global.GatherPress.event_datetime.datetime_end).toBe(date);
});

test('updateDateTimeEnd without second argument', () => {
	const date = '2023-12-28 12:26:00';

	updateDateTimeEnd(date);

	expect(global.GatherPress.event_datetime.datetime_end).toBe(date);
});
