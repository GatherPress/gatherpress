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
	// const type = '1' === GatherPress.has_event_past ? 'past' : 'upcoming';
	const type = 'upcoming';
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div { ...blockProps }>
			<AttendanceSelector
				eventId={ postId }
				currentUser={ currentUser }
				type={ type }
			/>
		</div>
	);
};

export default Edit;
