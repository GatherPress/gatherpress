/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
} from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpManager from './rsvp-manager';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component defines the edit interface for the GatherPress RSVP block in the block editor.
 * It renders an Inspector Controls panel for additional settings and a structured layout using
 * `InnerBlocks` with a predefined template. The block is configured to support dynamic content
 * like RSVP templates displayed within a grid layout.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = () => {
	const blockProps = useBlockProps();
	const [editMode, setEditMode] = useState(false);
	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};
	const [defaultStatus, setDefaultStatus] = useState('attending');

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
	];

	return (
		<div {...blockProps}>
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
