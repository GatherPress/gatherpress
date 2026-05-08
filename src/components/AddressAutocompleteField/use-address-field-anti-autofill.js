/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';

/**
 * @param {string} value    - Current value.
 * @param {Object} inputRef - Ref to the input or textarea DOM node.
 * @return {Object} suppressNativeAutofill, unlockAddressInput.
 */
export function useAddressFieldAntiAutofill( value, inputRef ) {
	const [ suppressNativeAutofill, setSuppressNativeAutofill ] = useState(
		() => ! ( value && String( value ).trim() )
	);

	useEffect( () => {
		if ( value && String( value ).trim() ) {
			setSuppressNativeAutofill( false );
		}
	}, [ value ] );

	useEffect( () => {
		const el = inputRef.current;
		if ( ! el ) {
			return;
		}
		el.setAttribute( 'autocomplete', 'off' );
		el.dataset.lpignore = 'true';
		// `data-1p-ignore` exposes as `1pIgnore` in the dataset map, which
		// can't be reached via dot notation because the key starts with a digit.
		el.dataset[ '1pIgnore' ] = 'true';
		el.dataset.bwignore = 'true';
		el.dataset.formType = 'other';
		// eslint-disable-next-line react-hooks/exhaustive-deps -- run once; ref is stable.
	}, [] );

	const unlockAddressInput = useCallback( () => {
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				setSuppressNativeAutofill( false );
			} );
		} );
	}, [] );

	return { suppressNativeAutofill, unlockAddressInput };
}
