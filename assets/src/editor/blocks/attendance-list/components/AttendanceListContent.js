import React from 'react';
import AttendeeList from './AttendeeList';

const AttendanceListContent = ({ items, activeValue }) => {
	const renderedItems = items.map(( item, index) => {
		const { title, value } = item;
		const active = (value === activeValue) ? 'active' : 'hidden';

		return(
			<div
				key={index}
				className={`tab-pain flex flex-row flex-wrap ${active}`}
				id={`nav-${value}`}
				role="tabpanel"
				aria-labelledby={`nav-${value}-tab`}
			>
				<AttendeeList value={value} />
			</div>
		);
	});

	return(
		<div className="tab-content p-3">
			{renderedItems}
		</div>
	);
}

export default AttendanceListContent;
