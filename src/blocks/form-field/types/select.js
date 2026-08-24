/**
 * External dependencies
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import {
	getInputStyles,
	getLabelStyles,
	getLabelWrapperStyles,
	getOptionUpdates,
	getWrapperClasses,
} from '../helpers';

/**
 * Renders a select field component for the block editor.
 *
 * A select shows one option at a time, so the options are only editable while
 * the block is selected: the field then expands into the list a browser shows
 * when the control is open, and collapses back to the closed control when
 * selection moves away. That keeps the canvas showing what the visitor sees
 * without hiding the options from the author.
 *
 * @param {Object}   props                   - Component props.
 * @param {Object}   props.attributes        - Block attributes object.
 * @param {Function} props.setAttributes     - Function to update block attributes.
 * @param {Object}   props.blockProps        - WordPress block wrapper properties.
 * @param {Function} props.generateFieldName - Function to generate field name from label.
 * @param {boolean}  props.isSelected        - Whether the block is currently selected.
 *
 * @return {JSX.Element} The select field component.
 */
export default function SelectField( {
	attributes,
	setAttributes,
	blockProps,
	generateFieldName,
	isSelected,
} ) {
	const {
		fieldType,
		fieldName,
		fieldValue,
		label,
		required,
		requiredText,
		requiredTextColor,
		helpText,
		radioOptions = [ { label: '', value: '', id: uuidv4() } ],
	} = attributes;

	// Handle label blur to auto-generate field name.
	const handleLabelBlur = ( labelValue ) => {
		if ( ! fieldName && labelValue ) {
			const generatedFieldName = generateFieldName( labelValue );

			if ( generatedFieldName ) {
				setAttributes( { fieldName: generatedFieldName } );
			}
		}
	};

	// Handle select option changes.
	const updateSelectOption = ( index, field, value ) => {
		setAttributes(
			getOptionUpdates( {
				options: radioOptions,
				index,
				field,
				value,
				fieldValue,
			} ),
		);

		if ( 'label' === field && 0 === index && ! fieldName && value ) {
			const generatedFieldName = generateFieldName( value );

			if ( generatedFieldName ) {
				setAttributes( { fieldName: generatedFieldName } );
			}
		}
	};

	/**
	 * Move focus to an option, scoped to the field that was typed in.
	 *
	 * The lookup starts from the element that received the keystroke so a
	 * second select field on the same post cannot take the focus, and the
	 * range comes from that element's own document because the options
	 * render inside the editor canvas iframe rather than the admin document.
	 *
	 * @param {HTMLElement} scopeElement    - The element the keystroke came from.
	 * @param {number}      targetIndex     - Index of the option to focus.
	 * @param {boolean}     placeCaretAtEnd - Whether to put the caret at the end.
	 *
	 * @return {void}
	 */
	const focusOption = ( scopeElement, targetIndex, placeCaretAtEnd = false ) => {
		const list = scopeElement?.closest( '.gatherpress-select-options' );

		if ( ! list ) {
			return;
		}

		setTimeout( () => {
			const element = list.querySelectorAll(
				'.gatherpress-select-option .rich-text',
			)[ targetIndex ];

			if ( ! element ) {
				return;
			}

			element.focus();

			if ( ! placeCaretAtEnd ) {
				return;
			}

			// Move cursor to end of text. The canvas can be torn down inside
			// the timer, which leaves the element without a view to select in.
			const ownerDocument = element.ownerDocument;
			const selection = ownerDocument.defaultView?.getSelection();

			if ( ! selection ) {
				return;
			}

			const range = ownerDocument.createRange();

			range.selectNodeContents( element );
			range.collapse( false );
			selection.removeAllRanges();
			selection.addRange( range );
		}, 50 );
	};

	const addSelectOption = ( scopeElement, index ) => {
		// Insert directly after the option Enter was pressed in, the way a
		// list behaves, rather than appending to the end of the list.
		const insertAt = index + 1;
		const newOptions = [ ...radioOptions ];

		newOptions.splice( insertAt, 0, { label: '', value: '', id: uuidv4() } );
		setAttributes( { radioOptions: newOptions } );

		focusOption( scopeElement, insertAt );
	};

	const removeSelectOption = ( scopeElement, index ) => {
		const optionToRemove = radioOptions[ index ];
		const newOptions = radioOptions.filter( ( _, i ) => i !== index );

		// Clear fieldValue if removing the selected option.
		const updates = { radioOptions: newOptions };

		if ( fieldValue === optionToRemove.value ) {
			updates.fieldValue = '';
		}

		setAttributes( updates );

		focusOption( scopeElement, Math.max( 0, index - 1 ), true );
	};

	const handleKeyDown = ( event, index ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			addSelectOption( event.target, index );
		} else if ( 'Backspace' === event.key || 'Delete' === event.key ) {
			const currentOption = radioOptions[ index ];

			// Only remove if the option is empty and it's not the last remaining option.
			if ( ! currentOption.label && 1 < radioOptions.length ) {
				event.preventDefault();
				removeSelectOption( event.target, index );
			}
		}
	};

	// The expanded list is the same control in its open state, so it takes the
	// styles the closed select takes — font, colors, border, width — and the
	// rows carry the padding so the box itself can sit flush against them.
	const inputStyles = getInputStyles( fieldType, attributes );
	const { padding: inputPadding, ...boxStyles } = inputStyles;
	const rowStyles = { paddingInline: inputPadding };

	// Mirror what the rendered field shows when nothing is chosen: a required
	// select opens on its placeholder, and any other select opens on its first
	// option, which is what a browser does with no selection.
	const showPlaceholder = Boolean( required ) && '' === fieldValue;
	const previewValue = showPlaceholder
		? ''
		: fieldValue || radioOptions[ 0 ]?.value || '';

	return (
		<div
			{ ...blockProps }
			className={ getWrapperClasses( fieldType, blockProps ) }
		>
			<div
				className="gatherpress-label-wrapper"
				style={ getLabelWrapperStyles( attributes ) }
			>
				<RichText
					tagName="label"
					placeholder={ __( 'Add label…', 'gatherpress' ) }
					value={ label }
					onChange={ ( value ) => setAttributes( { label: value } ) }
					onBlur={ () => handleLabelBlur( label ) }
					allowedFormats={ [ 'gatherpress/tooltip' ] }
					style={ getLabelStyles( attributes ) }
				/>
				{ required && (
					<RichText
						tagName="span"
						className="gatherpress-label-required"
						placeholder={ __( '(required)', 'gatherpress' ) }
						value={ requiredText }
						onChange={ ( value ) =>
							setAttributes( { requiredText: value } )
						}
						allowedFormats={ [ 'gatherpress/tooltip' ] }
						style={ {
							...( requiredTextColor && {
								color: requiredTextColor,
							} ),
						} }
					/>
				) }
			</div>

			{ isSelected ? (
				<div
					className="gatherpress-select-options"
					style={ boxStyles }
				>
					{ radioOptions.map( ( option, index ) => (
						<div
							key={ option.id }
							className={
								fieldValue === option.value &&
								'' !== option.value
									? 'gatherpress-select-option is-selected'
									: 'gatherpress-select-option'
							}
							style={ rowStyles }
						>
							<RichText
								tagName="span"
								placeholder={ __(
									'Option label…',
									'gatherpress',
								) }
								value={ option.label }
								onChange={ ( value ) =>
									updateSelectOption( index, 'label', value )
								}
								onKeyDown={ ( event ) =>
									handleKeyDown( event, index )
								}
								allowedFormats={ [ 'gatherpress/tooltip' ] }
								identifier={ `select-option-${ index }` }
							/>
						</div>
					) ) }
				</div>
			) : (
				<select
					style={ inputStyles }
					name={ fieldName }
					value={ previewValue }
					disabled={ true }
					tabIndex={ -1 }
					onChange={ () => {} }
				>
					{ showPlaceholder && (
						<option value="">
							{ __( 'Select an option', 'gatherpress' ) }
						</option>
					) }
					{ radioOptions.map( ( option ) => (
						<option key={ option.id } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			) }
			{ helpText && (
				<p className="gatherpress-help-text">{ helpText }</p>
			) }
		</div>
	);
}
