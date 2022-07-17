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
	const type = '1' === GatherPress.has_event_past ? 'past' : 'upcoming';

	return (
		<div { ...blockProps }>
			<AttendanceSelector
				eventId={ GatherPress.post_id }
				currentUser={ GatherPress.current_user }
				type={ type }
			/>
		</div>
	);
};

export default Edit;
