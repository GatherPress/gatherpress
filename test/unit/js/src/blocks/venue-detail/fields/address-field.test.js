/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';

/**
 * Internal dependencies.
 */
import { useEffect, useState } from '@wordpress/element';
import AddressField from '@src/blocks/venue-detail/fields/address-field';
import AddressAutocompleteField from '@src/components/AddressAutocompleteField';
import {
	fetchAddressSuggestions,
	primeGeocodeCache,
} from '@src/helpers/geocoding';

jest.mock( '@src/helpers/geocoding', () => {
	const actual = jest.requireActual( '@src/helpers/geocoding' );
	return {
		...actual,
		fetchAddressSuggestions: jest.fn().mockResolvedValue( [] ),
		primeGeocodeCache: jest.fn(),
	};
} );

jest.mock( '@wordpress/components', () => {
	const components = jest.requireActual( '@wordpress/components' );
	return {
		...components,
		Popover: ( { children, onClose } ) => (
			<div data-testid="address-popover">
				<button
					type="button"
					data-testid="popover-close"
					onClick={ () => onClose && onClose() }
				>
					close
				</button>
				{ children }
			</div>
		),
	};
} );

jest.mock( '@wordpress/compose', () => {
	const compose = jest.requireActual( '@wordpress/compose' );
	return {
		...compose,
		useDebounce: ( fn ) => fn,
	};
} );

/**
 * Mirrors a parent-controlled field so fireEvent.change updates `value` and suggestion UI can appear.
 *
 * @param {Object} props Props: initialValue, onChange, and remaining AddressField props.
 */
function ControlledAddressField( props ) {
	const { initialValue = '', onChange, ...rest } = props;
	const [ value, setValue ] = useState( initialValue );

	useEffect( () => {
		setValue( initialValue );
	}, [ initialValue ] );

	return (
		<AddressField
			{ ...rest }
			value={ value }
			onChange={ ( v ) => {
				setValue( v );
				onChange( v );
			} }
		/>
	);
}

