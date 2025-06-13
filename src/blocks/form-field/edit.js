/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

// Import field type components
import DefaultField from './types/default';
import RadioField from './types/radio';
import CheckboxField from './types/checkbox';
import TextareaField from './types/textarea';
import HiddenField from './types/hidden';

// Import panels
import FieldSettingsPanel from './panels/field-settings';

/**
 * Edit component for the Form Field block.
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
export default function Edit({ attributes, setAttributes }) {
	const { fieldType } = attributes;
	const blockProps = useBlockProps();

	// Generate field name from label
	const generateFieldName = (labelValue) => {
		return labelValue
			.toLowerCase()
			.trim()
			.replace(/[^a-z0-9\s]/g, '')
			.replace(/\s+/g, '_')
			.replace(/^_+|_+$/g, '');
	};

	// Get the appropriate field component
	const getFieldComponent = () => {
		const commonProps = {
			attributes,
			setAttributes,
			blockProps,
			generateFieldName,
		};

		switch (fieldType) {
			case 'radio':
				return <RadioField {...commonProps} />;
			case 'checkbox':
				return <CheckboxField {...commonProps} />;
			case 'textarea':
				return <TextareaField {...commonProps} />;
			case 'hidden':
				return <HiddenField {...commonProps} />;
			case 'text':
			case 'email':
			case 'url':
			case 'number':
			default:
				return <DefaultField {...commonProps} />;
		}
	};

	return (
		<>
			{getFieldComponent()}
			<FieldSettingsPanel
				attributes={attributes}
				setAttributes={setAttributes}
			/>
		</>
	);
}
