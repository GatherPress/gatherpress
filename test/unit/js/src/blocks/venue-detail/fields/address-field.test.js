/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import AddressField from '../../../../../../../src/blocks/venue-detail/fields/address-field';

// Mock RichText component.
jest.mock( '@wordpress/block-editor', () => ( {
	RichText: ( { tagName: Tag, value, placeholder, className, style } ) => (
		<Tag
			data-testid="rich-text"
			className={ className }
			style={ style }
			data-value={ value }
			data-placeholder={ placeholder }
		>
			{ value || placeholder }
		</Tag>
	),
} ) );

describe( 'AddressField', () => {
	const defaultProps = {
		value: '',
		onChange: jest.fn(),
		placeholder: 'Enter address…',
		onKeyDown: jest.fn(),
	};

	it( 'renders with address tag', () => {
		render( <AddressField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'address' );
	} );

	it( 'has correct class name', () => {
		render( <AddressField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.className ).toBe( 'gatherpress-venue-detail__address' );
	} );

	it( 'has inline display style', () => {
		render( <AddressField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.style.display ).toBe( 'inline' );
	} );

	it( 'displays the value when provided', () => {
		render( <AddressField { ...defaultProps } value="123 Main St" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-value' ) ).toBe( '123 Main St' );
	} );

	it( 'displays placeholder when no value', () => {
		render( <AddressField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-placeholder' ) ).toBe(
			'Enter address…'
		);
	} );
} );
