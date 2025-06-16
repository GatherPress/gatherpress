/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Get input styles based on field type and attributes.
 *
 * @param {string} fieldType  - The type of field (text, checkbox, etc.)
 * @param {Object} attributes - Block attributes
 * @return {Object} Style object for the input
 */
export const getInputStyles = (fieldType, attributes) => {
	const {
		inputFontSize,
		inputLineHeight,
		inputPadding,
		inputBorderWidth,
		inputBorderRadius,
		fieldWidth,
		fieldTextColor,
		fieldBackgroundColor,
		borderColor,
	} = attributes;

	const styles = {};

	// Override browser defaults for readonly inputs in editor.
	styles.opacity = 1;

	// Font and text styles (for text-based inputs).
	if (['text', 'email', 'url', 'number', 'textarea'].includes(fieldType)) {
		styles.cursor = 'text';

		if (inputFontSize !== undefined) {
			styles.fontSize = `${inputFontSize}`;
		}

		if (inputLineHeight !== undefined) {
			styles.lineHeight = inputLineHeight;
		}

		if (fieldTextColor) {
			styles.color = fieldTextColor;
		}

		if (fieldBackgroundColor) {
			styles.backgroundColor = fieldBackgroundColor;
		}

		if (inputPadding !== undefined) {
			styles.padding = `${inputPadding}px`;
		}

		if (inputBorderRadius !== undefined) {
			styles.borderRadius = `${inputBorderRadius}px`;
		}

		if (fieldWidth !== undefined) {
			styles.width = `${fieldWidth}%`;
		}
	} else {
		styles.cursor = 'default';
	}

	// Border styles (for all input types).
	if (inputBorderWidth !== undefined) {
		styles.borderWidth = `${inputBorderWidth}px`;
	}

	if (borderColor) {
		styles.borderColor = borderColor;
	}

	// Override any other disabled styling.
	if (['checkbox', 'radio'].includes(fieldType)) {
		styles.opacity = 1;
	}

	// Ensure consistent appearance for readonly inputs.
	if (
		!fieldBackgroundColor &&
		['text', 'email', 'url', 'number', 'textarea'].includes(fieldType)
	) {
		styles.backgroundColor = 'transparent';
	}

	if (!fieldTextColor) {
		styles.color = 'inherit';
	}

	return styles;
};

/**
 * Get label styles based on attributes.
 *
 * @param {Object} attributes - Block attributes
 * @return {Object} Style object for the label
 */
export const getLabelStyles = (attributes) => {
	const { labelFontSize, labelLineHeight, labelTextColor } = attributes;

	const styles = {};

	if (labelFontSize !== undefined) {
		styles.fontSize = `${labelFontSize}`;
	}
	if (labelLineHeight !== undefined) {
		styles.lineHeight = labelLineHeight;
	}
	if (labelTextColor) {
		styles.color = labelTextColor;
	}

	return styles;
};

/**
 * Get option styles (for radio buttons) based on attributes.
 *
 * @param {Object} attributes - Block attributes
 * @return {Object} Style object for the options
 */
export const getOptionStyles = (attributes) => {
	const { optionFontSize, optionLineHeight, optionTextColor } = attributes;

	const styles = {};

	if (optionFontSize !== undefined) {
		styles.fontSize = `${optionFontSize}`;
	}
	if (optionLineHeight !== undefined) {
		styles.lineHeight = optionLineHeight;
	}
	if (optionTextColor) {
		styles.color = optionTextColor;
	}

	return styles;
};

/**
 * Get wrapper classes for the field.
 *
 * @param {string}  fieldType    - The type of field
 * @param {Object}  blockProps   - Block props from useBlockProps
 * @param {boolean} inlineLayout - Whether to use side-by-side layout
 * @return {string} CSS classes for the wrapper
 */
export const getWrapperClasses = (
	fieldType,
	blockProps,
	inlineLayout = false
) => {
	let classes = `${blockProps.className || ''} gatherpress-field-type-${fieldType}`;

	// Add inline layout class for text-based fields.
	if (
		inlineLayout &&
		['text', 'email', 'url', 'number', 'textarea'].includes(fieldType)
	) {
		classes += ' gatherpress-inline-layout';
	}

	return classes.trim();
};

/**
 * Renders the appropriate field value control based on field type.
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.fieldType     - The type of form field.
 * @param {Object}   props.attributes    - Block attributes object.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @return {JSX.Element|null} The field value control component or null.
 */
export default function FieldValue({ fieldType, attributes, setAttributes }) {
	const { fieldValue } = attributes;

	switch (fieldType) {
		case 'email':
			return (
				<TextControl
					label={__('Default Value', 'gatherpress')}
					type="email"
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__(
						'Default email address for this field',
						'gatherpress'
					)}
				/>
			);

		case 'url':
			return (
				<TextControl
					label={__('Default Value', 'gatherpress')}
					type="url"
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__('Default URL for this field', 'gatherpress')}
				/>
			);

		case 'number':
			return (
				<TextControl
					label={__('Default Value', 'gatherpress')}
					type="number"
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__(
						'Default number value for this field',
						'gatherpress'
					)}
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					label={__('Default Value', 'gatherpress')}
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__(
						'Default content for this textarea',
						'gatherpress'
					)}
					rows={3}
				/>
			);

		case 'checkbox':
			return (
				<ToggleControl
					label={__('Default Checked', 'gatherpress')}
					checked={!!fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__(
						'Whether this checkbox should be checked by default',
						'gatherpress'
					)}
				/>
			);

		case 'radio':
			// Radio buttons handle their values through radioOptions
			return null;

		case 'hidden':
			return (
				<TextControl
					label={__('Value', 'gatherpress')}
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__('The value for this hidden field', 'gatherpress')}
				/>
			);

		case 'text':
		default:
			return (
				<TextControl
					label={__('Default Value', 'gatherpress')}
					value={fieldValue}
					onChange={(value) => setAttributes({ fieldValue: value })}
					help={__('Default value for this field', 'gatherpress')}
				/>
			);
	}
}
