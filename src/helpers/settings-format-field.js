/**
 * Put a format picked from the list into the Custom field.
 *
 * The preview follows the Custom field's input events, so one is sent for it.
 * It is marked as filled in, so it doesn't count as typing and pick Custom.
 *
 * @since TBD
 *
 * @param {Object}           elements        The format field elements.
 * @param {HTMLInputElement} elements.custom The Custom field.
 * @param {HTMLInputElement} radio           The picked radio.
 *
 * @return {void}
 */
export function fillCustomField( elements, radio ) {
	elements.custom.value = radio.value;
	elements.filling = true;
	elements.custom.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	elements.filling = false;
}

/**
 * Wire up the Date Format and Time Format fields the way Settings > General works.
 *
 * Picking a format fills the Custom field and the preview with it, and
 * clicking or typing in the Custom field picks Custom. Does nothing on pages
 * without a format field.
 *
 * @since TBD
 *
 * @return {void}
 */
export function initFormatFields() {
	document
		.querySelectorAll( '[data-gatherpress_component_name="format-field"]' )
		.forEach( ( fieldset ) => {
			const customRadio = fieldset.querySelector(
				'.gatherpress-format-field__custom-radio',
			);
			const elements = {
				custom: fieldset.querySelector( '.gatherpress-format-field__custom' ),
				filling: false,
			};

			fieldset
				.querySelectorAll( 'input[type="radio"]' )
				.forEach( ( radio ) => {
					if ( radio !== customRadio ) {
						radio.addEventListener( 'change', () =>
							fillCustomField( elements, radio ),
						);
					}
				} );

			[ 'click', 'input' ].forEach( ( type ) =>
				elements.custom.addEventListener( type, () => {
					if ( ! elements.filling ) {
						customRadio.checked = true;
					}
				} ),
			);
		} );
}
