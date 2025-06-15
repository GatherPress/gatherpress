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
	getOptionStyles,
	getWrapperClasses,
} from '../helpers';

export default function RadioField({
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
		required,
		requiredText,
		requiredTextColor,
		radioOptions = [{ label: '', value: '' }],
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

	// Handle radio option changes
	const updateRadioOption = (index, field, value) => {
		const newOptions = [...radioOptions];
		newOptions[index] = { ...newOptions[index], [field]: value };

		if (field === 'label') {
			const cleanValue = value
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '');
			newOptions[index].value = cleanValue || value;
		}

		setAttributes({ radioOptions: newOptions });

		if (field === 'label' && index === 0 && !fieldName && value) {
			const generatedFieldName = generateFieldName(value);
			if (generatedFieldName) {
				setAttributes({ fieldName: generatedFieldName });
			}
		}
	};

	const addRadioOption = () => {
		const newOptions = [...radioOptions, { label: '', value: '' }];
		setAttributes({ radioOptions: newOptions });

		setTimeout(() => {
			const radioOptionElements = document.querySelectorAll(
				'.gatherpress-radio-option .rich-text'
			);
			const lastOption =
				radioOptionElements[radioOptionElements.length - 1];
			if (lastOption) {
				lastOption.focus();
			}
		}, 50);
	};

	return (
		<div
			{...blockProps}
			className={getWrapperClasses(fieldType, blockProps)}
		>
			<div className="gatherpress-label-wrapper">
				<RichText
					tagName="legend"
					placeholder={__('Radio group title…', 'gatherpress')}
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

			<div className="gatherpress-radio-group">
				{radioOptions.map((option, index) => (
					<div key={index} className="gatherpress-radio-option">
						<input
							style={getInputStyles(fieldType, attributes)}
							type="radio"
							name={fieldName}
							value={option.value}
							checked={fieldValue === option.value}
							disabled={true}
							tabIndex={-1}
						/>
						<RichText
							tagName="label"
							placeholder={__('Option label…', 'gatherpress')}
							value={option.label}
							onChange={(value) =>
								updateRadioOption(index, 'label', value)
							}
							onKeyDown={(event) => {
								if (event.key === 'Enter') {
									event.preventDefault();
									addRadioOption();
								}
							}}
							allowedFormats={[]}
							identifier={`radio-option-${index}`}
							style={getOptionStyles(attributes)}
						/>
					</div>
				))}
			</div>
		</div>
	);
}
