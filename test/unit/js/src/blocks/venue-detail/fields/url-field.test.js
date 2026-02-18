/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies.
 */
import UrlField from '../../../../../../../src/blocks/venue-detail/fields/url-field';

// Mock WordPress dependencies.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	BlockControls: ( { children } ) => (
		<div data-testid="block-controls">{ children }</div>
	),
	RichText: ( {
		tagName: Tag,
		value,
		placeholder,
		className,
		href,
		target,
		rel,
	} ) => (
		<Tag
			data-testid="rich-text"
			className={ className }
			href={ href }
			target={ target }
			rel={ rel }
			data-value={ value }
			data-placeholder={ placeholder }
		>
			{ value || placeholder }
		</Tag>
	),
} ) );

jest.mock( '@wordpress/components', () => {
	// Use require inside the factory to avoid out-of-scope variable issue.
	const { forwardRef } = require( '@wordpress/element' );

	return {
		Popover: ( { children } ) => (
			<div data-testid="popover">{ children }</div>
		),
		ToggleControl: ( { label, checked, onChange } ) => (
			// eslint-disable-next-line jsx-a11y/label-has-associated-control
			<label data-testid={ `toggle-${ label }` }>
				<input
					type="checkbox"
					checked={ checked }
					onChange={ ( e ) => onChange( e.target.checked ) }
				/>
				{ label }
			</label>
		),
		ToolbarButton: forwardRef( function MockToolbarButton(
			{ title, onClick, isPressed },
			ref
		) {
			return (
				<button
					ref={ ref }
					data-testid="toolbar-button"
					onClick={ onClick }
					data-pressed={ isPressed }
					title={ title }
				>
					{ title }
				</button>
			);
		} ),
		ToolbarGroup: ( { children } ) => (
			<div data-testid="toolbar-group">{ children }</div>
		),
	};
} );

jest.mock( '@wordpress/icons', () => ( {
	link: 'link-icon',
} ) );

// Mock the helpers.
jest.mock(
	'../../../../../../../src/blocks/venue-detail/helpers',
	() => ( {
		cleanUrlForDisplay: ( url ) => {
			if ( ! url ) {
				return '';
			}
			return url
				.replace( /^https?:\/\//, '' )
				.replace( /^www\./, '' )
				.replace( /\/$/, '' );
		},
	} )
);

describe( 'UrlField', () => {
	const defaultProps = {
		value: '',
		onChange: jest.fn(),
		placeholder: 'Venue website URL…',
		onKeyDown: jest.fn(),
		linkTarget: '_self',
		cleanUrl: false,
		setAttributes: jest.fn(),
	};

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders as span when no value', () => {
		render( <UrlField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'span' );
	} );

	it( 'renders as anchor when value exists', () => {
		render( <UrlField { ...defaultProps } value="https://example.com" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.tagName.toLowerCase() ).toBe( 'a' );
	} );

	it( 'has href when value exists', () => {
		render( <UrlField { ...defaultProps } value="https://example.com" /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'href' ) ).toBe( 'https://example.com' );
	} );

	it( 'has correct class name', () => {
		render( <UrlField { ...defaultProps } /> );

		const element = screen.getByTestId( 'rich-text' );
		expect( element.className ).toBe( 'gatherpress-venue-detail__url' );
	} );

	it( 'displays full URL when cleanUrl is false', () => {
		render(
			<UrlField
				{ ...defaultProps }
				value="https://www.example.com/"
				cleanUrl={ false }
			/>
		);

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'data-value' ) ).toBe(
			'https://www.example.com/'
		);
	} );

	it( 'renders BlockControls with toolbar button', () => {
		render( <UrlField { ...defaultProps } /> );

		expect( screen.getByTestId( 'block-controls' ) ).toBeTruthy();
		expect( screen.getByTestId( 'toolbar-button' ) ).toBeTruthy();
	} );

	it( 'opens popover when toolbar button clicked', () => {
		render( <UrlField { ...defaultProps } /> );

		// Initially no popover.
		expect( screen.queryByTestId( 'popover' ) ).toBeNull();

		// Click the button.
		fireEvent.click( screen.getByTestId( 'toolbar-button' ) );

		// Popover should appear.
		expect( screen.getByTestId( 'popover' ) ).toBeTruthy();
	} );

	it( 'sets target _blank when linkTarget is _blank', () => {
		render(
			<UrlField
				{ ...defaultProps }
				value="https://example.com"
				linkTarget="_blank"
			/>
		);

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( element.getAttribute( 'rel' ) ).toBe( 'noopener noreferrer' );
	} );

	it( 'does not set target when linkTarget is _self', () => {
		render(
			<UrlField
				{ ...defaultProps }
				value="https://example.com"
				linkTarget="_self"
			/>
		);

		const element = screen.getByTestId( 'rich-text' );
		expect( element.getAttribute( 'target' ) ).toBeNull();
	} );

	it( 'displays default placeholder when not provided', () => {
		render( <UrlField { ...defaultProps } placeholder="" /> );

		const element = screen.getByTestId( 'rich-text' );
		// Uses default 'Venue website URL…' placeholder.
		expect( element.getAttribute( 'data-placeholder' ) ).toBe( 'Venue website URL…' );
	} );
} );
