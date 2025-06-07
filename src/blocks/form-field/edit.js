/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
	PanelColorSettings,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	RangeControl,
	Button,
	Flex,
	FlexItem,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * Edit component for the Form Field block.
 *
 * @param {Object}   props               The block props.
 * @param {Object}   props.attributes    The block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {JSX.Element} The edit component.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		fieldType,
		fieldName,
		label,
		placeholder,
		required,
		requiredText,
		minValue,
		maxValue,
		radioOptions = [{ label: '', value: '' }], // Default radio options.
		inputFontSize,
		inputLineHeight,
		inputBorderWidth,
		inputBorderRadius,
		labelFontSize,
		labelLineHeight,
		optionFontSize,
		optionLineHeight,
		sideBySideLayout,
		fieldWidth,
		labelTextColor,
		fieldTextColor,
		fieldBackgroundColor,
		borderColor,
		optionTextColor,
	} = attributes;

	const blockProps = useBlockProps();

	// Generate field name from label
	const generateFieldName = (labelValue) => {
		return labelValue
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9\s]/g, '') // Remove special characters except spaces
			.replace(/\s+/g, '_') // Replace spaces with underscores
			.replace(/^_+|_+$/g, ''); // Remove leading/trailing underscores
	};

	// Handle label blur to auto-generate field name
	const handleLabelBlur = (labelValue) => {
		if (!fieldName && labelValue) {
			const generatedFieldName = generateFieldName(labelValue);
			if (generatedFieldName) {
				setAttributes({ fieldName: generatedFieldName });
			}
		}
	};

	// Define which field types get which styles.
	const getInputStyles = (ft) => {
		const styles = {};

		if (['text', 'email', 'url', 'number', 'textarea'].includes(ft)) {
			if (inputFontSize !== undefined) {
				styles.fontSize = `${inputFontSize}px`;
			}
			if (inputLineHeight !== undefined) {
				styles.lineHeight = inputLineHeight;
			}
			if (inputBorderWidth !== undefined) {
				styles.borderWidth = `${inputBorderWidth}px`;
			}
			if (inputBorderRadius !== undefined) {
				styles.borderRadius = `${inputBorderRadius}px`;
			}
			if (fieldTextColor) {
				styles.color = fieldTextColor;
			}
			if (fieldBackgroundColor) {
				styles.backgroundColor = fieldBackgroundColor;
			}
			if (borderColor) {
				styles.borderColor = borderColor;
			}
		}

		if (['checkbox', 'radio'].includes(ft)) {
			if (inputBorderWidth !== undefined) {
				styles.borderWidth = `${inputBorderWidth}px`;
			}
			if (borderColor) {
				styles.borderColor = borderColor;
			}
		}

		// Hidden fields get opacity for editor visibility
		if (ft === 'hidden') {
			styles.opacity = 0.5;
		}

		return styles;
	};

	// Get label styles
	const getLabelStyles = () => {
		const styles = {};
		if (labelFontSize !== undefined) {
			styles.fontSize = `${labelFontSize}px`;
		}
		if (labelLineHeight !== undefined) {
			styles.lineHeight = labelLineHeight;
		}
		if (labelTextColor) {
			styles.color = labelTextColor;
		}
		return styles;
	};

	// Get input container styles for layout
	const getInputContainerStyles = () => {
		const styles = {};

		// Apply width to text-based inputs only
		if (
			['text', 'email', 'url', 'number', 'textarea'].includes(
				fieldType
			) &&
			fieldWidth
		) {
			styles.width = `${fieldWidth}%`;
		}

		return styles;
	};

	// Get option styles (for radio buttons)
	const getOptionStyles = () => {
		const styles = {};
		if (optionFontSize !== undefined) {
			styles.fontSize = `${optionFontSize}px`;
		}
		if (optionLineHeight !== undefined) {
			styles.lineHeight = optionLineHeight;
		}
		if (optionTextColor) {
			styles.color = optionTextColor;
		}
		return styles;
	};

	// Handle radio option changes
	const updateRadioOption = (index, field, value) => {
		const newOptions = [...radioOptions];
		newOptions[index] = { ...newOptions[index], [field]: value };

		// Auto-generate value from label when label changes
		if (field === 'label') {
			const cleanValue = value
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '');
			newOptions[index].value = cleanValue || value; // fallback to original value if cleaning results in empty string
		}

		setAttributes({ radioOptions: newOptions });

		// Auto-generate field name from first option label if field name is empty
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

		// Focus will naturally move to the new option
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

	const removeRadioOption = (index) => {
		const newOptions = radioOptions.filter((_, i) => i !== index);
		setAttributes({ radioOptions: newOptions });
	};

	const renderInput = () => {
		if (fieldType === 'radio') {
			return (
				<div className="gatherpress-radio-group">
					{radioOptions.map((option, index) => (
						<div key={index} className="gatherpress-radio-option">
							<input
								style={getInputStyles(fieldType)}
								type="radio"
								name={fieldName}
								disabled={true}
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
								style={getOptionStyles()}
							/>
						</div>
					))}
				</div>
			);
		}

		if (fieldType === 'textarea') {
			return (
				<div style={getInputContainerStyles()}>
					<textarea
						style={getInputStyles(fieldType)}
						name={fieldName}
						onChange={(e) =>
							setAttributes({ placeholder: e.target.value })
						}
						placeholder={placeholder}
						required={required}
						rows={4}
						{...(minValue !== undefined && { minLength: minValue })}
						{...(maxValue !== undefined && { maxLength: maxValue })}
						disabled={true}
					/>
				</div>
			);
		}

		return (
			<div style={getInputContainerStyles()}>
				<input
					style={getInputStyles(fieldType)}
					type={fieldType}
					name={fieldName}
					placeholder={placeholder}
					required={required}
					disabled={true}
				/>
			</div>
		);
	};

	// Build the main wrapper class names
	const getWrapperClasses = () => {
		let classes = `${blockProps.className || ''} gatherpress-field-type-${fieldType}`;

		const isTextBasedField = [
			'text',
			'email',
			'url',
			'number',
			'textarea',
		].includes(fieldType);
		if (sideBySideLayout && isTextBasedField) {
			classes += ' gatherpress-side-by-side';
		}

		return classes.trim();
	};

	return (
		<>
			<div {...blockProps} className={getWrapperClasses()}>
				{fieldType === 'hidden' && (
					<div
						className="gatherpress-hidden-field-indicator"
						style={{
							padding: '12px',
							border: '2px dashed #ccc',
							borderRadius: '4px',
							textAlign: 'center',
							opacity: 0.7,
							fontSize: '14px',
							color: '#666',
						}}
					>
						<span
							className="dashicons dashicons-hidden"
							style={{ marginRight: '8px' }}
						></span>
						{__('Hidden Field', 'gatherpress')}
						{fieldName && `: ${fieldName}`}
					</div>
				)}

				{fieldType === 'radio' && (
					<div className="gatherpress-label-wrapper">
						<RichText
							tagName="legend"
							placeholder={__(
								'Radio group title…',
								'gatherpress'
							)}
							value={label}
							onChange={(value) =>
								setAttributes({ label: value })
							}
							onBlur={() => handleLabelBlur(label)}
							allowedFormats={[]}
							style={getLabelStyles()}
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
				)}

				{fieldType !== 'hidden' && fieldType !== 'radio' && (
					<div className="gatherpress-label-wrapper">
						<RichText
							tagName="label"
							placeholder={__('Add label…', 'gatherpress')}
							value={label}
							onChange={(value) =>
								setAttributes({ label: value })
							}
							onBlur={() => handleLabelBlur(label)}
							allowedFormats={[]}
							style={getLabelStyles()}
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
				)}

				{fieldType !== 'hidden' && renderInput()}
			</div>

			<InspectorControls>
				<PanelBody title={__('Field Settings', 'gatherpress')}>
					<SelectControl
						label={__('Field Type', 'gatherpress')}
						value={fieldType}
						options={[
							{ label: __('Text', 'gatherpress'), value: 'text' },
							{
								label: __('Email', 'gatherpress'),
								value: 'email',
							},
							{
								label: __('URL', 'gatherpress'),
								value: 'url',
							},
							{
								label: __('Number', 'gatherpress'),
								value: 'number',
							},
							{
								label: __('Textarea', 'gatherpress'),
								value: 'textarea',
							},
							{
								label: __('Checkbox', 'gatherpress'),
								value: 'checkbox',
							},
							{
								label: __('Radio', 'gatherpress'),
								value: 'radio',
							},
							{
								label: __('Hidden', 'gatherpress'),
								value: 'hidden',
							},
						]}
						onChange={(value) =>
							setAttributes({ fieldType: value })
						}
					/>

					<TextControl
						label={__('Field Name', 'gatherpress')}
						value={fieldName}
						onChange={(value) =>
							setAttributes({ fieldName: value })
						}
						help={__(
							'The name attribute for the form field',
							'gatherpress'
						)}
					/>

					{fieldType !== 'radio' && fieldType !== 'hidden' && (
						<TextControl
							label={__('Placeholder', 'gatherpress')}
							value={placeholder}
							onChange={(value) =>
								setAttributes({ placeholder: value })
							}
							help={__(
								'Placeholder text shown inside the field',
								'gatherpress'
							)}
						/>
					)}

					{fieldType === 'hidden' && (
						<TextControl
							label={__('Value', 'gatherpress')}
							value={placeholder}
							onChange={(value) =>
								setAttributes({ placeholder: value })
							}
							help={__(
								'The value for this hidden field',
								'gatherpress'
							)}
						/>
					)}

					{fieldType !== 'hidden' && (
						<ToggleControl
							label={__('Required', 'gatherpress')}
							checked={required}
							onChange={(value) =>
								setAttributes({ required: value })
							}
							help={__('Make this field required', 'gatherpress')}
						/>
					)}

					{fieldType === 'number' && (
						<>
							<NumberControl
								label={__('Minimum Value', 'gatherpress')}
								value={minValue}
								onChange={(value) =>
									setAttributes({ minValue: value })
								}
								help={__(
									'Minimum allowed value for this number field',
									'gatherpress'
								)}
							/>
							<NumberControl
								label={__('Maximum Value', 'gatherpress')}
								value={maxValue}
								onChange={(value) =>
									setAttributes({ maxValue: value })
								}
								help={__(
									'Maximum allowed value for this number field',
									'gatherpress'
								)}
							/>
						</>
					)}

					{fieldType === 'textarea' && (
						<>
							<NumberControl
								label={__('Minimum Length', 'gatherpress')}
								value={minValue}
								onChange={(value) =>
									setAttributes({ minValue: value })
								}
								min={0}
								help={__(
									'Minimum number of characters required',
									'gatherpress'
								)}
							/>
							<NumberControl
								label={__('Maximum Length', 'gatherpress')}
								value={maxValue}
								onChange={(value) =>
									setAttributes({ maxValue: value })
								}
								min={0}
								help={__(
									'Maximum number of characters allowed',
									'gatherpress'
								)}
							/>
						</>
					)}

					{['text', 'email', 'url'].includes(fieldType) && (
						<>
							<NumberControl
								label={__('Minimum Length', 'gatherpress')}
								value={minValue}
								onChange={(value) =>
									setAttributes({ minValue: value })
								}
								min={0}
								help={__(
									'Minimum number of characters required',
									'gatherpress'
								)}
							/>
							<NumberControl
								label={__('Maximum Length', 'gatherpress')}
								value={maxValue}
								onChange={(value) =>
									setAttributes({ maxValue: value })
								}
								min={0}
								help={__(
									'Maximum number of characters allowed',
									'gatherpress'
								)}
							/>
						</>
					)}
				</PanelBody>

				{fieldType === 'radio' && (
					<PanelBody title={__('Radio Options', 'gatherpress')}>
						{radioOptions.map((option, index) => (
							<div key={index}>
								<Flex justify="normal" gap="2">
									<FlexItem>
										<TextControl
											label={`${__('Option', 'gatherpress')} ${index + 1}`}
											value={option.label}
											onChange={(value) =>
												updateRadioOption(
													index,
													'label',
													value
												)
											}
											help={__(
												'Label and value for this option',
												'gatherpress'
											)}
										/>
									</FlexItem>
									<FlexItem>
										{radioOptions.length > 1 && (
											<Button
												variant="secondary"
												isDestructive
												onClick={() =>
													removeRadioOption(index)
												}
												style={{
													marginTop: '-1rem',
												}}
												icon="no-alt"
												label={__(
													'Remove option',
													'gatherpress'
												)}
											/>
										)}
									</FlexItem>
								</Flex>
							</div>
						))}
						<Button variant="secondary" onClick={addRadioOption}>
							{__('Add Option', 'gatherpress')}
						</Button>
					</PanelBody>
				)}

				{['text', 'email', 'url', 'number', 'textarea'].includes(
					fieldType
				) && (
					<PanelBody title={__('Layout Settings', 'gatherpress')}>
						<ToggleControl
							label={__('Side by Side Layout', 'gatherpress')}
							checked={sideBySideLayout}
							onChange={(value) =>
								setAttributes({ sideBySideLayout: value })
							}
							help={__(
								'Display label and input side by side',
								'gatherpress'
							)}
						/>

						<RangeControl
							label={__('Field Width (%)', 'gatherpress')}
							value={fieldWidth}
							onChange={(value) =>
								setAttributes({ fieldWidth: value })
							}
							min={0}
							max={100}
							help={__(
								'Width of the input field as a percentage',
								'gatherpress'
							)}
						/>
					</PanelBody>
				)}

				{fieldType !== 'hidden' && (
					<PanelColorSettings
						title={__('Colors', 'gatherpress')}
						colorSettings={[
							{
								value: labelTextColor,
								onChange: (value) =>
									setAttributes({ labelTextColor: value }),
								label: __('Label Text', 'gatherpress'),
							},
							...(fieldType === 'radio'
								? [
										{
											value: optionTextColor,
											onChange: (value) =>
												setAttributes({
													optionTextColor: value,
												}),
											label: __(
												'Option Text',
												'gatherpress'
											),
										},
									]
								: []),
							...(fieldType !== 'checkbox' &&
							fieldType !== 'radio'
								? [
										{
											value: fieldTextColor,
											onChange: (value) =>
												setAttributes({
													fieldTextColor: value,
												}),
											label: __(
												'Field Text',
												'gatherpress'
											),
										},
										{
											value: fieldBackgroundColor,
											onChange: (value) =>
												setAttributes({
													fieldBackgroundColor: value,
												}),
											label: __(
												'Field Background',
												'gatherpress'
											),
										},
									]
								: []),
							{
								value: borderColor,
								onChange: (value) =>
									setAttributes({ borderColor: value }),
								label: __('Border', 'gatherpress'),
							},
						]}
					/>
				)}

				{fieldType !== 'hidden' && (
					<PanelBody title={__('Label Styles', 'gatherpress')}>
						<RangeControl
							label={__('Font Size (px)', 'gatherpress')}
							value={labelFontSize}
							onChange={(value) =>
								setAttributes({ labelFontSize: value })
							}
							min={10}
							max={32}
						/>
						<RangeControl
							label={__('Line Height', 'gatherpress')}
							value={labelLineHeight}
							onChange={(value) =>
								setAttributes({ labelLineHeight: value })
							}
							min={1}
							max={3}
							step={0.1}
						/>
					</PanelBody>
				)}

				{fieldType === 'radio' && (
					<PanelBody title={__('Option Styles', 'gatherpress')}>
						<RangeControl
							label={__('Font Size (px)', 'gatherpress')}
							value={optionFontSize}
							onChange={(value) =>
								setAttributes({ optionFontSize: value })
							}
							min={10}
							max={32}
						/>
						<RangeControl
							label={__('Line Height', 'gatherpress')}
							value={optionLineHeight}
							onChange={(value) =>
								setAttributes({ optionLineHeight: value })
							}
							min={1}
							max={3}
							step={0.1}
						/>
					</PanelBody>
				)}

				{fieldType !== 'hidden' && (
					<PanelBody title={__('Input Field Styles', 'gatherpress')}>
						<RangeControl
							label={__('Font Size (px)', 'gatherpress')}
							value={inputFontSize}
							onChange={(value) =>
								setAttributes({ inputFontSize: value })
							}
							min={10}
							max={32}
						/>
						<RangeControl
							label={__('Line Height', 'gatherpress')}
							value={inputLineHeight}
							onChange={(value) =>
								setAttributes({ inputLineHeight: value })
							}
							min={1}
							max={3}
							step={0.1}
						/>
						<RangeControl
							label={__('Border Width (px)', 'gatherpress')}
							value={inputBorderWidth}
							onChange={(value) =>
								setAttributes({ inputBorderWidth: value })
							}
							min={0}
							max={100}
						/>
						<RangeControl
							label={__('Border Radius (px)', 'gatherpress')}
							value={inputBorderRadius}
							onChange={(value) =>
								setAttributes({ inputBorderRadius: value })
							}
							min={0}
							max={100}
						/>
					</PanelBody>
				)}
			</InspectorControls>
		</>
	);
}
