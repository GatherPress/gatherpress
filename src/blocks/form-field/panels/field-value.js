/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';

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
