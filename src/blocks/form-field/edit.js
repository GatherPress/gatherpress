/**
 * WordPress dependencies.
 */
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
 *
 * @param {Object}   props               The block props.
 * @param {Object}   props.attributes    The block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {JSX.Element} The edit component.
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
