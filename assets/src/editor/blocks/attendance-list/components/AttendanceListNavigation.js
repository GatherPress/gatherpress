import React, { useState } from 'react';
import AttendanceListNavigationItem from "./AttendanceListNavigationItem";

const AttendanceListNavigation = ({ items, activeValue, onTitleClick }) => {
	let defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0,
		waitlist: 0
	};

	if ('object' === typeof GatherPress) {
		for (const [key, value] of Object.entries(GatherPress.attendees)) {
			defaultCount[key] = value.count;
		}
	}

	const [attendanceCount, setAttendanceCount] = useState(defaultCount);

	addEventListener('setAttendanceCount', (e) => {
		setAttendanceCount(e.detail);
	}, false);

	const renderedItems = items.map((item, index) => {
		const additionalClasses = (item.value === activeValue) ? 'active' : 'opacity-50';

		return(
			<AttendanceListNavigationItem
				key={index}
				item={item}
				count={attendanceCount[item.value]}
				additionalClasses={additionalClasses}
				onTitleClick={onTitleClick}
			/>
		);
	});

	return(
		<nav className="flex border-b ml-0" role="tab-list">
			{renderedItems}
		</nav>
	);
}

export default AttendanceListNavigation;
