/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { maybeConvertUtcOffsetForDatabase } from '../../../../../src/helpers/datetime';

/**
 * Coverage for maybeConvertUtcOffsetForDatabase.
 */
test('maybeConvertUtcOffsetForDatabase converts UTC+9.5 to correct format', () => {
	const offset = 'UTC+9.5';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('+09:30');
});

test('maybeConvertUtcOffsetForDatabase converts UTC to correct format', () => {
	const offset = 'UTC';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('UTC');
});

test('maybeConvertUtcOffsetForDatabase converts UTC-1.75 to correct format', () => {
	const offset = 'UTC-1.75';

	expect(maybeConvertUtcOffsetForDatabase(offset)).toBe('-01:45');
});
