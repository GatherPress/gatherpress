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
import FrequencyControl from '@src/panels/event-settings/recurrence/frequency';

describe( 'FrequencyControl', () => {
	test( 'renders the current frequency and interval', () => {
		render(
			<FrequencyControl
				frequency="weekly"
				interval={ 3 }
				onFrequencyChange={ jest.fn() }
				onIntervalChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Frequency' ) ).toHaveValue( 'weekly' );
		expect( screen.getByLabelText( 'Repeat every' ) ).toHaveValue( 3 );
	} );

	test( 'calls onFrequencyChange with the selected value', () => {
		const onFrequencyChange = jest.fn();

		render(
			<FrequencyControl
				frequency="daily"
				interval={ 1 }
				onFrequencyChange={ onFrequencyChange }
				onIntervalChange={ jest.fn() }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );

		expect( onFrequencyChange ).toHaveBeenCalledWith( 'monthly' );
	} );

	test( 'calls onIntervalChange with a numeric value', () => {
		const onIntervalChange = jest.fn();

		render(
			<FrequencyControl
				frequency="daily"
				interval={ 1 }
				onFrequencyChange={ jest.fn() }
				onIntervalChange={ onIntervalChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '5' },
		} );

		expect( onIntervalChange ).toHaveBeenCalledWith( 5 );
	} );

	test( 'offers Yearly as a selectable frequency option', () => {
		render(
			<FrequencyControl
				frequency="daily"
				interval={ 1 }
				onFrequencyChange={ jest.fn() }
				onIntervalChange={ jest.fn() }
			/>,
		);

		expect(
			screen.getByRole( 'option', { name: 'Yearly' } ),
		).toHaveValue( 'yearly' );
	} );

	test( 'calls onFrequencyChange with yearly when selected', () => {
		const onFrequencyChange = jest.fn();

		render(
			<FrequencyControl
				frequency="daily"
				interval={ 1 }
				onFrequencyChange={ onFrequencyChange }
				onIntervalChange={ jest.fn() }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'yearly' },
		} );

		expect( onFrequencyChange ).toHaveBeenCalledWith( 'yearly' );
	} );

	test( 'renders the current yearly frequency', () => {
		render(
			<FrequencyControl
				frequency="yearly"
				interval={ 1 }
				onFrequencyChange={ jest.fn() }
				onIntervalChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Frequency' ) ).toHaveValue( 'yearly' );
	} );
} );
