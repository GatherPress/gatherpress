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
export const getInputStyles = ( fieldType, attributes ) => {
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
	if ( ! [ 'checkbox', 'radio', 'hidden' ].includes( fieldType ) ) {
		styles.cursor = 'text';

		if ( undefined !== inputFontSize ) {
			styles.fontSize = `${ inputFontSize }`;
		}

		if ( undefined !== inputLineHeight ) {
			styles.lineHeight = inputLineHeight;
		}

		if ( fieldTextColor ) {
			styles.color = fieldTextColor;
		}

		if ( fieldBackgroundColor ) {
			styles.backgroundColor = fieldBackgroundColor;
		}

		if ( undefined !== inputPadding ) {
			styles.padding = `${ inputPadding }px`;
		}

		if ( undefined !== inputBorderRadius ) {
			styles.borderRadius = `${ inputBorderRadius }px`;
		}

		if ( undefined !== fieldWidth ) {
			styles.width = `${ fieldWidth }%`;
		}

		if ( undefined !== inputBorderWidth ) {
			styles.borderWidth = `${ inputBorderWidth }px`;
		}

		if ( borderColor ) {
			styles.borderColor = borderColor;
		}
	} else {
		styles.cursor = 'default';
	}

	// Override any other disabled styling.
	if ( [ 'checkbox', 'radio' ].includes( fieldType ) ) {
		styles.opacity = 1;
	}

	// Ensure consistent appearance for readonly inputs.
	if (
		! fieldBackgroundColor &&
		! [ 'checkbox', 'radio', 'hidden' ].includes( fieldType )
	) {
		styles.backgroundColor = 'transparent';
	}

	if ( ! fieldTextColor ) {
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
export const getLabelStyles = ( attributes ) => {
	const { labelTextColor } = attributes;

	const styles = {};

	if ( labelTextColor ) {
		styles.color = labelTextColor;
	}

	styles.cursor = 'text';

	return styles;
};

/**
 * Get label wrapper styles based on attributes.
 *
 * @param {Object} attributes - Block attributes
 * @return {Object} Style object for the label wrapper
 */
export const getLabelWrapperStyles = ( attributes ) => {
	const { labelFontSize, labelLineHeight } = attributes;

	const styles = {};

	if ( undefined !== labelFontSize ) {
		styles.fontSize = `${ labelFontSize }`;
	}

	if ( undefined !== labelLineHeight ) {
		styles.lineHeight = labelLineHeight;
	}

	return styles;
};

/**
 * Get option styles (for radio buttons) based on attributes.
 *
 * @param {Object} attributes - Block attributes
 * @return {Object} Style object for the options
 */
export const getOptionStyles = ( attributes ) => {
	const { optionFontSize, optionLineHeight, optionTextColor } = attributes;

	const styles = {};

	if ( optionFontSize !== undefined ) {
		styles.fontSize = `${ optionFontSize }`;
	}

	if ( optionLineHeight !== undefined ) {
		styles.lineHeight = optionLineHeight;
	}

	if ( optionTextColor ) {
		styles.color = optionTextColor;
	}

	styles.cursor = 'text';

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
	inlineLayout = false,
) => {
	let classes = `${ blockProps.className || '' } gatherpress-form-field--${ fieldType }`;

	// Add inline layout class for text-based fields.
	if (
		inlineLayout &&
		! [ 'checkbox', 'radio', 'hidden', 'textarea' ].includes( fieldType )
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
export default function FieldValue( { fieldType, attributes, setAttributes } ) {
	const { fieldValue } = attributes;

	switch ( fieldType ) {
		case 'email':
			return (
				<TextControl
					label={ __( 'Default Value', 'gatherpress' ) }
					type="email"
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __(
						'Default email address for this field.',
						'gatherpress',
					) }
				/>
			);

		case 'url':
			return (
				<TextControl
					label={ __( 'Default Value', 'gatherpress' ) }
					type="url"
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __( 'Default URL for this field.', 'gatherpress' ) }
				/>
			);

		case 'tel':
			return (
				<TextControl
					label={ __( 'Default Value', 'gatherpress' ) }
					type="tel"
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __(
						'Default telephone number for this field.',
						'gatherpress',
					) }
				/>
			);

		case 'number':
			return (
				<TextControl
					label={ __( 'Default Value', 'gatherpress' ) }
					type="number"
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __(
						'Default number value for this field.',
						'gatherpress',
					) }
				/>
			);

		case 'textarea':
			return (
				<TextareaControl
					label={ __( 'Default Value', 'gatherpress' ) }
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __(
						'Default content for this textarea.',
						'gatherpress',
					) }
					rows={ 3 }
				/>
			);

		case 'checkbox':
			return (
				<ToggleControl
					label={ __( 'Default Checked', 'gatherpress' ) }
					checked={ !! fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __(
						'Whether this checkbox should be checked by default.',
						'gatherpress',
					) }
				/>
			);

		case 'radio':
			// Radio buttons handle their values through radioOptions.
			return null;

		case 'hidden':
			return (
				<TextControl
					label={ __( 'Value', 'gatherpress' ) }
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __( 'The value for this hidden field.', 'gatherpress' ) }
				/>
			);

		default:
			return (
				<TextControl
					label={ __( 'Default Value', 'gatherpress' ) }
					value={ fieldValue }
					onChange={ ( value ) => setAttributes( { fieldValue: value } ) }
					help={ __( 'Default value for this field.', 'gatherpress' ) }
				/>
			);
	}
}
