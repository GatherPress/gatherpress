/**
 * Internal dependencies.
 */
import MaxAttendanceLimit from '../../../components/MaxAttendanceLimit';

/**
 * A panel component for managing the maximum attendance limit.
 *
 * This component renders a section containing the `MaxAttendanceLimit` component,
 * allowing users to set and manage the total number of people allowed at an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the MaxAttendanceLimitPanel.
 */
const MaxAttendanceLimitPanel = () => {
	return (
		<section>
			<MaxAttendanceLimit />
		</section>
	);
};

export default MaxAttendanceLimitPanel;
