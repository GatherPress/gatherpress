/**
 * External dependencies.
 */
import { render } from '@testing-library/react';
import { expect, jest, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import {
	DateTimeStartLabel,
	DateTimeStartPicker,
	DateTimeEndLabel,
	DateTimeEndPicker,
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

/**
 * Coverage for DateTimeStartPicker.
 */
test('DateTimeStartPicker renders component correctly', () => {
	const dateTimeStart = '2023-12-28T10:26:00';
	const setDateTimeStart = jest.fn();

	const { container } = render(
		<DateTimeStartPicker
			dateTimeStart={dateTimeStart}
			setDateTimeStart={setDateTimeStart}
		/>
	);

	expect(container.children[0]).toHaveClass('components-datetime');
});

/**
 * Coverage for DateTimeEndPicker.
 */
test('DateTimeEndPicker renders component correctly', () => {
	const dateTimeEnd = '2023-12-28T10:26:00';
	const setDateTimeEnd = jest.fn();

	const { container } = render(
		<DateTimeEndPicker
			dateTimeEnd={dateTimeEnd}
			setDateTimeEnd={setDateTimeEnd}
		/>
	);

	expect(container.children[0]).toHaveClass('components-datetime');
});
