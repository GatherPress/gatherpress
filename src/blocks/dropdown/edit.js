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
	ToolbarGroup,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid';
import { dispatch, select } from '@wordpress/data';

const Edit = ({ attributes, setAttributes, clientId }) => {
	const blockProps = useBlockProps();
	const parentClass = blockProps.className || '';
	const [isExpanded, setIsExpanded] = useState(false);

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

	// Ensure dropdown items inherit styles from the parent block
	useEffect(() => {
		const { updateBlockAttributes } = dispatch('core/block-editor');
		const { getBlockOrder, getBlockAttributes } =
			select('core/block-editor');

		const innerBlockIds = getBlockOrder(clientId);

		innerBlockIds.forEach((blockId) => {
			const innerBlockAttributes = getBlockAttributes(blockId);

			// Only update child attributes if they differ from the parent
			const needsUpdate =
				JSON.stringify(innerBlockAttributes.itemPadding) !==
					JSON.stringify(attributes.itemPadding) ||
				innerBlockAttributes.itemTextColor !==
					attributes.itemTextColor ||
				innerBlockAttributes.itemBgColor !== attributes.itemBgColor;

			if (needsUpdate) {
				updateBlockAttributes(blockId, {
					itemPadding: attributes.itemPadding,
					itemTextColor: attributes.itemTextColor,
					itemBgColor: attributes.itemBgColor,
				});
			}
		});
	}, [
		attributes.itemPadding,
		attributes.itemTextColor,
		attributes.itemBgColor,
		clientId,
	]);

	// Handle changes to item padding and sanitize values
	const handleItemPaddingChange = (newPadding) => {
		const sanitizedPadding = Object.fromEntries(
			Object.entries(newPadding).map(([key, value]) => [
				key,
				isNaN(parseInt(value)) ? 0 : parseInt(value),
			])
		);
		setAttributes({ itemPadding: sanitizedPadding });
	};

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
					]}
				/>

				{/* Dropdown Item Settings */}
				<PanelBody
					title={__('Settings', 'gatherpress')}
					initialOpen={false}
				>
					<BoxControl
						label={__('Item Padding', 'gatherpress')}
						values={attributes.itemPadding}
						onChange={handleItemPaddingChange}
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
				className={`${parentClass}__trigger`}
				value={attributes.label}
				onChange={(value) => setAttributes({ label: value })}
				placeholder={__('Dropdown Label…', 'gatherpress')}
				style={{
					color: attributes.labelColor,
				}}
			/>

			{/* Dropdown Items Container */}
			<div
				id={attributes.dropdownId}
				role="region"
				className={`${parentClass}__menu`}
				style={{
					display: isExpanded ? 'block' : 'none',
					backgroundColor: attributes.itemBgColor,
				}}
			>
				<InnerBlocks allowedBlocks={['gatherpress/dropdown-item']} />
			</div>
		</div>
	);
};

export default Edit;
