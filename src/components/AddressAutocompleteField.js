/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Popover, Spinner, TextControl } from '@wordpress/components';
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
import { useAddressFieldAntiAutofill } from '../hooks/use-address-field-anti-autofill';
import {
	shouldShowAddressSuggestionUi,
	useAddressAutocomplete,
} from '../hooks/use-address-autocomplete';

/**
 * Address field with Nominatim suggestions (block: textarea + canvas; settings: TextControl + form).
 *
 * @param {Object}             props               - Props.
 * @param {'block'|'settings'} props.variant       - Block canvas (textarea) vs. venue form (TextControl).
 * @param {string}             props.value         - Address value.
 * @param {Function}           props.onChange      - Value change handler.
 * @param {string}             [props.placeholder] - Block only.
 * @param {Function}           [props.onKeyDown]   - Block only (e.g. block insertion).
 * @param {boolean}            [props.disabled]    - Block only.
 * @param {string}             [props.help]        - Settings only.
 *
 * @return {JSX.Element} Field UI.
 */
export default function AddressAutocompleteField( {
	variant,
	value,
	onChange,
	placeholder,
	onKeyDown,
	disabled,
	help,
} ) {
	const inputRef = useRef( null );
	const [ listboxId ] = useState(
		() =>
			`gp-addr-suggest-${ Math.random().toString( 36 ).slice( 2, 11 ) }`
	);
	const [ fieldUid ] = useState(
		() => `gp-addr-${ Math.random().toString( 36 ).slice( 2, 12 ) }`
	);

	const { suppressNativeAutofill, unlockAddressInput } =
		useAddressFieldAntiAutofill( value, inputRef );

	const {
		suggestions,
		activeIndex,
		setActiveIndex,
		isLoadingSuggestions,
		handleChange,
		closeSuggestions,
		selectSuggestion,
		handleKeyDown,
	} = useAddressAutocomplete( {
		onChange,
		onKeyDown: 'block' === variant ? onKeyDown : undefined,
	} );

	const showSuggestionUi = shouldShowAddressSuggestionUi(
		value,
		suggestions,
		isLoadingSuggestions
	);
	const showSuggestionPanel = 0 < suggestions.length;

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
		if ( 'block' === variant ) {
			adjustTextareaHeight();
		}
	}, [ variant, value, adjustTextareaHeight ] );

	useEffect( () => {
		if ( 'block' !== variant ) {
			return;
		}
		const el = inputRef.current;
		if ( ! el || 'undefined' === typeof ResizeObserver ) {
			return;
		}
		const observer = new ResizeObserver( () => {
			adjustTextareaHeight();
		} );
		observer.observe( el );
		return () => observer.disconnect();
	}, [ variant, adjustTextareaHeight ] );

	const suggestionPopover =
		showSuggestionUi && inputRef.current ? (
			<Popover
				anchor={ inputRef.current }
				placement="bottom-start"
				shift
				focusOnMount={ false }
				onClose={ closeSuggestions }
				className="gatherpress-venue-detail__address-popover"
			>
				{ isLoadingSuggestions && ! showSuggestionPanel && (
					<div className="gatherpress-venue-detail__address-popover-inner">
						<Spinner />
						<span className="gatherpress-venue-detail__address-loading">
							{ __( 'Searching for addresses…', 'gatherpress' ) }
						</span>
					</div>
				) }
				{ showSuggestionPanel && (
					<ul
						id={ listboxId }
						className="gatherpress-venue-detail__address-suggestions"
						role="listbox"
						aria-label={ __( 'Address suggestions', 'gatherpress' ) }
					>
						{ suggestions.map( ( item, index ) => (
							<li key={ `${ item.label }-${ index }` } role="none">
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
									onMouseEnter={ () => setActiveIndex( index ) }
								>
									{ item.label }
								</button>
							</li>
						) ) }
					</ul>
				) }
			</Popover>
		) : null;

	if ( 'block' === variant && disabled ) {
		return (
			<address className="gatherpress-venue-detail__address">
				<span className="wp-block-gatherpress-venue-detail__placeholder">
					{ placeholder }
				</span>
			</address>
		);
	}

	if ( 'block' === variant ) {
		const baseClass = 'gatherpress-venue-detail__address';
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
						aria-controls={
							showSuggestionPanel ? listboxId : undefined
						}
					/>
				</address>
				{ suggestionPopover }
			</div>
		);
	}

	const helpSlotText =
		showSuggestionPanel || isLoadingSuggestions ? undefined : help;

	return (
		<div className="gatherpress-address-autocomplete">
			<TextControl
				ref={ inputRef }
				__next40pxDefaultSize
				type="search"
				autoComplete="off"
				autoCapitalize="off"
				autoCorrect="off"
				spellCheck={ false }
				readOnly={ suppressNativeAutofill }
				onFocus={ unlockAddressInput }
				onKeyDown={ handleKeyDown }
				onMouseDown={ unlockAddressInput }
				id={ fieldUid }
				label={ __( 'Full Address', 'gatherpress' ) }
				value={ value }
				onChange={ handleChange }
				help={ helpSlotText }
				name={ fieldUid }
				aria-autocomplete="list"
				aria-controls={ showSuggestionPanel ? listboxId : undefined }
			/>
			{ suggestionPopover }
		</div>
	);
}
