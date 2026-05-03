/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Popover, Spinner, TextControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import {
	useCallback,
	useEffect,
	useLayoutEffect,
	useRef,
	useState,
} from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useAddressFieldAntiAutofill } from './use-address-field-anti-autofill';
import {
	shouldShowAddressSuggestionUi,
	useAddressAutocomplete,
} from './use-address-autocomplete';
import './index.scss';

/**
 * Address field with Photon-backed suggestions (block: textarea + canvas; settings: TextControl + form).
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
	// Stable, deterministic per-instance id from @wordpress/compose. Avoids
	// Math.random() (flagged by SonarCloud as a weak PRNG) and matches WP's
	// idiom for generating unique DOM ids inside React components.
	const instanceId = useInstanceId( AddressAutocompleteField );
	const fieldUid = `gatherpress-address-${ instanceId }`;
	const listboxId = `${ fieldUid }-suggest`;
	// Track focus so the suggestion popover only shows while the field is
	// focused. Clicks on suggestion buttons preventDefault on mousedown below
	// so they don't steal focus and unnecessarily close the popover.
	const [ isFieldFocused, setIsFieldFocused ] = useState( false );

	const { suppressNativeAutofill, unlockAddressInput } =
		useAddressFieldAntiAutofill( value, inputRef );

	const {
		suggestions,
		activeIndex,
		setActiveIndex,
		isLoadingSuggestions,
		suggestionError,
		handleChange,
		closeSuggestions,
		selectSuggestion,
		handleKeyDown,
	} = useAddressAutocomplete( {
		onChange,
		onKeyDown: 'block' === variant ? onKeyDown : undefined,
	} );

	const showSuggestionUi =
		isFieldFocused &&
		shouldShowAddressSuggestionUi(
			value,
			suggestions,
			isLoadingSuggestions,
			suggestionError
		);
	const showSuggestionPanel = 0 < suggestions.length;
	const showSuggestionError =
		Boolean( suggestionError ) && ! showSuggestionPanel && ! isLoadingSuggestions;

	// Stable per-option id for the combobox `aria-activedescendant` pattern.
	const optionId = ( index ) => `${ listboxId }-opt-${ index }`;
	const activeDescendantId =
		showSuggestionPanel && 0 <= activeIndex && activeIndex < suggestions.length
			? optionId( activeIndex )
			: undefined;

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
				className="gatherpress-address-autocomplete__popover"
			>
				{ isLoadingSuggestions && ! showSuggestionPanel && (
					<div className="gatherpress-address-autocomplete__popover-inner">
						<Spinner />
						<span className="gatherpress-address-autocomplete__loading">
							{ __( 'Searching for addresses…', 'gatherpress' ) }
						</span>
					</div>
				) }
				{ showSuggestionError && (
					<div
						className="gatherpress-address-autocomplete__popover-inner gatherpress-address-autocomplete__error"
						role="status"
					>
						<span>{ suggestionError }</span>
					</div>
				) }
				{ showSuggestionPanel && (
					<ul
						id={ listboxId }
						className="gatherpress-address-autocomplete__suggestions"
						role="listbox"
						aria-label={ __( 'Address suggestions', 'gatherpress' ) }
					>
						{ suggestions.map( ( item, index ) => (
							<li key={ `${ item.label }-${ index }` } role="none">
								<button
									type="button"
									id={ optionId( index ) }
									role="option"
									tabIndex={ -1 }
									aria-selected={ activeIndex === index }
									className={
										activeIndex === index
											? 'gatherpress-address-autocomplete__suggestion is-active'
											: 'gatherpress-address-autocomplete__suggestion'
									}
									// Prevent the input from blurring on
									// mousedown so the popover doesn't flicker
									// shut before the click lands.
									onMouseDown={ ( event ) =>
										event.preventDefault()
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
						onFocus={ () => {
							unlockAddressInput();
							setIsFieldFocused( true );
						} }
						onBlur={ () => setIsFieldFocused( false ) }
						onMouseDown={ unlockAddressInput }
						placeholder={ placeholder }
						readOnly={ suppressNativeAutofill }
						role="combobox"
						aria-label={ __( 'Venue address', 'gatherpress' ) }
						aria-autocomplete="list"
						aria-expanded={ showSuggestionPanel }
						aria-controls={
							showSuggestionPanel ? listboxId : undefined
						}
						aria-activedescendant={ activeDescendantId }
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
				onFocus={ () => {
					unlockAddressInput();
					setIsFieldFocused( true );
				} }
				onBlur={ () => setIsFieldFocused( false ) }
				onKeyDown={ handleKeyDown }
				onMouseDown={ unlockAddressInput }
				id={ fieldUid }
				label={ __( 'Full Address', 'gatherpress' ) }
				value={ value }
				onChange={ handleChange }
				help={ helpSlotText }
				name={ fieldUid }
				role="combobox"
				aria-autocomplete="list"
				aria-expanded={ showSuggestionPanel }
				aria-controls={ showSuggestionPanel ? listboxId : undefined }
				aria-activedescendant={ activeDescendantId }
			/>
			{ suggestionPopover }
		</div>
	);
}
