/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import PhoneField from '../../../../../../../src/blocks/venue-detail/fields/phone-field';

// Mock RichText component.
jest.mock( '@wordpress/block-editor', () => ( {
	RichText: ( {
		tagName: Tag,
		value,
		placeholder,
		className,
		href,
		onClick,
	} ) => (
		<Tag
			data-testid="rich-text"
			className={ className }
			href={ href }
			data-value={ value }
			data-placeholder={ placeholder }
			onClick={ onClick }
		>
			{ value || placeholder }
		</Tag>
	),
} ) );

describe( 'PhoneField', () => {
	const defaultProps = {
		value: '',
		onChange: jest.fn(),
		placeholder: 'Enter phone…',
		onKeyDown: jest.fn(),
	};

	it( 'renders as span when no value', () => {
		render( <PhoneField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'span' );
	} );

	it( 'renders as anchor when value exists', () => {
		render( <PhoneField { ...defaultProps } value="555-1234" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'a' );
	} );

	it( 'has tel: href when value exists', () => {
		render( <PhoneField { ...defaultProps } value="555-1234" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'href' ) ).toBe( 'tel:555-1234' );
	} );

	it( 'has correct class name', () => {
		render( <PhoneField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.className ).toBe( 'gatherpress-venue-detail__phone' );
	} );

	it( 'displays the value when provided', () => {
		render( <PhoneField { ...defaultProps } value="555-1234" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-value' ) ).toBe( '555-1234' );
	} );

	it( 'displays placeholder when no value', () => {
		render( <PhoneField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-placeholder' ) ).toBe(
			'Enter phone…'
		);
	} );

	it( 'renders non-editable placeholder when disabled', () => {
		render( <PhoneField { ...defaultProps } disabled={ true } /> );

		// Should not render RichText when disabled.
		expect( screen.queryByTestId( 'rich-text' ) ).toBeNull();

		// Should render static placeholder.
		const placeholder = screen.getByText( 'Enter phone…' );
		expect( placeholder ).toBeTruthy();
		expect( placeholder.className ).toBe(
			'wp-block-gatherpress-venue-detail__placeholder'
		);
	} );

	it( 'has onClick handler when value exists', () => {
		render( <PhoneField { ...defaultProps } value="555-1234" /> );

		const element = screen.getByTestId( 'rich-text' );

		// The element should have an onClick handler attached.
		expect( element.onclick ).toBeDefined();

		// Fire the click event to exercise the handler.
		fireEvent.click( element );
	} );
} );
