/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
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
	const { fieldType, fieldName, label, placeholder, required } = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<div {...blockProps}>
				{label && (
					<div>
						{label}
						{required && <span className="required">*</span>}
					</div>
				)}
				<input
					type={fieldType}
					name={fieldName}
					placeholder={placeholder}
					required={required}
					disabled // Disabled in editor
				/>
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
						]}
						onChange={(value) =>
							setAttributes({ fieldType: value })
						}
					/>

					<TextControl
						label={__('Label', 'gatherpress')}
						value={label}
						onChange={(value) => setAttributes({ label: value })}
						help={__(
							'The label for this form field',
							'gatherpress'
						)}
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

					<ToggleControl
						label={__('Required', 'gatherpress')}
						checked={required}
						onChange={(value) => setAttributes({ required: value })}
						help={__('Make this field required', 'gatherpress')}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
