/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { FontSizePicker, PanelColorSettings } from '@wordpress/block-editor';
import { BaseControl, PanelBody, RangeControl } from '@wordpress/components';

/**
 * Renders styling panels for checkbox form fields.
 *
 * @param {Object}   props               - Component props.
 * @param {Object}   props.attributes    - Block attributes object.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @return {JSX.Element} The checkbox field styling panels.
 */
export default function CheckboxFieldPanels({ attributes, setAttributes }) {
	const {
		inputBorderWidth,
		labelFontSize,
		labelLineHeight,
		labelTextColor,
		requiredTextColor,
		borderColor,
	} = attributes;

	return (
		<>
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
				<RangeControl
					label={__('Border Width (px)', 'gatherpress')}
					value={inputBorderWidth}
					onChange={(value) =>
						setAttributes({ inputBorderWidth: value })
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
						value: requiredTextColor,
						onChange: (value) =>
							setAttributes({ requiredTextColor: value }),
						label: __('Required Text', 'gatherpress'),
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
