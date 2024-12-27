/**
 * WordPress dependencies
 */
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
	RichText,
} from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalBoxControl as BoxControl,
	PanelBody,
	ToolbarButton,
	RangeControl,
	ToolbarGroup,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid';
import { dispatch, select } from '@wordpress/data';

const Edit = ({ attributes, setAttributes, clientId }) => {
	const blockProps = useBlockProps();
	const [isExpanded, setIsExpanded] = useState(false);
	const {
		itemBgColor,
		itemHoverBgColor,
		itemPadding,
		itemTextColor,
		itemHoverTextColor,
		itemDividerColor,
		itemDividerThickness,
	} = attributes;
	// Generate a persistent unique ID for the dropdown if not already set
	useEffect(() => {
		if (!attributes.dropdownId) {
			const newId = `dropdown-${uuidv4()}`;
			setAttributes({ dropdownId: newId });
		}
	}, [attributes.dropdownId, setAttributes]);

	// Update `metadata.name` with the label value for the List View
	useEffect(() => {
		const currentLabel = attributes.label || __('Dropdown', 'gatherpress');
		const currentMetadata =
			select('core/block-editor').getBlockAttributes(clientId).metadata ||
			{};

		// Only update if the metadata name differs from the current label
		if (currentMetadata.name !== currentLabel) {
			dispatch('core/block-editor').updateBlockAttributes(clientId, {
				metadata: { ...currentMetadata, name: currentLabel },
			});
		}
	}, [attributes.label, clientId]);

	const dropdownStyles = `
		#${attributes.dropdownId} .wp-block-gatherpress-dropdown-item {
			padding: ${itemPadding.top} ${itemPadding.right} ${itemPadding.bottom} ${itemPadding.left};
			color: ${itemTextColor || 'inherit'};
			background-color: ${itemBgColor || 'transparent'};
		}

		#${attributes.dropdownId} .wp-block-gatherpress-dropdown-item:hover {
			color: ${itemHoverTextColor || 'inherit'};
			background-color: ${itemHoverBgColor || 'transparent'};
		}

		#${attributes.dropdownId} .wp-block-gatherpress-dropdown-item:not(:first-child) {
			border-top: ${itemDividerThickness || 1}px solid ${itemDividerColor || 'transparent'};
		}
	`;

	// Toggle dropdown visibility
	const handleToggle = () => {
		setIsExpanded((prev) => !prev);
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelColorSettings
					title={__('Colors', 'gatherpress')}
					colorSettings={[
						{
							value: attributes.labelColor,
							onChange: (newColor) =>
								setAttributes({ labelColor: newColor }),
							label: __('Label Text Color', 'gatherpress'),
						},
						{
							value: attributes.itemTextColor,
							onChange: (value) =>
								setAttributes({ itemTextColor: value }),
							label: __('Item Text Color', 'gatherpress'),
						},
						{
							value: attributes.itemBgColor,
							onChange: (value) =>
								setAttributes({ itemBgColor: value }),
							label: __('Item Background Color', 'gatherpress'),
						},
						{
							value: attributes.itemHoverTextColor,
							onChange: (value) =>
								setAttributes({ itemHoverTextColor: value }),
							label: __('Item Hover Text Color', 'gatherpress'),
						},
						{
							value: attributes.itemHoverBgColor,
							onChange: (value) =>
								setAttributes({ itemHoverBgColor: value }),
							label: __(
								'Item Hover Background Color',
								'gatherpress'
							),
						},
						{
							value: attributes.itemDividerColor,
							onChange: (newColor) =>
								setAttributes({ itemDividerColor: newColor }),
							label: __('Item Divider Color', 'gatherpress'),
						},
						{
							value: attributes.dropdownBorderColor,
							onChange: (value) =>
								setAttributes({ dropdownBorderColor: value }),
							label: __('Dropdown Border Color', 'gatherpress'),
						},
					]}
				/>

				{/* Dropdown Item Settings */}
				<PanelBody
					title={__('Settings', 'gatherpress')}
					initialOpen={false}
				>
					<BoxControl
						label={__('Item Padding', 'gatherpress')}
						values={attributes.itemPadding || 8}
						onChange={(value) =>
							setAttributes({ itemPadding: value })
						}
					/>
					<RangeControl
						label={__('Item Divider Thickness', 'gatherpress')}
						value={attributes.itemDividerThickness || 1}
						onChange={(value) =>
							setAttributes({ itemDividerThickness: value })
						}
						min={0}
						max={10}
					/>
					<RangeControl
						label={__('Dropdown Z-Index', 'gatherpress')}
						value={attributes.dropdownZIndex}
						onChange={(value) =>
							setAttributes({ dropdownZIndex: value })
						}
						min={0}
						max={1000}
					/>
					<RangeControl
						label={__('Dropdown Max Width', 'gatherpress')}
						value={parseInt(attributes.dropdownMaxWidth, 10)}
						onChange={(value) =>
							setAttributes({ dropdownMaxWidth: `${value}px` })
						}
						min={100}
						max={800}
					/>
					<RangeControl
						label={__('Dropdown Border Thickness', 'gatherpress')}
						value={attributes.dropdownBorderThickness || 1}
						onChange={(value) =>
							setAttributes({ dropdownBorderThickness: value })
						}
						min={0}
						max={20}
					/>
					<RangeControl
						label={__('Dropdown Border Radius', 'gatherpress')}
						value={attributes.dropdownBorderRadius}
						onChange={(value) =>
							setAttributes({ dropdownBorderRadius: value })
						}
						min={0}
						max={50}
					/>
				</PanelBody>
			</InspectorControls>

			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={isExpanded ? 'no-alt' : 'plus'}
						onClick={handleToggle}
						label={
							isExpanded
								? __('Close Dropdown', 'gatherpress')
								: __('Open Dropdown', 'gatherpress')
						}
					/>
				</ToolbarGroup>
			</BlockControls>

			{/* Dropdown Label */}
			<RichText
				tagName="a"
				href="#"
				role="button"
				aria-expanded={isExpanded}
				aria-controls={attributes.dropdownId}
				tabIndex={0}
				className="wp-block-gatherpress-dropdown__trigger"
				value={attributes.label}
				onChange={(value) => setAttributes({ label: value })}
				placeholder={__('Dropdown Label…', 'gatherpress')}
				style={{
					color: attributes.labelColor,
				}}
			/>

			{/* Dropdown Items Container */}
			<style>{dropdownStyles}</style>
			<div
				id={attributes.dropdownId}
				role="region"
				className="wp-block-gatherpress-dropdown__menu"
				style={{
					display: isExpanded ? 'block' : 'none',
					backgroundColor: attributes.itemBgColor,
					border: `${attributes.dropdownBorderThickness || 1}px solid ${attributes.dropdownBorderColor || '#000'}`,
					borderRadius: `${attributes.dropdownBorderRadius || 0}px`,
					zIndex: attributes.dropdownZIndex,
					width: attributes.dropdownMaxWidth,
				}}
			>
				<InnerBlocks allowedBlocks={['gatherpress/dropdown-item']} />
			</div>
		</div>
	);
};

export default Edit;
