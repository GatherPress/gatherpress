/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, parse, serialize } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import TEMPLATES from './templates';

/**
 * Helper function to convert a template to blocks.
 *
 * @param {Array} template The block template structure.
 * @return {Array} Array of blocks created from the template.
 */
function templateToBlocks(template) {
	return template.map(([name, attributes, innerBlocks]) => {
		return createBlock(
			name,
			attributes,
			templateToBlocks(innerBlocks || [])
		);
	});
}

/**
 * Edit component for the GatherPress RSVP block.
 *
 * @param {Object}   props               The props passed to the component.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      The unique ID of the block instance.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the RSVP block.
 */
const Edit = ({ attributes, setAttributes, clientId }) => {
	const { serializedInnerBlocks = '{}', selectedStatus } = attributes;
	const blockProps = useBlockProps();
	const { replaceInnerBlocks } = useDispatch(blockEditorStore);

	// Get the current inner blocks
	const innerBlocks = useSelect(
		(select) => select(blockEditorStore).getBlocks(clientId),
		[clientId]
	);

	// Save the provided inner blocks to the serializedInnerBlocks attribute
	const saveInnerBlocks = useCallback(
		(state, newState, blocks) => {
			const currentSerializedBlocks = JSON.parse(
				serializedInnerBlocks || '{}'
			);

			// Encode the serialized content for safe use in HTML attributes
			const sanitizedSerialized = serialize(blocks);

			const updatedBlocks = {
				...currentSerializedBlocks,
				[state]: sanitizedSerialized,
			};

			delete updatedBlocks[newState];

			setAttributes({
				serializedInnerBlocks: JSON.stringify(updatedBlocks),
			});
		},
		[serializedInnerBlocks, setAttributes]
	);

	// Load inner blocks for a given state
	const loadInnerBlocksForState = useCallback(
		(state) => {
			const savedBlocks = JSON.parse(serializedInnerBlocks || '{}')[
				state
			];
			if (savedBlocks && savedBlocks.length > 0) {
				replaceInnerBlocks(clientId, parse(savedBlocks, {}));
			}
		},
		[clientId, replaceInnerBlocks, serializedInnerBlocks]
	);

	// Handle status change: save current inner blocks and load new ones
	const handleStatusChange = (newStatus) => {
		loadInnerBlocksForState(newStatus); // Load blocks for the new state
		setAttributes({
			selectedStatus: newStatus,
		}); // Update the state
		saveInnerBlocks(selectedStatus, newStatus, innerBlocks); // Save current inner blocks before switching state
	};

	// Hydrate inner blocks for all statuses if not set
	useEffect(() => {
		const hydrateInnerBlocks = () => {
			const currentSerializedBlocks = JSON.parse(
				serializedInnerBlocks || '{}'
			);

			const updatedBlocks = Object.keys(TEMPLATES).reduce(
				(updatedSerializedBlocks, templateKey) => {
					if (currentSerializedBlocks[templateKey]) {
						updatedSerializedBlocks[templateKey] =
							currentSerializedBlocks[templateKey];

						return updatedSerializedBlocks;
					}

					if (templateKey !== selectedStatus) {
						const blocks = templateToBlocks(TEMPLATES[templateKey]);

						updatedSerializedBlocks[templateKey] =
							serialize(blocks);
					}

					return updatedSerializedBlocks;
				},
				{ ...currentSerializedBlocks }
			);

			setAttributes({
				serializedInnerBlocks: JSON.stringify(updatedBlocks),
			});
		};

		// Adding a setTimeout with 0ms delay pushes execution to the end of the event queue,
		// ensuring WordPress has properly initialized the post state before we attempt to
		// hydrate inner blocks. This prevents false "new post" detection that could interfere
		// with block initialization.
		setTimeout(() => {
			hydrateInnerBlocks();
		}, 0);
	}, [serializedInnerBlocks, setAttributes, selectedStatus]);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('RSVP Block Settings', 'gatherpress')}>
					<p>
						{__(
							'Select an RSVP status to edit how this block appears for users with that status.',
							'gatherpress'
						)}
					</p>
					<SelectControl
						label={__('Edit Block Status', 'gatherpress')}
						value={selectedStatus}
						options={[
							{
								label: __(
									'No Response (Default)',
									'gatherpress'
								),
								value: 'no_status',
							},
							{
								label: __('Attending', 'gatherpress'),
								value: 'attending',
							},
							{
								label: __('Waiting List', 'gatherpress'),
								value: 'waiting_list',
							},
							{
								label: __('Not Attending', 'gatherpress'),
								value: 'not_attending',
							},
							{
								label: __('Past Event', 'gatherpress'),
								value: 'past',
							},
						]}
						onChange={handleStatusChange}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks template={TEMPLATES[selectedStatus]} />
			</div>
		</>
	);
};

export default Edit;
