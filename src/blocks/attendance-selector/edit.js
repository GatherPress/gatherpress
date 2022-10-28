/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';

const Edit = () => {
	const blockProps = useBlockProps();
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div { ...blockProps }>
			<h4 style={{ color: 'maroon' }}>AttendanceSelector</h4>
			<AttendanceSelector
				eventId={ postId }
				currentUser={ currentUser }
				type={ 'upcoming' }
			/>
		</div>
	);
};

export default Edit;
