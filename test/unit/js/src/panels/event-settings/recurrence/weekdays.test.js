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
	CheckboxControl: ( { label, checked, onChange } ) => (
		<input
			aria-label={ label }
			type="checkbox"
			checked={ checked }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	),
} ) );

/**
 * Internal dependencies
 */
import WeekdaysControl from '@src/panels/event-settings/recurrence/weekdays';

describe( 'WeekdaysControl', () => {
	test( 'renders every day, checked according to the weekdays array', () => {
		render( <WeekdaysControl weekdays={ [ 2, 4 ] } onChange={ jest.fn() } /> );

		expect( screen.getByLabelText( 'Sunday' ) ).not.toBeChecked();
		expect( screen.getByLabelText( 'Tuesday' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Thursday' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Saturday' ) ).not.toBeChecked();
	} );

	test( 'adds a day when its checkbox is checked', () => {
		const onChange = jest.fn();

		render( <WeekdaysControl weekdays={ [ 2 ] } onChange={ onChange } /> );

		fireEvent.click( screen.getByLabelText( 'Thursday' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 2, 4 ] );
	} );

	test( 'removes a day when its checkbox is unchecked', () => {
		const onChange = jest.fn();

		render(
			<WeekdaysControl weekdays={ [ 2, 4 ] } onChange={ onChange } />,
		);

		fireEvent.click( screen.getByLabelText( 'Tuesday' ) );

		expect( onChange ).toHaveBeenCalledWith( [ 4 ] );
	} );
} );
