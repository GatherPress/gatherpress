/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	RangeControl,
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
		inputFontSize,
		inputLineHeight,
		inputBorderWidth,
		inputBorderRadius,
	} = attributes;

	const blockProps = useBlockProps();

	return (
		<>
			<div {...blockProps}>
				<RichText
					tagName="label"
					placeholder={__('Add label…', 'gatherpress')}
					value={label}
					onChange={(value) => setAttributes({ label: value })}
					allowedFormats={[]}
				/>
				{required && <span className="required">*</span>}
				<input
					style={{
						fontSize:
							inputFontSize !== undefined
								? `${inputFontSize}px`
								: undefined,
						lineHeight:
							inputLineHeight !== undefined
								? inputLineHeight
								: undefined,
						borderWidth:
							inputBorderWidth !== undefined
								? `${inputBorderWidth}px`
								: undefined,
						borderRadius:
							inputBorderRadius !== undefined
								? `${inputBorderRadius}px`
								: undefined,
					}}
					type={fieldType}
					name={fieldName}
					value={placeholder || ''}
					onChange={(e) =>
						setAttributes({ placeholder: e.target.value })
					}
					placeholder={__('Enter placeholder text…', 'gatherpress')}
					required={required}
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
						max={10}
					/>
					<RangeControl
						label={__('Border Radius (px)', 'gatherpress')}
						value={inputBorderRadius}
						onChange={(value) =>
							setAttributes({ inputBorderRadius: value })
						}
						min={0}
						max={20}
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
