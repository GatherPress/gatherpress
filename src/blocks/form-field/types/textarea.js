/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { getInputStyles, getLabelStyles, getWrapperClasses } from '../helpers';

/**
 * Renders a textarea field component for the block editor.
 *
 * @param {Object}   props                   - Component props.
 * @param {Object}   props.attributes        - Block attributes object.
 * @param {Function} props.setAttributes     - Function to update block attributes.
 * @param {Object}   props.blockProps        - WordPress block wrapper properties.
 * @param {Function} props.generateFieldName - Function to generate field name from label.
 * @return {JSX.Element} The textarea field component.
 */
export default function TextareaField({
	attributes,
	setAttributes,
	blockProps,
	generateFieldName,
}) {
	const {
		fieldType,
		fieldName,
		fieldValue,
		label,
		placeholder,
		required,
		requiredText,
		requiredTextColor,
		minValue,
		maxValue,
		inlineLayout,
		textareaRows,
	} = attributes;

	// Handle label blur to auto-generate field name.
	const handleLabelBlur = (labelValue) => {
		if (!fieldName && labelValue) {
			const generatedFieldName = generateFieldName(labelValue);
			if (generatedFieldName) {
				setAttributes({ fieldName: generatedFieldName });
			}
		}
	};

	return (
		<div
			{...blockProps}
			className={getWrapperClasses(fieldType, blockProps, inlineLayout)}
		>
			<div className="gatherpress-label-wrapper">
				<RichText
					tagName="label"
					placeholder={__('Add label…', 'gatherpress')}
					value={label}
					onChange={(value) => setAttributes({ label: value })}
					onBlur={() => handleLabelBlur(label)}
					allowedFormats={[]}
					style={getLabelStyles(attributes)}
				/>
				{required && (
					<RichText
						tagName="span"
						className="gatherpress-label-required"
						placeholder={__('(required)', 'gatherpress')}
						value={requiredText}
						onChange={(value) =>
							setAttributes({ requiredText: value })
						}
						allowedFormats={[]}
						style={{
							...(requiredTextColor && {
								color: requiredTextColor,
							}),
						}}
					/>
				)}
			</div>
			<textarea
				style={getInputStyles(fieldType, attributes)}
				name={fieldName}
				placeholder={placeholder}
				defaultValue={fieldValue}
				required={required}
				readOnly={true}
				autoComplete="off"
				tabIndex={-1}
				rows={textareaRows}
				{...(minValue !== undefined && { minLength: minValue })}
				{...(maxValue !== undefined && { maxLength: maxValue })}
			/>
		</div>
	);
}