describe( 'AddressField', () => {
	let defaultProps;
	let resizeObserverCallback;

	beforeEach( () => {
		defaultProps = {
			initialValue: '',
			onChange: jest.fn(),
			placeholder: 'Enter address…',
			onKeyDown: jest.fn(),
		};
		resizeObserverCallback = null;
		jest.clearAllMocks();
		global.requestAnimationFrame = jest.fn( ( cb ) => {
			cb();
			return 0;
		} );
		global.ResizeObserver = class ResizeObserver {
			constructor( callback ) {
				resizeObserverCallback = callback;
			}
			observe() {}
			disconnect() {}
		};
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	const flushMicrotasks = async () => {
		await act( async () => {
			await Promise.resolve();
		} );
	};

	it( 'renders textarea inside address with correct classes', () => {
		render( <ControlledAddressField { ...defaultProps } /> );

		const address = screen.getByRole( 'textbox' ).closest( 'address' );
		expect( address ).toBeTruthy();
		expect( address.className ).toBe(
			'gatherpress-venue-detail__address'
		);

		const field = screen.getByRole( 'textbox' );
		expect( field.className ).toBe(
			'gatherpress-venue-detail__address-input'
		);
	} );

	it( 'does not set inline display on address (layout from editor styles)', () => {
		render( <ControlledAddressField { ...defaultProps } /> );

		const address = screen.getByRole( 'textbox' ).closest( 'address' );
		expect( address.style.display ).toBe( '' );
	} );

	it( 'displays the value when provided', () => {
		render(
			<ControlledAddressField
				{ ...defaultProps }
				initialValue="123 Main St"
			/>
		);

		const field = screen.getByRole( 'textbox' );
		expect( field.value ).toBe( '123 Main St' );
	} );

	it( 'displays placeholder when no value', () => {
		render( <ControlledAddressField { ...defaultProps } /> );

		const field = screen.getByRole( 'textbox' );
		expect( field.getAttribute( 'placeholder' ) ).toBe(
			'Enter address…'
		);
	} );

	it( 'renders non-editable placeholder when disabled', () => {
		render(
			<AddressField
				value=""
				onChange={ defaultProps.onChange }
				placeholder={ defaultProps.placeholder }
				onKeyDown={ defaultProps.onKeyDown }
				disabled={ true }
			/>
		);

		expect( screen.queryByRole( 'textbox' ) ).toBeNull();

		const placeholder = screen.getByText( 'Enter address…' );
		expect( placeholder ).toBeTruthy();
		expect( placeholder.className ).toBe(
			'wp-block-gatherpress-venue-detail__placeholder'
		);
	} );

	it( 'sets anti-autofill attributes on the textarea after mount', () => {
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );
		expect( field.getAttribute( 'autocomplete' ) ).toBe( 'off' );
		expect( field.getAttribute( 'data-lpignore' ) ).toBe( 'true' );
	} );

	it( 'starts readOnly when empty and clears after non-empty value', () => {
		const { rerender } = render( <ControlledAddressField { ...defaultProps } /> );
		let field = screen.getByRole( 'textbox' );
		expect( field.readOnly ).toBe( true );

		rerender(
			<ControlledAddressField
				{ ...defaultProps }
				initialValue="Something"
			/>
		);
		field = screen.getByRole( 'textbox' );
		expect( field.readOnly ).toBe( false );
	} );

	it( 'clears readOnly after focus via unlock (double rAF)', () => {
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );
		expect( field.readOnly ).toBe( true );

		fireEvent.focus( field );

		expect( field.readOnly ).toBe( false );
	} );

	it( 'clears readOnly on mouseDown', () => {
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );
		fireEvent.mouseDown( field );
		expect( field.readOnly ).toBe( false );
	} );

	it( 'does not call fetch for queries shorter than 3 characters after debounce', async () => {
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'ab' } } );
		await flushMicrotasks();

		expect( fetchAddressSuggestions ).not.toHaveBeenCalled();
	} );

	it( 'calls fetchAddressSuggestions after debounce when query is long enough', async () => {
		fetchAddressSuggestions.mockResolvedValue( [] );
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, {
			target: { value: '123 Main Street' },
		} );
		await flushMicrotasks();

		await waitFor( () => {
			expect( fetchAddressSuggestions ).toHaveBeenCalledWith(
				'123 Main Street'
			);
		} );
	} );

	it( 'clears suggestions when fetch rejects', async () => {
		fetchAddressSuggestions.mockRejectedValueOnce( new Error( 'fail' ) );
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'Paris Fra' } } );
		await flushMicrotasks();

		await waitFor( () => {
			expect( fetchAddressSuggestions ).toHaveBeenCalled();
		} );
	} );

	it( 'shows loading state then suggestions', async () => {
		let resolveFetch;
		fetchAddressSuggestions.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveFetch = resolve;
				} )
		);

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'NYC query' } } );
		await flushMicrotasks();

		await waitFor( () => {
			expect(
				screen.getByText( 'Searching for addresses…' )
			).toBeTruthy();
		} );

		await act( async () => {
			resolveFetch( [
				{
					label: 'New York, NY',
					latitude: '40',
					longitude: '-74',
				},
			] );
		} );

		await waitFor( () => {
			expect(
				screen.getByRole( 'option', { name: 'New York, NY' } )
			).toBeTruthy();
		} );
	} );

	it( 'closes suggestions on Escape while loading', async () => {
		let resolveFetch;
		fetchAddressSuggestions.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveFetch = resolve;
				} )
		);

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'Wait for it' } } );
		await flushMicrotasks();

		await waitFor( () => {
			expect(
				screen.getByText( 'Searching for addresses…' )
			).toBeTruthy();
		} );

		fireEvent.keyDown( field, { key: 'Escape', code: 'Escape' } );

		await act( async () => {
			resolveFetch( [] );
		} );

		await waitFor( () => {
			expect(
				screen.queryByText( 'Searching for addresses…' )
			).toBeNull();
		} );
	} );

	it( 'navigates suggestions with arrow keys and selects with Enter', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'First St',
				latitude: '1',
				longitude: '2',
			},
			{
				label: 'Second Ave',
				latitude: '3',
				longitude: '4',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'Find me' } } );
		await flushMicrotasks();

		const opt1 = await screen.findByRole( 'option', {
			name: 'First St',
		} );
		const opt2 = await screen.findByRole( 'option', {
			name: 'Second Ave',
		} );

		expect( opt1.getAttribute( 'aria-selected' ) ).toBe( 'true' );
		expect( opt2.getAttribute( 'aria-selected' ) ).toBe( 'false' );

		fireEvent.keyDown( field, { key: 'ArrowDown', code: 'ArrowDown' } );
		expect( opt2.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.keyDown( field, { key: 'ArrowUp', code: 'ArrowUp' } );
		expect( opt1.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.keyDown( field, { key: 'ArrowUp', code: 'ArrowUp' } );
		expect( opt2.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.keyDown( field, { key: 'Enter', code: 'Enter', shiftKey: false } );

		expect( primeGeocodeCache ).toHaveBeenCalledWith(
			'Second Ave',
			'3',
			'4'
		);
		expect( defaultProps.onChange ).toHaveBeenCalledWith(
			'Second Ave'
		);
	} );

	it( 'does not select on Enter when Shift is held and forwards to onKeyDown', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'Only',
				latitude: '1',
				longitude: '2',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'query here' } } );
		await flushMicrotasks();

		await screen.findByRole( 'option', { name: 'Only' } );

		fireEvent.keyDown( field, {
			key: 'Enter',
			code: 'Enter',
			shiftKey: true,
		} );

		expect( primeGeocodeCache ).not.toHaveBeenCalled();
		expect( defaultProps.onKeyDown ).toHaveBeenCalled();
	} );

	it( 'calls onKeyDown when list is empty', () => {
		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.keyDown( field, { key: 'x', code: 'KeyX' } );

		expect( defaultProps.onKeyDown ).toHaveBeenCalled();
	} );

	it( 'selects suggestion on button click', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'Click Me St',
				latitude: '9',
				longitude: '8',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'click test' } } );
		await flushMicrotasks();

		const btn = await screen.findByRole( 'option', {
			name: 'Click Me St',
		} );
		fireEvent.click( btn );

		expect( primeGeocodeCache ).toHaveBeenCalledWith(
			'Click Me St',
			'9',
			'8'
		);
		expect( defaultProps.onChange ).toHaveBeenCalledWith( 'Click Me St' );
	} );

	it( 'updates active option on mouse enter', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'A',
				latitude: '1',
				longitude: '1',
			},
			{
				label: 'B',
				latitude: '2',
				longitude: '2',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'multi opt' } } );
		await flushMicrotasks();

		const optA = await screen.findByRole( 'option', { name: 'A' } );
		const optB = await screen.findByRole( 'option', { name: 'B' } );

		expect( optA.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.mouseEnter( optB );
		expect( optB.getAttribute( 'aria-selected' ) ).toBe( 'true' );
	} );

	it( 'closes suggestions when popover close is triggered', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'Close test',
				latitude: '1',
				longitude: '1',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'close me' } } );
		await flushMicrotasks();

		await screen.findByRole( 'option', { name: 'Close test' } );

		fireEvent.click( screen.getByTestId( 'popover-close' ) );

		expect(
			screen.queryByRole( 'option', { name: 'Close test' } )
		).toBeNull();
	} );

	it( 'runs ResizeObserver callback to adjust textarea height', async () => {
		render(
			<ControlledAddressField { ...defaultProps } initialValue="hello" />
		);
		const field = screen.getByRole( 'textbox' );

		await waitFor( () => {
			expect( resizeObserverCallback ).toEqual( expect.any( Function ) );
		} );

		act( () => {
			resizeObserverCallback();
		} );

		expect( field ).toBeTruthy();
	} );

	it( 'closes suggestions on Escape when the list is open', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'Escape City',
				latitude: '1',
				longitude: '1',
			},
		] );

		render( <ControlledAddressField { ...defaultProps } /> );
		const field = screen.getByRole( 'textbox' );

		fireEvent.change( field, { target: { value: 'Escape query' } } );
		await flushMicrotasks();

		await screen.findByRole( 'option', { name: 'Escape City' } );

		fireEvent.keyDown( field, { key: 'Escape', code: 'Escape' } );

		expect(
			screen.queryByRole( 'option', { name: 'Escape City' } )
		).toBeNull();
	} );
} );

