/**
 * Get input styles based on field type and attributes
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
 * Get label styles based on attributes
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
 * Get option styles (for radio buttons) based on attributes
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
 * Get wrapper classes for the field
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

	// Add side-by-side class for text-based fields
	if (
		inlineLayout &&
		['text', 'email', 'url', 'number', 'textarea'].includes(fieldType)
	) {
		classes += ' gatherpress-inline-layout';
	}

	return classes.trim();
};
