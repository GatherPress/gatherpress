/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { FontSizePicker, PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	BaseControl,
} from '@wordpress/components';

export default function DefaultFieldPanels({ attributes, setAttributes }) {
	const {
		inputFontSize,
		inputLineHeight,
		inputBorderWidth,
		inputBorderRadius,
		labelFontSize,
		labelLineHeight,
		inlineLayout,
		fieldWidth,
		labelTextColor,
		fieldTextColor,
		fieldBackgroundColor,
		borderColor,
	} = attributes;

	return (
		<>
			<PanelBody title={__('Layout Settings', 'gatherpress')}>
				<ToggleControl
					label={__('Inline Layout', 'gatherpress')}
					checked={inlineLayout}
					onChange={(value) => setAttributes({ inlineLayout: value })}
					help={__(
						'Display label and input on the same line.',
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

			<PanelBody title={__('Label Styles', 'gatherpress')}>
				<BaseControl __nextHasNoMarginBottom={true}>
					<FontSizePicker
						withReset={true}
						size="__unstable-large"
						__nextHasNoMarginBottom
						onChange={(value) =>
							setAttributes({ labelFontSize: value })
						}
						value={labelFontSize}
					/>
				</BaseControl>
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
				<BaseControl __nextHasNoMarginBottom={true}>
					<FontSizePicker
						withReset={true}
						size="__unstable-large"
						__nextHasNoMarginBottom
						onChange={(value) =>
							setAttributes({ inputFontSize: value })
						}
						value={inputFontSize}
					/>
				</BaseControl>
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
		</>
	);
}
