/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: jest.fn(),
} ) );

import FormatControl, {
	FORMAT_CUSTOM,
	getFormatChoices,
} from '@src/components/FormatControl';
import { getFromConfig } from '@src/helpers/editor-settings';

const DATE_CHOICES = [
	{ format: 'l, F j, Y', example: 'Wednesday, September 23, 2026' },
	{ format: 'Y-m-d', example: '2026-09-23' },
];

const TIME_CHOICES = [ { format: 'H:i', example: '18:00' } ];

// A format nobody would put on a list, with the backslash escapes a PHP date
// format uses for literal characters.
const UNLISTED_FORMAT = 'jS \\o\\f F';

/**
 * Point `getFromConfig` at the two choice lists.
 *
 * @param {Array|undefined} dates The date choices, or undefined for none.
 * @param {Array|undefined} times The time choices, or undefined for none.
 */
function mockChoices( dates, times ) {
	getFromConfig.mockImplementation( ( key ) =>
		'dateFormatChoices' === key ? dates : times,
	);
}

/**
 * Coverage for the FormatControl component.
 */
describe( 'FormatControl', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockChoices( DATE_CHOICES, TIME_CHOICES );
	} );

	it( 'offers both lists as the dates they render', () => {
		expect( getFormatChoices() ).toEqual( [
			...DATE_CHOICES,
			...TIME_CHOICES,
		] );
	} );

	it( 'offers nothing when neither list is exposed', () => {
		mockChoices( undefined, undefined );

		expect( getFormatChoices() ).toEqual( [] );
	} );

	it( 'renders each choice by its example, plus inherit and custom', () => {
		render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default (a date)"
				onChange={ jest.fn() }
			/>,
		);

		const select = screen.getByLabelText( 'Start date format' );
		const labels = [ ...select.options ].map( ( option ) => option.text );

		expect( labels ).toEqual( [
			'Site default (a date)',
			'Wednesday, September 23, 2026',
			'2026-09-23',
			'18:00',
			'Custom…',
		] );
		expect( select ).toHaveValue( '' );
	} );

	it( 'reports the chosen format', () => {
		const onChange = jest.fn();

		render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Start date format' ), {
			target: { value: 'Y-m-d' },
		} );

		expect( onChange ).toHaveBeenCalledWith( 'Y-m-d' );
	} );

	it( 'reports an empty format when set back to the site default', () => {
		const onChange = jest.fn();

		render(
			<FormatControl
				label="Start date format"
				value="Y-m-d"
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Start date format' ), {
			target: { value: '' },
		} );

		expect( onChange ).toHaveBeenCalledWith( '' );
	} );

	it( 'opens the custom field without reporting the sentinel', () => {
		const onChange = jest.fn();

		render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		expect( screen.queryByLabelText( 'Custom format' ) ).toBeNull();

		fireEvent.change( screen.getByLabelText( 'Start date format' ), {
			target: { value: FORMAT_CUSTOM },
		} );

		expect( screen.getByLabelText( 'Custom format' ) ).toBeInTheDocument();
		expect( onChange ).not.toHaveBeenCalled();
	} );

	it( 'reports what is typed into the custom field', () => {
		const onChange = jest.fn();

		render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Start date format' ), {
			target: { value: FORMAT_CUSTOM },
		} );
		fireEvent.change( screen.getByLabelText( 'Custom format' ), {
			target: { value: 'D' },
		} );

		expect( onChange ).toHaveBeenCalledWith( 'D' );
	} );

	it( 'opens on custom when the saved format is not on the list', () => {
		render(
			<FormatControl
				label="Start date format"
				value={ UNLISTED_FORMAT }
				inheritLabel="Site default"
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Start date format' ) ).toHaveValue(
			FORMAT_CUSTOM,
		);
		expect( screen.getByLabelText( 'Custom format' ) ).toHaveValue(
			UNLISTED_FORMAT,
		);
	} );

	it( 'opens on custom when an unlisted format arrives later', () => {
		const { rerender } = render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.queryByLabelText( 'Custom format' ) ).toBeNull();

		// An undo, a pattern sync or another setAttributes can hand the
		// control a format it never saw at mount.
		rerender(
			<FormatControl
				label="Start date format"
				value={ UNLISTED_FORMAT }
				inheritLabel="Site default"
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Start date format' ) ).toHaveValue(
			FORMAT_CUSTOM,
		);
		expect( screen.getByLabelText( 'Custom format' ) ).toHaveValue(
			UNLISTED_FORMAT,
		);
	} );

	it( 'keeps the custom field open when it is emptied', () => {
		const onChange = jest.fn();

		const { rerender } = render(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		fireEvent.change( screen.getByLabelText( 'Start date format' ), {
			target: { value: FORMAT_CUSTOM },
		} );
		fireEvent.change( screen.getByLabelText( 'Custom format' ), {
			target: { value: '' },
		} );

		rerender(
			<FormatControl
				label="Start date format"
				value=""
				inheritLabel="Site default"
				onChange={ onChange }
			/>,
		);

		expect( screen.getByLabelText( 'Custom format' ) ).toBeInTheDocument();
	} );

	it( 'opens on the list when the saved format is on it', () => {
		render(
			<FormatControl
				label="Start date format"
				value="Y-m-d"
				inheritLabel="Site default"
				onChange={ jest.fn() }
			/>,
		);

		expect( screen.getByLabelText( 'Start date format' ) ).toHaveValue(
			'Y-m-d',
		);
		expect( screen.queryByLabelText( 'Custom format' ) ).toBeNull();
	} );
} );
