/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import {
	getInputStyles,
	getLabelStyles,
	getLabelWrapperStyles,
	getOptionStyles,
	getWrapperClasses,
} from '../helpers';

/**
 * Renders a radio button group field component for the block editor.
 *
 * @param {Object}   props                   - Component props.
 * @param {Object}   props.attributes        - Block attributes object.
 * @param {Function} props.setAttributes     - Function to update block attributes.
 * @param {Object}   props.blockProps        - WordPress block wrapper properties.
 * @param {Function} props.generateFieldName - Function to generate field name from label.
 * @return {JSX.Element} The radio button group field component.
 */
export default function RadioField( {
	attributes,
	setAttributes,
	blockProps,
	generateFieldName,
} ) {
	const {
		fieldType,
		fieldName,
		fieldValue,
		label,
		required,
		requiredText,
		requiredTextColor,
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

	// Handle radio option changes.
	const updateRadioOption = ( index, field, value ) => {
		const newOptions = [ ...radioOptions ];
		newOptions[ index ] = { ...newOptions[ index ], [ field ]: value };

		if ( 'label' === field ) {
			const cleanValue = value
				.toLowerCase()
				.split( /[^a-z0-9]+/ ) // Split on non-alphanumeric sequences.
				.filter( ( part ) => 0 < part.length ) // Remove empty strings.
				.join( '-' ); // Join with dashes.
			newOptions[ index ].value = cleanValue || value;
		}

		setAttributes( { radioOptions: newOptions } );

		if ( 'label' === field && 0 === index && ! fieldName && value ) {
			const generatedFieldName = generateFieldName( value );
			if ( generatedFieldName ) {
				setAttributes( { fieldName: generatedFieldName } );
			}
		}
	};

	const addRadioOption = () => {
		const newOptions = [ ...radioOptions, { label: '', value: '', id: uuidv4() } ];
		setAttributes( { radioOptions: newOptions } );

		setTimeout( () => {
			const radioOptionElements = document.querySelectorAll(
				'.gatherpress-radio-option .rich-text',
			);
			const lastOption =
				radioOptionElements[ radioOptionElements.length - 1 ];
			if ( lastOption ) {
				lastOption.focus();
			}
		}, 50 );
	};

	const removeRadioOption = ( index ) => {
		const optionToRemove = radioOptions[ index ];
		const newOptions = radioOptions.filter( ( _, i ) => i !== index );

		// Clear fieldValue if removing the selected option.
		const updates = { radioOptions: newOptions };
		if ( fieldValue === optionToRemove.value ) {
			updates.fieldValue = '';
		}

		setAttributes( updates );

		// Focus the previous option after removal and set cursor to end.
		setTimeout( () => {
			const targetIndex = Math.max( 0, index - 1 );
			const radioOptionElements = document.querySelectorAll(
				'.gatherpress-radio-option .rich-text',
			);
			if ( radioOptionElements[ targetIndex ] ) {
				const element = radioOptionElements[ targetIndex ];
				element.focus();

				// Move cursor to end of text.
				const range = document.createRange();
				const selection = getSelection();

				range.selectNodeContents( element );
				range.collapse( false );
				selection.removeAllRanges();
				selection.addRange( range );
			}
		}, 50 );
	};

	const handleKeyDown = ( event, index ) => {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			addRadioOption();
		} else if ( 'Backspace' === event.key || 'Delete' === event.key ) {
			const currentOption = radioOptions[ index ];

			// Only remove if the option is empty and it's not the last remaining option.
			if ( ! currentOption.label && 1 < radioOptions.length ) {
				event.preventDefault();
				removeRadioOption( index );
			}
		}
	};

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
					tagName="legend"
					placeholder={ __( 'Radio group title…', 'gatherpress' ) }
					value={ label }
					onChange={ ( value ) => setAttributes( { label: value } ) }
					onBlur={ () => handleLabelBlur( label ) }
					allowedFormats={ [] }
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
						allowedFormats={ [] }
						style={ {
							...( requiredTextColor && {
								color: requiredTextColor,
							} ),
						} }
					/>
				) }
			</div>

			<div
				className="gatherpress-radio-group"
				style={ getLabelWrapperStyles( attributes ) }
			>
				{ radioOptions.map( ( option, index ) => (
					<div key={ option.id } className="gatherpress-radio-option">
						<input
							style={ getInputStyles( fieldType, attributes ) }
							type="radio"
							name={ fieldName }
							value={ option.value }
							checked={
								fieldValue === option.value &&
								'' !== option.value
							}
							disabled={ true }
							tabIndex={ -1 }
							autoComplete="off"
						/>
						<RichText
							tagName="label"
							placeholder={ __( 'Option label…', 'gatherpress' ) }
							value={ option.label }
							onChange={ ( value ) =>
								updateRadioOption( index, 'label', value )
							}
							onKeyDown={ ( event ) => handleKeyDown( event, index ) }
							allowedFormats={ [] }
							identifier={ `radio-option-${ index }` }
							style={ getOptionStyles( attributes ) }
						/>
					</div>
				) ) }
			</div>
		</div>
	);
}
