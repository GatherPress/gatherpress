/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	DateTimeStartLabel,
	DateTimeEndLabel,
} from '../../../../../src/components/DateTime';

/**
 * Coverage for DateTimeStartLabel.
 */
test('DateTimeStartLabel displays correct format', () => {
	const props = {
		dateTimeStart: '2023-12-28T10:26:00',
	};

	expect(DateTimeStartLabel(props)).toBe('December 28, 2023 10:26 am');
});

/**
 * Coverage for DateTimeEndLabel.
 */
test('DateTimeEndLabel displays correct format', () => {
	const props = {
		dateTimeEnd: '2023-12-28T10:26:00',
	};

	expect(DateTimeEndLabel(props)).toBe('December 28, 2023 10:26 am');
});
