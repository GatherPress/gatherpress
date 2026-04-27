/**
 * WordPress dependencies.
 */
import { useDebounce } from '@wordpress/compose';
import {
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import {
	fetchAddressSuggestions,
	getAddressSearchMinQueryLength,
	primeGeocodeCache,
} from '../../helpers/geocoding';

/**
 * Returns whether the suggestion UI (list, loading, or error) should be visible.
 *
 * @param {string}  value                - Current input value.
 * @param {Array}   suggestions          - Loaded suggestions.
 * @param {boolean} isLoadingSuggestions - Whether a fetch is in flight.
 * @param {string}  [suggestionError]    - Error message when the last fetch failed.
 *
 * @return {boolean} True when the UI should show.
 */
export function shouldShowAddressSuggestionUi(
	value,
	suggestions,
	isLoadingSuggestions,
	suggestionError
) {
	const meetsMinLength =
		getAddressSearchMinQueryLength() <=
		String( value || '' ).trim().length;

	return (
		0 < suggestions.length ||
		( isLoadingSuggestions && meetsMinLength ) ||
		( Boolean( suggestionError ) && meetsMinLength )
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
	const [ suggestionError, setSuggestionError ] = useState( '' );
	const abortControllerRef = useRef( null );

	useEffect( () => {
		setActiveIndex( 0 );
	}, [ suggestions ] );

	// Abort any in-flight suggestion request on unmount.
	useEffect( () => {
		return () => {
			if ( abortControllerRef.current ) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}
		};
	}, [] );

	const loadSuggestions = useDebounce(
		useCallback( ( query ) => {
			// Supersede any in-flight request so stale responses cannot clobber newer ones.
			if ( abortControllerRef.current ) {
				abortControllerRef.current.abort();
				abortControllerRef.current = null;
			}

			if (
				! query ||
				getAddressSearchMinQueryLength() > query.trim().length
			) {
				setSuggestions( [] );
				setSuggestionError( '' );
				setIsLoadingSuggestions( false );
				return;
			}

			const controller = new AbortController();
			abortControllerRef.current = controller;
			setIsLoadingSuggestions( true );
			setSuggestionError( '' );

			fetchAddressSuggestions( query, { signal: controller.signal } )
				.then( ( items ) => {
					if ( controller.signal.aborted ) {
						return;
					}
					setSuggestions( items );
				} )
				.catch( ( error ) => {
					if ( 'AbortError' === error?.name ) {
						return;
					}
					// Surface a generic, non-alarming message; low-level details go to the console via apiFetch.
					setSuggestions( [] );
					setSuggestionError(
						__(
							'Address suggestions are temporarily unavailable. Try again in a moment, or enter the address manually.',
							'gatherpress'
						)
					);
				} )
				.finally( () => {
					if ( controller.signal.aborted ) {
						return;
					}
					setIsLoadingSuggestions( false );
					if ( abortControllerRef.current === controller ) {
						abortControllerRef.current = null;
					}
				} );
			// Everything this callback closes over is stable: `abortControllerRef` is a ref,
			// the state setters are stable by React guarantee, and `fetchAddressSuggestions` /
			// `getAddressSearchMinQueryLength` / `__` are module-level imports. If you add a
			// prop or non-setter state reference to this body, drop the disable and let the
			// exhaustive-deps rule flag it rather than silently going stale.
			// eslint-disable-next-line react-hooks/exhaustive-deps
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
		if ( abortControllerRef.current ) {
			abortControllerRef.current.abort();
			abortControllerRef.current = null;
		}
		setSuggestions( [] );
		setSuggestionError( '' );
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
		suggestionError,
		handleChange,
		closeSuggestions,
		selectSuggestion,
		handleKeyDown,
	};
}
