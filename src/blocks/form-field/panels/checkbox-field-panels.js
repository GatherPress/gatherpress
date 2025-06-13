/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { PanelColorSettings } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

export default function CheckboxFieldPanels({ attributes, setAttributes }) {
	const {
		inputBorderWidth,
		labelFontSize,
		labelLineHeight,
		labelTextColor,
		borderColor,
	} = attributes;

	return (
		<>
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
					label={__('Border Width (px)', 'gatherpress')}
					value={inputBorderWidth}
					onChange={(value) =>
						setAttributes({ inputBorderWidth: value })
					}
					min={0}
					max={100}
				/>
			</PanelBody>
		</>
	);
}
