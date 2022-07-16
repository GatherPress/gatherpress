/**
 * External dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
/**
 * Internal dependencies.
 */
import AttendanceSelector from '../components/AttendanceSelector';

const Edit = ( props ) => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<AttendanceSelector eventId={GatherPress.post_id} currentUser={GatherPress.current_user} />
		</div>
	);
};

export default Edit;
