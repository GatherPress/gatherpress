/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import RsvpManager from './rsvp-manager';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * @param {Object} root0          - The props object passed to the component.
 * @param {string} root0.clientId - The block client ID.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ({ clientId }) => {
	const blockProps = useBlockProps();
	const [editMode, setEditMode] = useState(false);
	const [showEmptyRsvpMessage, setShowEmptyRsvpMessage] = useState(false);
	const [defaultStatus, setDefaultStatus] = useState('attending');

	const innerBlocks = useSelect(
		(select) => select('core/block-editor').getBlocks(clientId),
		[clientId]
	);

	useEffect(() => {
		innerBlocks.forEach((block) => {
			const blockElement = global.document.getElementById(
				`block-${block.clientId}`
			);

			if (blockElement) {
				const isEmptyRsvpBlock = block.attributes?.className?.includes(
					'gatherpress--empty-rsvp'
				);

				if (showEmptyRsvpMessage && isEmptyRsvpBlock) {
					blockElement.style.display = '';
				} else if (!showEmptyRsvpMessage && isEmptyRsvpBlock) {
					blockElement.style.display = 'none';
				} else if (showEmptyRsvpMessage && !isEmptyRsvpBlock) {
					blockElement.style.display = 'none';
				} else {
					blockElement.style.display = '';
				}
			}
		});
	}, [showEmptyRsvpMessage, innerBlocks, editMode]);

	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};

	const TEMPLATE = [
		[
			'core/group',
			{
				layout: {
					type: 'grid',
					columns: 3,
					justifyContent: 'center',
					alignContent: 'space-around',
					minimumColumnWidth: '8rem',
				},
			},
			[['gatherpress/rsvp-template', {}]],
		],
		[
			'core/group',
			{
				metadata: {
					name: __('Empty RSVP', 'gatherpress'),
				},
				className: 'gatherpress--empty-rsvp',
			},
			[
				[
					'core/paragraph',
					{
						content: __(
							'No one is attending this event yet.',
							'gatherpress'
						),
					},
				],
			],
		],
	];

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody>
					<ToggleControl
						label={__('Show Empty RSVP Block', 'gatherpress')}
						checked={showEmptyRsvpMessage}
						onChange={(value) => setShowEmptyRsvpMessage(value)}
						help={__(
							'Toggle to show or hide the Empty RSVP block. When shown, other blocks are hidden.',
							'gatherpress'
						)}
					/>
				</PanelBody>
			</InspectorControls>
			<BlockControls>
				<ToolbarGroup>
					<ToolbarButton
						label={__('Edit', 'gatherpress')}
						text={
							editMode
								? __('Preview', 'gatherpress')
								: __('Edit', 'gatherpress')
						}
						onClick={onEditClick}
					/>
				</ToolbarGroup>
			</BlockControls>
			{editMode && (
				<RsvpManager
					defaultStatus={defaultStatus}
					setDefaultStatus={setDefaultStatus}
				/>
			)}
			{!editMode && <InnerBlocks template={TEMPLATE} />}
		</div>
	);
};

export default Edit;
