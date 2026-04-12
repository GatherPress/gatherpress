/**
 * WordPress dependencies.
 */
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import {
	ADDRESS_SEARCH_MIN_QUERY_LENGTH,
	fetchAddressSuggestions,
	primeGeocodeCache,
} from '../helpers/geocoding';

/**
 * Returns whether the suggestion UI (list or loading) should be visible.
 *
 * @param {string}  value                - Current input value.
 * @param {Array}   suggestions          - Loaded suggestions.
 * @param {boolean} isLoadingSuggestions - Whether a fetch is in flight.
 *
 * @return {boolean} True when the UI should show.
 */
export function shouldShowAddressSuggestionUi(
	value,
	suggestions,
	isLoadingSuggestions
) {
	return (
		0 < suggestions.length ||
		( isLoadingSuggestions &&
			ADDRESS_SEARCH_MIN_QUERY_LENGTH <=
				String( value || '' ).trim().length )
	);
}

/**
 * Shared state and handlers for Photon-backed address autocomplete (editor + settings UI).
 *
 * @param {Object}   options                  - Options.
 * @param {Function} options.onChange         - Called with the full address string when the value or selection changes.
 * @param {Function} [options.onKeyDown]      - Optional key handler after autocomplete handles list keys.
 * @param {number}   [options.debounceMs=400] - Debounce for search requests.
 *
 * @return {Object} Hook API for wiring inputs and suggestion lists.
 */
export function useAddressAutocomplete( {
	onChange,
	onKeyDown,
	debounceMs = 400,
} ) {
	const [ suggestions, setSuggestions ] = useState( [] );
	const [ activeIndex, setActiveIndex ] = useState( 0 );
	const [ isLoadingSuggestions, setIsLoadingSuggestions ] = useState( false );

	useEffect( () => {
		setActiveIndex( 0 );
	}, [ suggestions ] );

	const loadSuggestions = useDebounce(
		useCallback( ( query ) => {
			if (
				! query ||
				ADDRESS_SEARCH_MIN_QUERY_LENGTH > query.trim().length
			) {
				setSuggestions( [] );
				setIsLoadingSuggestions( false );
				return;
			}
			setIsLoadingSuggestions( true );
			fetchAddressSuggestions( query )
				.then( ( items ) => {
					setSuggestions( items );
				} )
				.catch( () => {
					setSuggestions( [] );
				} )
				.finally( () => {
					setIsLoadingSuggestions( false );
				} );
		}, [] ),
		debounceMs
	);

	const handleChange = useCallback(
		( next ) => {
			onChange( next );
			loadSuggestions( next );
		},
		[ onChange, loadSuggestions ]
	);

	const closeSuggestions = useCallback( () => {
		setSuggestions( [] );
		setIsLoadingSuggestions( false );
	}, [] );

	const selectSuggestion = useCallback(
		( item ) => {
			primeGeocodeCache( item.label, item.latitude, item.longitude );
			onChange( item.label );
			closeSuggestions();
		},
		[ onChange, closeSuggestions ]
	);

	const handleKeyDown = useCallback(
		( event ) => {
			const hasList = 0 < suggestions.length;

			if ( 'Escape' === event.key ) {
				if ( hasList || isLoadingSuggestions ) {
					event.preventDefault();
					closeSuggestions();
				}
				return;
			}

			if ( hasList ) {
				if ( 'ArrowDown' === event.key ) {
					event.preventDefault();
					setActiveIndex( ( i ) =>
						suggestions.length <= i + 1 ? 0 : i + 1
					);
					return;
				}
				if ( 'ArrowUp' === event.key ) {
					event.preventDefault();
					setActiveIndex( ( i ) =>
						0 > i - 1 ? suggestions.length - 1 : i - 1
					);
					return;
				}
				if ( 'Enter' === event.key && ! event.shiftKey ) {
					event.preventDefault();
					event.stopPropagation();
					const item = suggestions[ activeIndex ] ?? suggestions[ 0 ];
					if ( item ) {
						selectSuggestion( item );
					}
					return;
				}
			}

			if ( onKeyDown ) {
				onKeyDown( event );
			}
		},
		[
			activeIndex,
			closeSuggestions,
			isLoadingSuggestions,
			onKeyDown,
			selectSuggestion,
			suggestions,
		]
	);

	return {
		suggestions,
		activeIndex,
		setActiveIndex,
		isLoadingSuggestions,
		handleChange,
		closeSuggestions,
		selectSuggestion,
		handleKeyDown,
	};
}
