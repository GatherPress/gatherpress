/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { getInputStyles, getLabelStyles, getWrapperClasses } from '../helpers';

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
		minValue,
		maxValue,
		sideBySideLayout,
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
			className={getWrapperClasses(
				fieldType,
				blockProps,
				sideBySideLayout
			)}
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
				rows={4}
				{...(minValue !== undefined && { minLength: minValue })}
				{...(maxValue !== undefined && { maxLength: maxValue })}
			/>
		</div>
	);
}
