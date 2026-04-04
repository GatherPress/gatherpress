/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Popover, Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies.
 */
import {
	fetchAddressSuggestions,
	primeGeocodeCache,
} from '../../../helpers/geocoding';

/**
 * Address field for venue details in the block editor.
 *
 * Uses a textarea (wraps long lines; auto-grows) with Nominatim suggestions in a Popover
 * so autocomplete works reliably in the canvas.
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.value       - The current field value.
 * @param {Function} props.onChange    - Callback when value changes.
 * @param {string}   props.placeholder - Placeholder text.
 * @param {Function} props.onKeyDown   - Keyboard handler (e.g. block insertion on Enter).
 * @param {boolean}  props.disabled    - Whether the field is disabled.
 * @return {JSX.Element} The rendered address field.
 */
const AddressField = ( {
	value,
	onChange,
	placeholder,
	onKeyDown,
	disabled,
} ) => {
	const baseClass = 'gatherpress-venue-detail__address';
	const inputRef = useRef( null );
	const [ listboxId ] = useState(
		() =>
			`gp-venue-address-suggestions-${ Math.random()
				.toString( 36 )
				.slice( 2, 11 ) }`
	);

	const [ suggestions, setSuggestions ] = useState( [] );
	const [ activeIndex, setActiveIndex ] = useState( 0 );
	const [ isLoadingSuggestions, setIsLoadingSuggestions ] = useState(
		false
	);
	const [ fieldUid ] = useState(
		() => `gp-vdetail-addr-${ Math.random().toString( 36 ).slice( 2, 12 ) }`
	);
	const [ suppressNativeAutofill, setSuppressNativeAutofill ] = useState(
		() => ! ( value && String( value ).trim() )
	);

	const showSuggestionUi =
		0 < suggestions.length ||
		( isLoadingSuggestions && 3 <= String( value || '' ).trim().length );

	useEffect( () => {
		if ( value && String( value ).trim() ) {
			setSuppressNativeAutofill( false );
		}
	}, [ value ] );

	useEffect( () => {
		setActiveIndex( 0 );
	}, [ suggestions ] );

	useEffect( () => {
		const el = inputRef.current;
		if ( ! el ) {
			return;
		}
		el.setAttribute( 'autocomplete', 'off' );
		el.setAttribute( 'data-lpignore', 'true' );
		el.setAttribute( 'data-1p-ignore', 'true' );
		el.setAttribute( 'data-bwignore', 'true' );
		el.setAttribute( 'data-form-type', 'other' );
	}, [] );

	const adjustTextareaHeight = useCallback( () => {
		const el = inputRef.current;
		if ( ! el || 'TEXTAREA' !== el.nodeName ) {
			return;
		}
		const maxPx = 220;
		el.style.height = 'auto';
		el.style.height = `${ Math.min( el.scrollHeight, maxPx ) }px`;
	}, [] );

	useLayoutEffect( () => {
		adjustTextareaHeight();
	}, [ value, adjustTextareaHeight ] );

	useEffect( () => {
		const el = inputRef.current;
		if ( ! el || 'undefined' === typeof ResizeObserver ) {
			return;
		}
		const observer = new ResizeObserver( () => {
			adjustTextareaHeight();
		} );
		observer.observe( el );
		return () => observer.disconnect();
	}, [ adjustTextareaHeight ] );

	const unlockAddressInput = useCallback( () => {
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				setSuppressNativeAutofill( false );
			} );
		} );
	}, [] );

	const loadSuggestions = useDebounce(
		useCallback( ( query ) => {
			if ( ! query || 3 > query.trim().length ) {
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
		400
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

	if ( disabled ) {
		return (
			<address className={ baseClass }>
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholder }
				</span>
			</address>
		);
	}

	return (
		<div className="gatherpress-venue-detail__address-wrap">
			<address className={ baseClass }>
				<textarea
					ref={ inputRef }
					id={ fieldUid }
					name={ fieldUid }
					className={ `${ baseClass }-input` }
					rows={ 1 }
					value={ value }
					onChange={ ( e ) => handleChange( e.target.value ) }
					onKeyDown={ handleKeyDown }
					onFocus={ unlockAddressInput }
					onMouseDown={ unlockAddressInput }
					placeholder={ placeholder }
					readOnly={ suppressNativeAutofill }
					aria-label={ __( 'Venue address', 'gatherpress' ) }
					aria-autocomplete="list"
				/>
			</address>
			{ showSuggestionUi && inputRef.current && (
				<Popover
					anchor={ inputRef.current }
					placement="bottom-start"
					shift
					focusOnMount={ false }
					onClose={ closeSuggestions }
					className="gatherpress-venue-detail__address-popover"
				>
					{ isLoadingSuggestions && 0 === suggestions.length && (
						<div className="gatherpress-venue-detail__address-popover-inner">
							<Spinner />
							<span className="gatherpress-venue-detail__address-loading">
								{ __( 'Searching for addresses…', 'gatherpress' ) }
							</span>
						</div>
					) }
					{ 0 < suggestions.length && (
						<ul
							id={ listboxId }
							className="gatherpress-venue-detail__address-suggestions"
							role="listbox"
							aria-label={ __( 'Address suggestions', 'gatherpress' ) }
						>
							{ suggestions.map( ( item, index ) => (
								<li
									key={ `${ item.label }-${ index }` }
									role="none"
								>
									<button
										type="button"
										role="option"
										tabIndex={ -1 }
										aria-selected={ activeIndex === index }
										className={
											activeIndex === index
												? 'gatherpress-venue-detail__address-suggestion is-active'
												: 'gatherpress-venue-detail__address-suggestion'
										}
										onClick={ () => selectSuggestion( item ) }
										onMouseEnter={ () =>
											setActiveIndex( index )
										}
									>
										{ item.label }
									</button>
								</li>
							) ) }
						</ul>
					) }
				</Popover>
			) }
		</div>
	);
};

export default AddressField;
