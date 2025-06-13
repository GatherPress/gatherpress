/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { PanelColorSettings } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	Button,
	Flex,
	FlexItem,
	TextControl,
} from '@wordpress/components';

export default function RadioFieldPanels({ attributes, setAttributes }) {
	const {
		radioOptions = [{ label: '', value: '' }],
		inputBorderWidth,
		labelFontSize,
		labelLineHeight,
		optionFontSize,
		optionLineHeight,
		labelTextColor,
		borderColor,
		optionTextColor,
	} = attributes;

	// Handle radio option changes
	const updateRadioOption = (index, field, value) => {
		const newOptions = [...radioOptions];
		newOptions[index] = { ...newOptions[index], [field]: value };

		if (field === 'label') {
			const cleanValue = value
				.toLowerCase()
				.replace(/[^a-z0-9]+/g, '-')
				.replace(/^-+|-+$/g, '');
			newOptions[index].value = cleanValue || value;
		}

		setAttributes({ radioOptions: newOptions });
	};

	const addRadioOption = () => {
		const newOptions = [...radioOptions, { label: '', value: '' }];
		setAttributes({ radioOptions: newOptions });
	};

	const removeRadioOption = (index) => {
		const newOptions = radioOptions.filter((_, i) => i !== index);
		setAttributes({ radioOptions: newOptions });
	};

	return (
		<>
			<PanelBody title={__('Radio Options', 'gatherpress')}>
				{radioOptions.map((option, index) => (
					<div key={index}>
						<Flex justify="normal" gap="2">
							<FlexItem>
								<TextControl
									label={`${__('Option', 'gatherpress')} ${index + 1}`}
									value={option.label}
									onChange={(value) =>
										updateRadioOption(index, 'label', value)
									}
									help={__(
										'Label and value for this option',
										'gatherpress'
									)}
								/>
							</FlexItem>
							<FlexItem>
								{radioOptions.length > 1 && (
									<Button
										variant="secondary"
										isDestructive
										onClick={() => removeRadioOption(index)}
										style={{ marginTop: '-1rem' }}
										icon="no-alt"
										label={__(
											'Remove option',
											'gatherpress'
										)}
									/>
								)}
							</FlexItem>
						</Flex>
					</div>
				))}
				<Button variant="secondary" onClick={addRadioOption}>
					{__('Add Option', 'gatherpress')}
				</Button>
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
						value: optionTextColor,
						onChange: (value) =>
							setAttributes({ optionTextColor: value }),
						label: __('Option Text', 'gatherpress'),
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

			<PanelBody title={__('Option Styles', 'gatherpress')}>
				<RangeControl
					label={__('Font Size (px)', 'gatherpress')}
					value={optionFontSize}
					onChange={(value) =>
						setAttributes({ optionFontSize: value })
					}
					min={10}
					max={32}
				/>
				<RangeControl
					label={__('Line Height', 'gatherpress')}
					value={optionLineHeight}
					onChange={(value) =>
						setAttributes({ optionLineHeight: value })
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
