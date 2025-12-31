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
	getWrapperClasses,
} from '../helpers';

/**
 * Renders a checkbox field component for the block editor.
 *
 * @param {Object}   props                   - Component props.
 * @param {Object}   props.attributes        - Block attributes object.
 * @param {Function} props.setAttributes     - Function to update block attributes.
 * @param {Object}   props.blockProps        - WordPress block wrapper properties.
 * @param {Function} props.generateFieldName - Function to generate field name from label.
 * @return {JSX.Element} The checkbox field component.
 */
export default function CheckboxField( {
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

	return (
		<div
			{ ...blockProps }
			className={ getWrapperClasses( fieldType, blockProps ) }
		>
			<input
				style={ getInputStyles( fieldType, attributes ) }
				type="checkbox"
				name={ fieldName }
				required={ required }
				checked={ !! fieldValue }
				disabled={ true }
				tabIndex={ -1 }
				autoComplete="off"
			/>
			<div
				className="gatherpress-label-wrapper"
				style={ getLabelWrapperStyles( attributes ) }
			>
				<RichText
					tagName="label"
					placeholder={ __( 'Add checkbox label…', 'gatherpress' ) }
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
		</div>
	);
}
