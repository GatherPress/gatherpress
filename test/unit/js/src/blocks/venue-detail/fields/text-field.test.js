/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import TextField from '../../../../../../../src/blocks/venue-detail/fields/text-field';

// Mock RichText component.
jest.mock( '@wordpress/block-editor', () => ( {
	RichText: ( { tagName: Tag, value, placeholder, className } ) => (
		<Tag
			data-testid="rich-text"
			className={ className }
			data-value={ value }
			data-placeholder={ placeholder }
		>
			{ value || placeholder }
		</Tag>
	),
} ) );

describe( 'TextField', () => {
	const defaultProps = {
		value: '',
		onChange: jest.fn(),
		placeholder: 'Enter text…',
		onKeyDown: jest.fn(),
	};

	it( 'renders with div tag', () => {
		render( <TextField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'div' );
	} );

	it( 'has correct class name', () => {
		render( <TextField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.className ).toBe( 'gatherpress-venue-detail__text' );
	} );

	it( 'displays the value when provided', () => {
		render( <TextField { ...defaultProps } value="Some text" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-value' ) ).toBe( 'Some text' );
	} );

	it( 'displays placeholder when no value', () => {
		render( <TextField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-placeholder' ) ).toBe(
			'Enter text…'
		);
	} );
} );
