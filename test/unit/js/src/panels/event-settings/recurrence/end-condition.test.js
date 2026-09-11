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
	TextControl: ( { label, value, onChange, type } ) => (
		<input
			aria-label={ label }
			type={ type }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
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
import EndConditionControl from '@src/panels/event-settings/recurrence/end-condition';

describe( 'EndConditionControl', () => {
	test( 'renders neither companion field for endType never', () => {
		render(
			<EndConditionControl
				endType="never"
				until=""
				count={ 0 }
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.queryByLabelText( 'End date' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText( 'Number of occurrences' ),
		).not.toBeInTheDocument();
	} );

	test( 'renders the date field for endType until', () => {
		render(
			<EndConditionControl
				endType="until"
				until="2026-09-01"
				count={ 0 }
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'End date' ) ).toHaveValue( '2026-09-01' );
		expect(
			screen.queryByLabelText( 'Number of occurrences' ),
		).not.toBeInTheDocument();
	} );

	test( 'renders the count field for endType count', () => {
		render(
			<EndConditionControl
				endType="count"
				until=""
				count={ 10 }
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.queryByLabelText( 'End date' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Number of occurrences' ) ).toHaveValue(
			10,
		);
	} );

	test( 'calls onChange with end_type when the select changes', () => {
		const onChange = jest.fn();

		render(
			<EndConditionControl
				endType="never"
				until=""
				count={ 0 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'until' },
		} );

		expect( onChange ).toHaveBeenCalledWith( { end_type: 'until' } );
	} );

	test( 'calls onChange with until when the date field changes', () => {
		const onChange = jest.fn();

		render(
			<EndConditionControl
				endType="until"
				until=""
				count={ 0 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'End date' ), {
			target: { value: '2026-10-01' },
		} );

		expect( onChange ).toHaveBeenCalledWith( { until: '2026-10-01' } );
	} );

	test( 'calls onChange with count when the count field changes', () => {
		const onChange = jest.fn();

		render(
			<EndConditionControl
				endType="count"
				until=""
				count={ 0 }
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Number of occurrences' ), {
			target: { value: '5' },
		} );

		expect( onChange ).toHaveBeenCalledWith( { count: 5 } );
	} );
} );
