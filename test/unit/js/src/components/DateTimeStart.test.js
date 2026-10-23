/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import DateTimeStart from '@src/components/DateTimeStart';

let mockIsAllDay = false;

// @wordpress/components needs the real @wordpress/data, which imports itself
// while it loads; a Proxy reads it lazily, so only the two hooks are replaced.
jest.mock( '@wordpress/data', () => {
	const mockActual = jest.requireActual( '@wordpress/data' );
	const mockHooks = {
		useSelect: ( selector ) =>
			selector( ( store ) =>
				'core/editor' === store
					? {
						getEditedPostAttribute: () => ( {
							gatherpress_is_all_day: mockIsAllDay,
						} ),
					}
					: { getDateTimeStart: () => '2026-10-17 18:00:00' },
			),
		useDispatch: () => ( {
			setDateTimeStart: () => {},
			setDateTimeEnd: () => {},
		} ),
	};

	return new Proxy( mockActual, {
		get: ( target, prop ) =>
			prop in mockHooks ? mockHooks[ prop ] : target[ prop ],
	} );
} );

// The pickers are not under test, and the real ones update state after they
// open, outside act().
jest.mock( '@wordpress/components', () => {
	const mockActual = jest.requireActual( '@wordpress/components' );
	const mockPickers = {
		DateTimePicker: () => <div>date and time picker</div>,
		DatePicker: () => <div>date picker</div>,
	};

	return new Proxy( mockActual, {
		get: ( target, prop ) =>
			prop in mockPickers ? mockPickers[ prop ] : target[ prop ],
	} );
} );

jest.mock( '@src/helpers/datetime', () => ( {
	createMomentWithTimezone: jest.fn( () => ( {
		format: ( pattern ) =>
			'date-only' === pattern
				? 'October 17, 2026'
				: 'October 17, 2026 6:00 pm',
	} ) ),
	dateTimeDatabaseFormat: 'YYYY-MM-DD HH:mm:ss',
	dateLabelFormat: jest.fn( () => 'date-only' ),
	dateTimeLabelFormat: jest.fn( () => 'date-and-time' ),
	dateTimeOffset: jest.fn(),
	getTimezone: jest.fn( () => 'UTC' ),
	toDayStart: jest.fn(),
	updateDateTimeStart: jest.fn(),
	useMatchedDuration: jest.fn( () => null ),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	hasEventPastNotice: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	getStartOfWeek: jest.fn( () => 0 ),
} ) );

/**
 * Coverage for the DateTimeStart toggle button. The heading label alone
 * replaced the date as the button's accessible name, so screen readers did
 * not read the date (#2466).
 */
describe( 'DateTimeStart', () => {
	beforeEach( () => {
		mockIsAllDay = false;
	} );

	it( 'names the toggle button with the start date and time', () => {
		render( <DateTimeStart /> );

		const button = screen.getByRole( 'button', {
			name: 'Date & time start October 17, 2026 6:00 pm',
		} );

		expect( button ).toHaveAttribute( 'id', 'gatherpress-datetime-start' );
	} );

	it( 'names the toggle button with the start date for an all-day event', () => {
		mockIsAllDay = true;

		render( <DateTimeStart /> );

		expect(
			screen.getByRole( 'button', { name: 'Date start October 17, 2026' } ),
		).toHaveAttribute( 'id', 'gatherpress-datetime-start' );
	} );

	it( 'opens the date picker when the heading label is clicked', () => {
		render( <DateTimeStart /> );

		const button = document.getElementById( 'gatherpress-datetime-start' );

		expect( button ).toHaveAttribute( 'aria-expanded', 'false' );

		fireEvent.click( screen.getByText( 'Date & time start' ) );

		expect( button ).toHaveAttribute( 'aria-expanded', 'true' );
		expect( screen.getByText( 'date and time picker' ) ).toBeInTheDocument();
	} );
} );
