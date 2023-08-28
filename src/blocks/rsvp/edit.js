/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal } from '../../helpers/globals';
import EditCover from '../../components/EditCover';

const Edit = () => {
	const blockProps = useBlockProps();
	const postId = getFromGlobal('post_id');
	const currentUser = getFromGlobal('current_user');

	return (
		<div {...blockProps}>
			<EditCover>
				<Rsvp
					eventId={postId}
					currentUser={currentUser}
					type={'upcoming'}
				/>
			</EditCover>
		</div>
	);
};

export default Edit;
