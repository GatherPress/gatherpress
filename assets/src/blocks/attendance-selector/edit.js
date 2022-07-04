import AttendanceSelector from '../components/AttendanceSelector';

const Edit = ( props ) => {
	return (
		<AttendanceSelector eventId={GatherPress.post_id} currentUser={GatherPress.current_user} />
	);
};

export default Edit;
