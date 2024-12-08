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
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

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
	const { serializedInnerBlocks = '{}' } = attributes;
	const [status, setStatus] = useState('no_status');
	const blockProps = useBlockProps();
	const { replaceInnerBlocks } = useDispatch(blockEditorStore);

	// Get the current inner blocks
	const innerBlocks = useSelect(
		(select) => select(blockEditorStore).getBlocks(clientId),
		[clientId]
	);

	// Save the provided inner blocks to the serializedInnerBlocks attribute
	const saveInnerBlocks = (state, blocks) => {
		const currentSerializedBlocks = JSON.parse(
			serializedInnerBlocks || '{}'
		);
		const updatedBlocks = {
			...currentSerializedBlocks,
			[state]: blocks,
		};

		setAttributes({
			serializedInnerBlocks: JSON.stringify(updatedBlocks),
		});
	};

	// Load inner blocks for a given state
	const loadInnerBlocksForState = useCallback(
		(state) => {
			const savedBlocks = JSON.parse(serializedInnerBlocks || '{}')[
				state
			];
			if (savedBlocks && savedBlocks.length > 0) {
				replaceInnerBlocks(clientId, savedBlocks);
			}
		},
		[clientId, replaceInnerBlocks, serializedInnerBlocks]
	);

	// Handle status change: save current inner blocks and load new ones
	const handleStatusChange = (newStatus) => {
		saveInnerBlocks(status, innerBlocks); // Save current inner blocks before switching state
		setStatus(newStatus); // Update the state
		loadInnerBlocksForState(newStatus); // Load blocks for the new state
	};

	// On initial render, ensure correct blocks are loaded
	useEffect(() => {
		loadInnerBlocksForState(status);
	}, [status, loadInnerBlocksForState]);

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						label={__('RSVP Status', 'gatherpress')}
						value={status}
						options={[
							{
								label: __(
									'No Status (User has not responded)',
									'gatherpress'
								),
								value: 'no_status',
							},
							{
								label: __(
									'Attending (User is confirmed)',
									'gatherpress'
								),
								value: 'attending',
							},
							{
								label: __(
									'Waiting List (Pending confirmation)',
									'gatherpress'
								),
								value: 'waiting_list',
							},
							{
								label: __(
									'Not Attending (User declined)',
									'gatherpress'
								),
								value: 'not_attending',
							},
						]}
						onChange={handleStatusChange}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks template={TEMPLATE} />
			</div>
		</>
	);
};

export default Edit;
