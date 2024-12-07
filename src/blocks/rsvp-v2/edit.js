/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { useSelect } from '@wordpress/data';
import TEMPLATE from './template';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component defines the edit interface for the GatherPress RSVP block in the block editor.
 * It dynamically manages and updates block attributes based on user input and the status of
 * nested blocks. The component includes:
 * - `InspectorControls` for managing RSVP statuses via a dropdown.
 * - Logic to locate and update text labels of nested `core/button` blocks based on the current RSVP status.
 * - `InnerBlocks` to allow nested content within the RSVP block.
 *
 * The `useEffect` hook ensures that changes to the RSVP status or inner blocks dynamically update
 * the block attributes, ensuring consistent behavior and text labels.
 *
 * @param {Object}   props               The props passed to the component.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the RSVP block.
 */
const Edit = ({ setAttributes }) => {
	const blockProps = useBlockProps();
	const [status, setStatus] = useState('no_status');

	const clientId = useSelect(
		(select) => select('core/block-editor').getSelectedBlock()?.clientId,
		[]
	);

	const innerBlocks = useSelect(
		(select) =>
			clientId ? select('core/block-editor').getBlocks(clientId) : [],
		[clientId]
	);

	const locateButtonBlock = (blocks) => {
		for (const block of blocks) {
			if (block.name === 'core/button') {
				return block;
			}
			if (block.innerBlocks.length > 0) {
				const found = locateButtonBlock(block.innerBlocks);
				if (found) {
					return found;
				}
			}
		}
		return null;
	};

	const buttonBlock = locateButtonBlock(innerBlocks);

	useEffect(() => {
		if (buttonBlock) {
			const buttonText = buttonBlock.attributes.text;

			switch (status) {
				case 'no_status':
					setAttributes({ noStatusLabel: buttonText });
					break;
				case 'attending':
					setAttributes({ attendingLabel: buttonText });
					break;
				case 'waiting_list':
					setAttributes({ waitingListLabel: buttonText });
					break;
				case 'not_attending':
					setAttributes({ notAttendingLabel: buttonText });
					break;
			}
		}
	}, [buttonBlock, setAttributes, status]);

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						label={__('Status', 'gatherpress')}
						value={status}
						options={[
							{
								label: __('No Status', 'gatherpress'),
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
						]}
						onChange={(newStatus) => setStatus(newStatus)}
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
