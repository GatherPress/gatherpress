/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';
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
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<EditCover>
				<RsvpResponse />
			</EditCover>
		</div>
	);
};

export default Edit;
