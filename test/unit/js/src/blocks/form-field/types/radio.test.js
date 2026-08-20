/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';
import { render, fireEvent, act } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import RadioField from '@src/blocks/form-field/types/radio';

// Mock RichText with a focusable stand-in that keeps the `rich-text` class the
// option lookup selects on, and forwards the keydown the component listens for.
jest.mock( '@wordpress/block-editor', () => ( {
	RichText: ( { tagName: Tag, value, placeholder, className, onKeyDown } ) => (
		<Tag
			className={ [ 'rich-text', className ].filter( Boolean ).join( ' ' ) }
			tabIndex={ 0 }
			onKeyDown={ onKeyDown }
		>
			{ value || placeholder }
		</Tag>
	),
} ) );

/**
 * Renders a radio field that owns its attributes, so adding an option actually
 * re-renders the group the way it does in the editor.
 *
 * @param {Object} props           Component props.
 * @param {string} props.fieldName Field name, used to tell two blocks apart.
 * @param {Array}  props.options   Initial radio options.
 *
 * @return {JSX.Element} The stateful radio field.
 */
function StatefulRadioField( { fieldName, options } ) {
	const [ attributes, setAttributes ] = useState( {
		fieldType: 'radio',
		fieldName,
		fieldValue: '',
		label: 'Group',
		required: false,
		radioOptions: options,
	} );

	return (
		<RadioField
			attributes={ attributes }
			setAttributes={ ( update ) =>
				setAttributes( ( current ) => ( { ...current, ...update } ) )
			}
			blockProps={ { className: `block-${ fieldName }` } }
			generateFieldName={ () => '' }
		/>
	);
}

/**
 * Reads the editable option labels belonging to one radio block.
 *
 * @param {HTMLElement} block The block wrapper element.
 *
 * @return {HTMLElement[]} The option label elements, in document order.
 */
function optionsOf( block ) {
	return Array.from(
		block.querySelectorAll( '.gatherpress-radio-option .rich-text' ),
	);
}

describe( 'RadioField option focus', () => {
	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'focuses the new option in the block that was typed in, not the last one on the page', () => {
		const { container } = render(
			<>
				<StatefulRadioField
					fieldName="first"
					options={ [
						{ label: 'One', value: 'one', id: 'a' },
						{ label: 'Two', value: 'two', id: 'b' },
					] }
				/>
				<StatefulRadioField
					fieldName="second"
					options={ [
						{ label: 'Alpha', value: 'alpha', id: 'c' },
						{ label: 'Beta', value: 'beta', id: 'd' },
					] }
				/>
			</>,
		);

		const firstBlock = container.querySelector( '.block-first' );
		const secondBlock = container.querySelector( '.block-second' );

		// Enter at the end of the first block's first option adds an option there.
		fireEvent.keyDown( optionsOf( firstBlock )[ 0 ], { key: 'Enter' } );

		act( () => {
			jest.advanceTimersByTime( 50 );
		} );

		const firstBlockOptions = optionsOf( firstBlock );

		expect( firstBlockOptions ).toHaveLength( 3 );
		expect( optionsOf( secondBlock ) ).toHaveLength( 2 );

		// The caret belongs in the option just created, which is in this block.
		expect( document.activeElement ).toBe( firstBlockOptions[ 2 ] );
		expect( secondBlock.contains( document.activeElement ) ).toBe( false );
	} );

	it( 'focuses options inside the canvas document when the editor is iframed', () => {
		// WordPress 7.1 always iframes the post editor, so the block renders in
		// a document that is not the admin one. Focus must still resolve.
		const iframe = document.createElement( 'iframe' );

		document.body.appendChild( iframe );

		const canvas = iframe.contentDocument;
		const container = canvas.createElement( 'div' );

		canvas.body.appendChild( container );

		render(
			<StatefulRadioField
				fieldName="framed"
				options={ [ { label: 'One', value: 'one', id: 'a' } ] }
			/>,
			{ container, baseElement: canvas.body },
		);

		const block = canvas.querySelector( '.block-framed' );

		fireEvent.keyDown( optionsOf( block )[ 0 ], { key: 'Enter' } );

		act( () => {
			jest.advanceTimersByTime( 50 );
		} );

		const framedOptions = optionsOf( block );

		expect( framedOptions ).toHaveLength( 2 );
		expect( canvas.activeElement ).toBe( framedOptions[ 1 ] );

		iframe.remove();
	} );

	it( 'moves focus back a step when an empty option is removed', () => {
		const { container } = render(
			<StatefulRadioField
				fieldName="removal"
				options={ [
					{ label: 'One', value: 'one', id: 'a' },
					{ label: '', value: '', id: 'b' },
				] }
			/>,
		);

		const block = container.querySelector( '.block-removal' );

		// Backspace in the trailing empty option removes it.
		fireEvent.keyDown( optionsOf( block )[ 1 ], { key: 'Backspace' } );

		act( () => {
			jest.advanceTimersByTime( 50 );
		} );

		const remaining = optionsOf( block );

		expect( remaining ).toHaveLength( 1 );
		expect( document.activeElement ).toBe( remaining[ 0 ] );
	} );

	it( 'does not throw when the canvas loses its view before the caret is placed', () => {
		const { container } = render(
			<StatefulRadioField
				fieldName="noView"
				options={ [
					{ label: 'One', value: 'one', id: 'a' },
					{ label: '', value: '', id: 'b' },
				] }
			/>,
		);

		const block = container.querySelector( '.block-noView' );

		fireEvent.keyDown( optionsOf( block )[ 1 ], { key: 'Backspace' } );

		// A browser discards the browsing context when the canvas goes away
		// (Code Editor switch, device-preview toggle), which leaves the
		// document without a defaultView while the timer is still pending.
		const ownerDocument = block.ownerDocument;
		const view = ownerDocument.defaultView;

		Object.defineProperty( ownerDocument, 'defaultView', {
			configurable: true,
			value: null,
		} );

		expect( () => {
			act( () => {
				jest.advanceTimersByTime( 50 );
			} );
		} ).not.toThrow();

		Object.defineProperty( ownerDocument, 'defaultView', {
			configurable: true,
			value: view,
		} );
	} );
} );
