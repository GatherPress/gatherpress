/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	fillCustomField,
	initFormatFields,
} from '@src/helpers/settings-format-field';

/**
 * Render the format field markup the format field template outputs.
 *
 * @return {Object} The radios, the Custom radio and the Custom field.
 */
function renderField() {
	document.body.innerHTML = `
		<fieldset data-gatherpress_component_name="format-field">
			<input type="radio" name="f" value="F j, Y" checked />
			<input type="radio" name="f" value="Y-m-d" />
			<input type="radio" name="f" value="__custom__" class="gatherpress-format-field__custom-radio" />
			<input type="text" class="gatherpress-format-field__custom" value="F j, Y" />
		</fieldset>
	`;

	const radios = document.querySelectorAll( 'input[type="radio"]' );

	return {
		presets: [ radios[ 0 ], radios[ 1 ] ],
		customRadio: radios[ 2 ],
		custom: document.querySelector( '.gatherpress-format-field__custom' ),
	};
}

describe( 'fillCustomField', () => {
	it( 'puts the format in the Custom field and tells the preview', () => {
		const { presets, custom } = renderField();
		const elements = { custom, filling: false };
		const preview = jest.fn( () => {
			expect( elements.filling ).toBe( true );
		} );

		custom.addEventListener( 'input', preview );
		fillCustomField( elements, presets[ 1 ] );

		expect( custom.value ).toBe( 'Y-m-d' );
		expect( preview ).toHaveBeenCalledTimes( 1 );
		expect( elements.filling ).toBe( false );
	} );
} );

describe( 'initFormatFields', () => {
	it( 'does nothing on pages without a format field', () => {
		document.body.innerHTML = '<div></div>';

		expect( () => initFormatFields() ).not.toThrow();
	} );

	it( 'fills the Custom field from the picked format without picking Custom', () => {
		const { presets, customRadio, custom } = renderField();

		initFormatFields();
		presets[ 1 ].checked = true;
		presets[ 1 ].dispatchEvent( new Event( 'change' ) );

		expect( custom.value ).toBe( 'Y-m-d' );
		expect( presets[ 1 ].checked ).toBe( true );
		expect( customRadio.checked ).toBe( false );
	} );

	it.each( [ 'click', 'input' ] )(
		'picks Custom on %s in the Custom field',
		( type ) => {
			const { presets, customRadio, custom } = renderField();

			initFormatFields();
			custom.dispatchEvent( new Event( type ) );

			expect( customRadio.checked ).toBe( true );
			expect( presets[ 0 ].checked ).toBe( false );
		},
	);
} );
