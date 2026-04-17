/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import PhoneField from '@src/blocks/venue-detail/fields/phone-field';

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

	it( 'always renders as an anchor so the contenteditable element does not remount on first keystroke', () => {
		const { rerender } = render( <PhoneField { ...defaultProps } /> );
		expect( screen.getByTestId( 'rich-text' ).tagName.toLowerCase() ).toBe(
			'a'
		);

		rerender( <PhoneField { ...defaultProps } value="555-1234" /> );
		expect( screen.getByTestId( 'rich-text' ).tagName.toLowerCase() ).toBe(
			'a'
		);
	} );

	it( 'uses a placeholder href when empty and a tel: href when populated', () => {
		const { rerender } = render( <PhoneField { ...defaultProps } /> );
		expect( screen.getByTestId( 'rich-text' ).getAttribute( 'href' ) ).toBe(
			'#'
		);

		rerender( <PhoneField { ...defaultProps } value="555-1234" /> );
		expect( screen.getByTestId( 'rich-text' ).getAttribute( 'href' ) ).toBe(
			'tel:555-1234'
		);
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
