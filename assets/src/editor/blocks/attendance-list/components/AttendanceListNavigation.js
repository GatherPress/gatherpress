import React from 'react';
import AttendanceListNavigationItem from "./AttendanceListNavigationItem";

const AttendanceListNavigation = ({ items, activeValue, onTitleClick }) => {
	const renderedItems = items.map((item, index) => {
		const additionalClasses = (item.value === activeValue) ? 'active' : 'opacity-50';

		return(
			<AttendanceListNavigationItem
				key={index}
				item={item}
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
