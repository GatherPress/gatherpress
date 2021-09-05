import React from 'react';

const AttendanceListNavigationItem = ({ item, additionalClasses, count, onTitleClick }) => {
	const { title, value } = item;

	return (
		<div className="gp-attendance-list__item -mb-px mr-2 list-none">
			<a
				className={`gp-attendance-list__anchor no-underline hover:no-underline ${additionalClasses}`}
				data-item={value}
				data-toggle="tab"
				href={`#nav-${value}`}
				role="tab"
				aria-controls={`#nav-${value}`}
				onClick={ e => onTitleClick( e, value ) }
			>
				{title}({count})
			</a>
		</div>
	);
};

export default AttendanceListNavigationItem;
