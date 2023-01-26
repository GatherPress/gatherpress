/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';
import { getFromGlobal } from '../../helpers/globals';

const Edit = () => {
	const blockProps = useBlockProps();
	const postId = getFromGlobal('post_id');
	const currentUser = getFromGlobal('current_user');

	return (
		<div {...blockProps}>
			<AttendanceSelector
				eventId={postId}
				currentUser={currentUser}
				type={'upcoming'}
			/>
		</div>
	);
};

export default Edit;
