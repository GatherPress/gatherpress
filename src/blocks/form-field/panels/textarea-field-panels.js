/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	RangeControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

export default function TextareaFieldPanels({ attributes, setAttributes }) {
	const {
		placeholder,
		minValue,
		maxValue,
		inputFontSize,
		inputLineHeight,
		inputBorderWidth,
		inputBorderRadius,
		labelFontSize,
		labelLineHeight,
		sideBySideLayout,
		fieldWidth,
		labelTextColor,
		fieldTextColor,
		fieldBackgroundColor,
		borderColor,
	} = attributes;

	return (
		<>
			<PanelBody title={__('Field Settings', 'gatherpress')}>
				<TextControl
					label={__('Placeholder', 'gatherpress')}
					value={placeholder}
					onChange={(value) => setAttributes({ placeholder: value })}
					help={__(
						'Placeholder text shown inside the field',
						'gatherpress'
					)}
				/>

				<NumberControl
					label={__('Minimum Length', 'gatherpress')}
					value={minValue}
					onChange={(value) => setAttributes({ minValue: value })}
					min={0}
					help={__(
						'Minimum number of characters required',
						'gatherpress'
					)}
				/>
				<NumberControl
					label={__('Maximum Length', 'gatherpress')}
					value={maxValue}
					onChange={(value) => setAttributes({ maxValue: value })}
					min={0}
					help={__(
						'Maximum number of characters allowed',
						'gatherpress'
					)}
				/>
			</PanelBody>

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
					onChange={(value) => setAttributes({ fieldWidth: value })}
					min={0}
					max={100}
					help={__(
						'Width of the input field as a percentage',
						'gatherpress'
					)}
				/>
			</PanelBody>

			<PanelColorSettings
				title={__('Colors', 'gatherpress')}
				colorSettings={[
					{
						value: labelTextColor,
						onChange: (value) =>
							setAttributes({ labelTextColor: value }),
						label: __('Label Text', 'gatherpress'),
					},
					{
						value: fieldTextColor,
						onChange: (value) =>
							setAttributes({ fieldTextColor: value }),
						label: __('Field Text', 'gatherpress'),
					},
					{
						value: fieldBackgroundColor,
						onChange: (value) =>
							setAttributes({ fieldBackgroundColor: value }),
						label: __('Field Background', 'gatherpress'),
					},
					{
						value: borderColor,
						onChange: (value) =>
							setAttributes({ borderColor: value }),
						label: __('Border', 'gatherpress'),
					},
				]}
			/>

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
		</>
	);
}
