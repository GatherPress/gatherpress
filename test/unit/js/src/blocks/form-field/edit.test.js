/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render } from '@testing-library/react';

/**
 * Mocks
 */
jest.mock( '@wordpress/block-editor', () => ( {
	FontSizePicker: () => null,
	InspectorControls: ( { children } ) => <div>{ children }</div>,
	PanelColorSettings: () => null,
	RichText: ( { tagName: Tag = 'div', value, placeholder } ) => (
		<Tag>{ value || placeholder }</Tag>
	),
	useBlockProps: jest.fn( () => ( {} ) ),
} ) );

/**
 * Internal dependencies
 */
import Edit from '@src/blocks/form-field/edit';

const baseAttributes = {
	autocomplete: '',
	fieldName: 'name',
	fieldType: 'text',
	fieldValue: '',
	helpText: '',
	label: 'Name',
	placeholder: '',
	prefillCurrentUser: false,
	required: false,
};

const renderEdit = ( attributes = {}, setAttributes = jest.fn() ) =>
	render(
		<Edit
			attributes={ { ...baseAttributes, ...attributes } }
			setAttributes={ setAttributes }
			isSelected={ true }
		/>
	);

describe( 'Form Field Edit autocomplete help', () => {
	it( 'opens the autocomplete documentation in a new tab and says so', () => {
		const { getByRole } = renderEdit();
		const link = getByRole( 'link', { name: /Learn more/ } );

		expect( link.getAttribute( 'href' ) ).toBe(
			'https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete'
		);
		expect( link.getAttribute( 'target' ) ).toBe( '_blank' );
		expect( link.getAttribute( 'rel' ) ).toContain( 'noopener' );
		expect(
			getByRole( 'link', { name: /opens in a new tab/ } )
		).toBe( link );
	} );

	it( 'has no documentation link for a hidden field', () => {
		const { queryByRole } = renderEdit( { fieldType: 'hidden' } );

		expect( queryByRole( 'link', { name: /Learn more/ } ) ).toBeNull();
	} );
} );
