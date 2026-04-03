/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import AddressField from '@src/blocks/venue-detail/fields/address-field';

jest.mock( '@src/helpers/geocoding', () => ( {
	fetchAddressSuggestions: jest.fn().mockResolvedValue( [] ),
	primeGeocodeCache: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => {
	const components = jest.requireActual( '@wordpress/components' );
	return {
		...components,
		Popover: ( { children } ) => (
			<div data-testid="address-popover">{ children }</div>
		),
	};
} );

describe( 'AddressField', () => {
	const defaultProps = {
		value: '',
		onChange: jest.fn(),
		placeholder: 'Enter address…',
		onKeyDown: jest.fn(),
	};

	it( 'renders textarea inside address with correct classes', () => {
		render( <AddressField { ...defaultProps } /> );

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
		render( <AddressField { ...defaultProps } /> );

		const address = screen.getByRole( 'textbox' ).closest( 'address' );
		expect( address.style.display ).toBe( '' );
	} );

	it( 'displays the value when provided', () => {
		render(
			<AddressField { ...defaultProps } value="123 Main St" />
		);

		expect( screen.getByRole( 'textbox' ) ).toHaveValue( '123 Main St' );
	} );

	it( 'displays placeholder when no value', () => {
		render( <AddressField { ...defaultProps } /> );

		expect( screen.getByRole( 'textbox' ) ).toHaveAttribute(
			'placeholder',
			'Enter address…'
		);
	} );

	it( 'renders non-editable placeholder when disabled', () => {
		render( <AddressField { ...defaultProps } disabled={ true } /> );

		expect( screen.queryByRole( 'textbox' ) ).toBeNull();

		const placeholder = screen.getByText( 'Enter address…' );
		expect( placeholder ).toBeTruthy();
		expect( placeholder.className ).toBe(
			'wp-block-gatherpress-venue-detail__placeholder'
		);
	} );
} );
