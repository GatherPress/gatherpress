/**
 * WordPress dependencies.
 */
import { useBlockProps, BlockControls } from '@wordpress/block-editor';
import { ToolbarGroup, ToolbarButton } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';
import RsvpResponseEdit from '../../components/RsvpResponseEdit';
import EditCover from '../../components/EditCover';

/**
 * Edit component for the GatherPress RSVP Response block.
 *
 * This component renders the edit view of the GatherPress RSVP Response block.
 * It provides an interface for users to view and manage RSVP responses.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = () => {
	const isAdmin = wp.data.select('core').canUser('create', 'posts');
	const blockProps = useBlockProps();

	const [editMode, setEditMode] = useState(false);

	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};

	return (
		<div {...blockProps}>
			{editMode && <RsvpResponseEdit />}
			{!editMode && (
				<EditCover>
					<RsvpResponse />
				</EditCover>
			)}
			{isAdmin && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							label="Edit Attendees"
							text={
								editMode
									? 'Preview Attendees'
									: 'Edit Attendees'
							}
							onClick={onEditClick}
						/>
					</ToolbarGroup>
				</BlockControls>
			)}
		</div>
	);
};

export default Edit;
