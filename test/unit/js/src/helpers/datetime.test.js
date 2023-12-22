/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	getTimeZone,
	getUtcOffset,
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForSelect,
	maybeConvertUtcOffsetForDisplay,
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
