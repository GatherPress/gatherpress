/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal } from '../../helpers/globals';
import EditCover from '../../components/EditCover';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect, dispatch, select } from '@wordpress/data';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to respond to the RSVP for the associated event.
 * The component includes the RSVP component and passes the event ID, current user,
 * and type of RSVP as props.
 *
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const {
		noStatusLabel,
		attendingLabel,
		waitingListLabel,
		notAttendingLabel,
	} = attributes;
	const [status, setStatus] = useState('no_status');
	const TEMPLATE = [
		[
			'core/buttons',
			{
				align: 'center',
				layout: { type: 'flex', justifyContent: 'center' },
			},
			[
				[
					'core/button',
					{
						// text: initialLabel,
						text: __('RSVP', 'gatherpress'),
						tagName: 'button',
						className: 'gatherpress-rsvp--js-open-modal',
					},
				],
			],
		],
		[
			'core/paragraph',
			{
				content: __('Attending', 'gatherpress'),
			},
		],
		[
			'gatherpress/modal',
			{ className: 'gatherpress-rsvp-modal' },
			[
				[
					'core/heading',
					{
						level: 3,
						content: __('Update your RSVP', 'gatherpress'),
					},
				],
				[
					'core/paragraph',
					{
						content: __(
							'To set or change your attending status, simply click the <strong>Not Attending</strong> button below.',
							'gatherpress'
						),
					},
				],
				[
					'core/buttons',
					{
						align: 'left',
						layout: { type: 'flex', justifyContent: 'flex-start' },
					},
					[
						[
							'core/button',
							{
								text: __('Attend', 'gatherpress'),
								tagName: 'button',
								className:
									'gatherpress-rsvp--js-status-attending',
							},
						],
						[
							'core/button',
							{
								text: __('Close', 'gatherpress'),
								tagName: 'button',
								className: 'gatherpress-rsvp--js-close-modal',
							},
						],
					],
				],
			],
		],
	];

	// Get clientId of the current block to target the InnerBlocks within it
	const clientId = useSelect(
		(select) => select('core/block-editor').getSelectedBlock()?.clientId,
		[]
	);

	// Get InnerBlocks within the 'gatherpress/rsvp-v2' block
	const innerBlocks = useSelect(
		(select) =>
			clientId ? select('core/block-editor').getBlocks(clientId) : [],
		[clientId]
	);

	// Helper function to recursively search for the core/button block
	const findButtonBlock = (blocks) => {
		for (const block of blocks) {
			if (block.name === 'core/button') {
				return block;
			}
			if (block.innerBlocks.length > 0) {
				const found = findButtonBlock(block.innerBlocks);
				if (found) {
					return found;
				}
			}
		}
		return null;
	};

	const buttonBlock = findButtonBlock(innerBlocks);

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
	}, [buttonBlock]);

	useEffect(() => {
		let newLabel = '';

		switch (status) {
			case 'no_status':
				newLabel = noStatusLabel;
				break;
			case 'attending':
				newLabel = attendingLabel;
				break;
			case 'waiting_list':
				newLabel = waitingListLabel;
				break;
			case 'not_attending':
				newLabel = notAttendingLabel;
				break;
		}

		if (buttonBlock) {
			dispatch('core/block-editor').updateBlockAttributes(
				buttonBlock.clientId,
				{
					text: newLabel,
				}
			);
		}
	}, [status]);

	useEffect(() => {});

	// Use `useSelect` to get the currently selected block.
	const selectedBlock = useSelect((select) => {
		return select('core/block-editor').getSelectedBlock();
	}, []); // Empty dependency array to call it once on mount

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
				<InnerBlocks
					template={TEMPLATE}
					// templateLock="all"
					// renderAppender={false}
				/>
			</div>
		</>
	);
};
export default Edit;
