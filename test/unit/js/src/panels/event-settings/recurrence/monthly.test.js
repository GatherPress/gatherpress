/**
 * External dependencies
 */
import { render, fireEvent, screen } from '@testing-library/react';
import { describe, expect, jest, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/components', () => ( {
	SelectControl: ( { label, value, options, onChange } ) => (
		<select
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	__experimentalNumberControl: ( { label, value, onChange } ) => (
		<input
			aria-label={ label }
			type="number"
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );

/**
 * Internal dependencies
 */
import MonthlyControl from '@src/panels/event-settings/recurrence/monthly';

describe( 'MonthlyControl', () => {
	test( 'renders the day-of-month input when monthlyMode is day_of_month', () => {
		render(
			<MonthlyControl
				monthlyMode="day_of_month"
				monthlyDay={ 15 }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Day of the month' ) ).toHaveValue( 15 );
		expect( screen.queryByLabelText( 'Week' ) ).not.toBeInTheDocument();
		expect( screen.queryByLabelText( 'Day' ) ).not.toBeInTheDocument();
	} );

	test( 'renders the ordinal/weekday selects when monthlyMode is nth_weekday', () => {
		render(
			<MonthlyControl
				monthlyMode="nth_weekday"
				monthlyDay={ 1 }
				monthlyOrdinal={ -1 }
				monthlyWeekday={ 3 }
				onChange={ jest.fn() }
			/>,
		);

		expect(
			screen.queryByLabelText( 'Day of the month' ),
		).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Week' ) ).toHaveValue( '-1' );
		expect( screen.getByLabelText( 'Day' ) ).toHaveValue( '3' );
	} );

	test( 'calls onChange with monthly_mode when the mode select changes', () => {
		const onChange = jest.fn();

		render(
			<MonthlyControl
				monthlyMode="day_of_month"
				monthlyDay={ 1 }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Repeat by' ), {
			target: { value: 'nth_weekday' },
		} );

		expect( onChange ).toHaveBeenCalledWith( { monthly_mode: 'nth_weekday' } );
	} );

	test( 'calls onChange with monthly_day when the day input changes', () => {
		const onChange = jest.fn();

		render(
			<MonthlyControl
				monthlyMode="day_of_month"
				monthlyDay={ 1 }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '15' },
		} );

		expect( onChange ).toHaveBeenCalledWith( { monthly_day: '15' } );
	} );

	test( 'hands the raw control value to onChange without coercing it', () => {
		const onChange = jest.fn();

		render(
			<MonthlyControl
				monthlyMode="day_of_month"
				monthlyDay={ 1 }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '' },
		} );

		// `Number( '' )` is 0, which is a rule the server rejects. The raw
		// value has to survive so the panel can tell "cleared" from "zero".
		expect( onChange ).toHaveBeenCalledWith( { monthly_day: '' } );
	} );

	test( 'renders an empty day field when monthlyDay is null', () => {
		render(
			<MonthlyControl
				monthlyMode="day_of_month"
				monthlyDay={ null }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Day of the month' ) ).toHaveValue( null );
	} );

	test( 'calls onChange with monthly_ordinal and monthly_weekday when nth-weekday selects change', () => {
		const onChange = jest.fn();

		render(
			<MonthlyControl
				monthlyMode="nth_weekday"
				monthlyDay={ 1 }
				monthlyOrdinal={ 1 }
				monthlyWeekday={ 1 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Week' ), {
			target: { value: '-1' },
		} );
		expect( onChange ).toHaveBeenCalledWith( { monthly_ordinal: -1 } );

		fireEvent.change( screen.getByLabelText( 'Day' ), {
			target: { value: '3' },
		} );
		expect( onChange ).toHaveBeenCalledWith( { monthly_weekday: 3 } );
	} );
} );
