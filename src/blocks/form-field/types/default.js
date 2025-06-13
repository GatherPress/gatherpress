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
	getInputContainerStyles,
	getWrapperClasses,
} from '../helpers';

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
		minValue,
		maxValue,
		sideBySideLayout,
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
			<div style={getInputContainerStyles(fieldType, attributes)}>
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
					{...(fieldType === 'number' &&
						minValue !== undefined && { min: minValue })}
					{...(fieldType === 'number' &&
						maxValue !== undefined && { max: maxValue })}
				/>
			</div>
		</div>
	);
}
