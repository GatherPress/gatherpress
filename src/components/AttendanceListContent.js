/**
 * Internal dependencies.
 */
import AttendeeList from './AttendeeList';
import { getFromGlobal } from '../helpers/misc';

const AttendanceListContent = ({ items, activeValue, limit = false }) => {
	const postId = getFromGlobal('post_id');
	const attendees = getFromGlobal('attendees');
	const renderedItems = items.map((item, index) => {
		const { value } = item;
		const active = value === activeValue ? 'active' : 'hidden';

		return (
			<div
				key={index}
				className={`gp-attendance-list__items gp-attendance-list__${active}`}
				id={`gp-attendance-${value}`}
				role="tabpanel"
				aria-labelledby={`gp-attendance-${value}-tab`}
			>
				<AttendeeList
					eventId={postId}
					value={value}
					limit={limit}
					attendees={attendees}
				/>
			</div>
		);
	});

	return <div className="gp-attendance-list__content">{renderedItems}</div>;
};

export default AttendanceListContent;