/**
 * @param {Object} props Props.
 */
function ControlledSettingsAddressField( props ) {
	const { initialValue = '', onChange, ...rest } = props;
	const [ value, setValue ] = useState( initialValue );

	useEffect( () => {
		setValue( initialValue );
	}, [ initialValue ] );

	return (
		<AddressAutocompleteField
			variant="settings"
			{ ...rest }
			value={ value }
			onChange={ ( v ) => {
				setValue( v );
				onChange( v );
			} }
		/>
	);
}

describe( 'AddressAutocompleteField (settings variant)', () => {
	let settingsProps;

	beforeEach( () => {
		settingsProps = {
			initialValue: '',
			onChange: jest.fn(),
			help: 'Help text for address.',
		};
		jest.clearAllMocks();
		global.requestAnimationFrame = jest.fn( ( cb ) => {
			cb();
			return 0;
		} );
	} );

	const flushMicrotasks = async () => {
		await act( async () => {
			await Promise.resolve();
		} );
	};

	it( 'renders labeled search field', () => {
		render( <ControlledSettingsAddressField { ...settingsProps } /> );

		expect(
			screen.getByRole( 'searchbox', { name: /full address/i } )
		).toBeTruthy();
	} );

	it( 'navigates suggestions with arrow keys and selects with Enter', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'First St',
				latitude: '1',
				longitude: '2',
			},
			{
				label: 'Second Ave',
				latitude: '3',
				longitude: '4',
			},
		] );

		render( <ControlledSettingsAddressField { ...settingsProps } /> );
		const field = screen.getByRole( 'searchbox', { name: /full address/i } );

		fireEvent.change( field, { target: { value: 'Find me' } } );
		await flushMicrotasks();

		const opt1 = await screen.findByRole( 'option', {
			name: 'First St',
		} );
		const opt2 = await screen.findByRole( 'option', {
			name: 'Second Ave',
		} );

		expect( opt1.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.keyDown( field, { key: 'ArrowDown', code: 'ArrowDown' } );
		expect( opt2.getAttribute( 'aria-selected' ) ).toBe( 'true' );

		fireEvent.keyDown( field, { key: 'Enter', code: 'Enter', shiftKey: false } );

		expect( primeGeocodeCache ).toHaveBeenCalledWith(
			'Second Ave',
			'3',
			'4'
		);
		expect( settingsProps.onChange ).toHaveBeenCalledWith( 'Second Ave' );
	} );

	it( 'closes suggestions on Escape when list is open', async () => {
		fetchAddressSuggestions.mockResolvedValue( [
			{
				label: 'Escape City',
				latitude: '1',
				longitude: '1',
			},
		] );

		render( <ControlledSettingsAddressField { ...settingsProps } /> );
		const field = screen.getByRole( 'searchbox', { name: /full address/i } );

		fireEvent.change( field, { target: { value: 'Escape query' } } );
		await flushMicrotasks();

		await screen.findByRole( 'option', { name: 'Escape City' } );

		fireEvent.keyDown( field, { key: 'Escape', code: 'Escape' } );

		expect(
			screen.queryByRole( 'option', { name: 'Escape City' } )
		).toBeNull();
	} );

	it( 'shows loading state then suggestions', async () => {
		let resolveFetch;
		fetchAddressSuggestions.mockImplementation(
			() =>
				new Promise( ( resolve ) => {
					resolveFetch = resolve;
				} )
		);

		render( <ControlledSettingsAddressField { ...settingsProps } /> );
		const field = screen.getByRole( 'searchbox', { name: /full address/i } );

		fireEvent.change( field, { target: { value: 'NYC query' } } );
		await flushMicrotasks();

		await waitFor( () => {
			expect(
				screen.getByText( 'Searching for addresses…' )
			).toBeTruthy();
		} );

		await act( async () => {
			resolveFetch( [
				{
					label: 'New York, NY',
					latitude: '40',
					longitude: '-74',
				},
			] );
		} );

		await waitFor( () => {
			expect(
				screen.getByRole( 'option', { name: 'New York, NY' } )
			).toBeTruthy();
		} );
	} );
} );
