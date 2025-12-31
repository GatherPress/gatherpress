/**
 * External dependencies.
 */
import { render, act } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
jest.mock( '@wordpress/date', () => ( {
	format: jest.fn( ( dateFormat ) => `Formatted: ${ dateFormat }` ),
} ) );

/**
 * Internal dependencies.
 */
import DateTimePreview from '../../../../../src/components/DateTimePreview';
import { format } from '@wordpress/date';

/**
 * Coverage for DateTimePreview component.
 */
describe( 'DateTimePreview', () => {
	let mockInput;
	let eventListeners;

	beforeEach( () => {
		jest.clearAllMocks();
		eventListeners = {};

		// Mock input element with addEventListener.
		mockInput = {
			addEventListener: jest.fn( ( event, handler, options ) => {
				eventListeners[ event ] = { handler, options };
			} ),
			value: '',
		};

		// Mock document.querySelector to return our mock input.
		document.querySelector = jest.fn( () => mockInput );
	} );

	it( 'renders component with initial date format', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: 'Y-m-d H:i:s',
			},
		};

		const { container } = render( <DateTimePreview { ...props } /> );

		expect( format ).toHaveBeenCalledWith( 'Y-m-d H:i:s' );
		expect( container.textContent ).toContain( 'Formatted: Y-m-d H:i:s' );
	} );

	it( 'queries for input element by name attribute', () => {
		const props = {
			attrs: {
				name: 'test_input_name',
				value: 'Y-m-d',
			},
		};

		render( <DateTimePreview { ...props } /> );

		expect( document.querySelector ).toHaveBeenCalledWith(
			'[name="test_input_name"]'
		);
	} );

	it( 'adds event listener to input element', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: 'Y-m-d H:i:s',
			},
		};

		render( <DateTimePreview { ...props } /> );

		expect( mockInput.addEventListener ).toHaveBeenCalledWith(
			'input',
			expect.any( Function ),
			{ once: true }
		);
	} );

	it( 'sets once: true option for event listener', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: 'Y-m-d',
			},
		};

		render( <DateTimePreview { ...props } /> );

		expect( eventListeners.input.options ).toEqual( { once: true } );
	} );

	it( 'updates format when input event is triggered', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: 'Y-m-d H:i:s',
			},
		};

		const { rerender } = render( <DateTimePreview { ...props } /> );

		// Trigger the input event.
		const inputEvent = {
			target: {
				value: 'F j, Y g:i a',
			},
		};

		// Wrap state update in act().
		act( () => {
			eventListeners.input.handler( inputEvent );
		} );

		// Force re-render to see updated state.
		rerender( <DateTimePreview { ...props } /> );

		expect( format ).toHaveBeenCalledWith( 'F j, Y g:i a' );
	} );

	it( 'renders nothing when value is empty', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: '',
			},
		};

		const { container } = render( <DateTimePreview { ...props } /> );

		expect( container.textContent ).toBe( '' );
	} );

	it( 'renders nothing when value is null', () => {
		const props = {
			attrs: {
				name: 'gatherpress_datetime_format',
				value: null,
			},
		};

		const { container } = render( <DateTimePreview { ...props } /> );

		expect( container.textContent ).toBe( '' );
	} );

	it( 'handles different date format patterns', () => {
		const testCases = [
			{ value: 'Y-m-d', expected: 'Formatted: Y-m-d' },
			{ value: 'F j, Y', expected: 'Formatted: F j, Y' },
			{ value: 'l, F j, Y g:i a', expected: 'Formatted: l, F j, Y g:i a' },
			{ value: 'd/m/Y H:i', expected: 'Formatted: d/m/Y H:i' },
		];

		testCases.forEach( ( testCase ) => {
			format.mockClear();
			const props = {
				attrs: {
					name: 'gatherpress_datetime_format',
					value: testCase.value,
				},
			};

			const { container } = render( <DateTimePreview { ...props } /> );

			expect( format ).toHaveBeenCalledWith( testCase.value );
			expect( container.textContent ).toBe( testCase.expected );
		} );
	} );

	it( 'extracts name and value from props.attrs', () => {
		const props = {
			attrs: {
				name: 'custom_name',
				value: 'Y-m-d',
				otherProp: 'should be ignored',
			},
		};

		render( <DateTimePreview { ...props } /> );

		expect( document.querySelector ).toHaveBeenCalledWith(
			'[name="custom_name"]'
		);
		expect( format ).toHaveBeenCalledWith( 'Y-m-d' );
	} );
} );
