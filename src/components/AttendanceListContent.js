import React from 'react';
import AttendeeList from './AttendeeList';

const AttendanceListContent = ({ items, activeValue, limit = false }) => {
	const renderedItems = items.map((item, index) => {
		const { value } = item;
		const active = value === activeValue ? 'active' : 'hidden';
		// eslint-disable-next-line no-undef
		const postId = GatherPress.post_id;
		// eslint-disable-next-line no-undef
		const attendees = GatherPress.attendees;

		return (
			<div
				key={index}
				className={`wp-block-gatherpress-attendance-list__items wp-block-gatherpress-attendance-list__${active}`}
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

	return <div className="wp-block-gatherpress-attendance-list__content">{renderedItems}</div>;
};

export default AttendanceListContent;
