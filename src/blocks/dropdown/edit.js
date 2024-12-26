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
import { BoxControl, PanelBody, ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid'; // Import UUID library for unique IDs

/**
 * Edit Component
 *
 * @param {Object} props Block properties.
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const [isExpanded, setIsExpanded] = useState(false);

	// Generate a persistent unique ID for the dropdown if not already set
	useEffect(() => {
		if (!attributes.dropdownId) {
			setAttributes({ dropdownId: `dropdown-${uuidv4()}` });
		}
	}, []);

	// Toggle dropdown visibility
	const handleToggle = () => {
		setIsExpanded((prev) => !prev);
	};

	// Ensure dropdown items are toggled correctly in the editor
	useEffect(() => {
		const dropdownItems = document.getElementById(attributes.dropdownId);
		if (dropdownItems) {
			dropdownItems.style.display = isExpanded ? 'block' : 'none';
		}
	}, [isExpanded]);

	// Close dropdown on Escape key
	useEffect(() => {
		const handleKeyDown = (event) => {
			if (event.key === 'Escape' && isExpanded) {
				setIsExpanded(false);
			}
		};
		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [isExpanded]);

	// Sync label with metadata.name
	useEffect(() => {
		wp.data.dispatch('core/block-editor').updateBlockAttributes(attributes.dropdownId, {
			metadata: {
				...attributes.metadata,
				name: attributes.label || __('Dropdown', 'gatherpress'),
			},
		});
	}, [attributes.label]);

	return (
		<div {...blockProps}>
			<InspectorControls>
				{/* Label Settings Panel */}
				<PanelBody title={__('Label Settings', 'gatherpress')} initialOpen={true}>
					<p>
						{__(
							'The dropdown label behaves like a button if the link is empty or set to "#".',
							'gatherpress'
						)}
					</p>
					<PanelColorSettings
						title={__('Label Colors', 'gatherpress')}
						initialOpen={true}
						colorSettings={[
							{
								value: attributes.labelColor,
								onChange: (newColor) => setAttributes({ labelColor: newColor }),
								label: __('Label Color', 'gatherpress'),
								help: __('Choose a color for the dropdown label.', 'gatherpress'),
							},
						]}
					/>
				</PanelBody>

				{/* Dropdown Settings Panel */}
				<PanelBody title={__('Dropdown Settings', 'gatherpress')} initialOpen={false}>
					<PanelColorSettings
						title={__('Dropdown Colors', 'gatherpress')}
						initialOpen={true}
						colorSettings={[
							{
								value: attributes.dropdownBgColor,
								onChange: (newColor) => setAttributes({ dropdownBgColor: newColor }),
								label: __('Dropdown Background Color', 'gatherpress'),
								help: __('Choose a background color for the dropdown items.', 'gatherpress'),
							},
						]}
					/>
					<BoxControl
						label={__('Padding', 'gatherpress')}
						values={attributes.padding}
						onChange={(value) => setAttributes({ padding: value })}
					/>
				</PanelBody>
			</InspectorControls>

			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						icon={isExpanded ? 'no-alt' : 'plus'}
						onClick={handleToggle}
						label={isExpanded ? __('Close Dropdown', 'gatherpress') : __('Open Dropdown', 'gatherpress')}
						title={isExpanded ? __('Close Dropdown', 'gatherpress') : __('Open Dropdown', 'gatherpress')}
						aria-label={isExpanded ? __('Close Dropdown', 'gatherpress') : __('Open Dropdown', 'gatherpress')}
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
				value={attributes.label}
				onChange={(value) => setAttributes({ label: value })}
				placeholder={__('Dropdown Label...', 'gatherpress')}
				style={{
					color: attributes.labelColor,
				}}
				onKeyDown={(event) => {
					if (event.key === 'Enter') {
						event.preventDefault(); // Prevent new line creation
					}
				}}
			/>

			{/* Dropdown Items Container */}
			<div
				id={attributes.dropdownId}
				role="region"
				style={{
					display: isExpanded ? 'block' : 'none',
					backgroundColor: attributes.dropdownBgColor,
					padding: attributes.padding
						? `${attributes.padding.top || 0}px ${attributes.padding.right || 0}px ${attributes.padding.bottom || 0}px ${attributes.padding.left || 0}px`
						: undefined,
				}}
			>
				<InnerBlocks allowedBlocks={['gatherpress/dropdown-item']} />
			</div>
		</div>
	);
};

export default Edit;
