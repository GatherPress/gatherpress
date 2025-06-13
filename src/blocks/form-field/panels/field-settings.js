/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

// Import specific panels
import DefaultFieldPanels from './default-field-panels';
import RadioFieldPanels from './radio-field-panels';
import CheckboxFieldPanels from './checkbox-field-panels';
import TextareaFieldPanels from './textarea-field-panels';
import FieldValue from './field-value';

export default function FieldSettingsPanel({ attributes, setAttributes }) {
	const { fieldType, fieldName, required } = attributes;

	// Get field-specific panels
	const getFieldPanels = () => {
		const commonProps = { attributes, setAttributes };

		switch (fieldType) {
			case 'radio':
				return <RadioFieldPanels {...commonProps} />;
			case 'checkbox':
				return <CheckboxFieldPanels {...commonProps} />;
			case 'textarea':
				return <TextareaFieldPanels {...commonProps} />;
			case 'text':
			case 'email':
			case 'url':
			case 'number':
			default:
				return <DefaultFieldPanels {...commonProps} />;
		}
	};

	return (
		<InspectorControls>
			<PanelBody title={__('Field Settings', 'gatherpress')}>
				<SelectControl
					label={__('Field Type', 'gatherpress')}
					value={fieldType}
					options={[
						{ label: __('Text', 'gatherpress'), value: 'text' },
						{ label: __('Email', 'gatherpress'), value: 'email' },
						{ label: __('URL', 'gatherpress'), value: 'url' },
						{ label: __('Number', 'gatherpress'), value: 'number' },
						{
							label: __('Textarea', 'gatherpress'),
							value: 'textarea',
						},
						{
							label: __('Checkbox', 'gatherpress'),
							value: 'checkbox',
						},
						{ label: __('Radio', 'gatherpress'), value: 'radio' },
						{ label: __('Hidden', 'gatherpress'), value: 'hidden' },
					]}
					onChange={(value) => {
						setAttributes({
							fieldType: value,
							fieldValue: '', // Reset fieldValue when type changes.
						});
					}}
				/>
				<TextControl
					label={__('Field Name', 'gatherpress')}
					value={fieldName}
					onChange={(value) => setAttributes({ fieldName: value })}
					help={__(
						'The name attribute for the form field',
						'gatherpress'
					)}
				/>
				<FieldValue
					fieldType={fieldType}
					attributes={attributes}
					setAttributes={setAttributes}
				/>
				{fieldType !== 'hidden' && (
					<ToggleControl
						label={__('Required', 'gatherpress')}
						checked={required}
						onChange={(value) => setAttributes({ required: value })}
						help={__('Make this field required', 'gatherpress')}
					/>
				)}
			</PanelBody>
			{getFieldPanels()}
		</InspectorControls>
	);
}
