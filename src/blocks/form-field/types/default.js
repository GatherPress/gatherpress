/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { RichText } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { getInputStyles, getLabelStyles, getWrapperClasses } from '../helpers';

export default function DefaultField({
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
	} = attributes;

	// Handle label blur to auto-generate field name
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
			<input
				style={getInputStyles(fieldType, attributes)}
				type={fieldType}
				name={fieldName}
				defaultValue={fieldValue}
				placeholder={placeholder}
				required={required}
				autoComplete="off"
				readOnly={true}
				tabIndex={-1}
				{...(undefined !== minValue && { min: minValue })}
				{...(undefined !== maxValue && { max: maxValue })}
			/>
		</div>
	);
}
