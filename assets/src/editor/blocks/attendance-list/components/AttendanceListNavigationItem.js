import React from 'react';

const AttendanceListNavigationItem = ({ item, additionalClasses, onTitleClick }) => {
	const { title, value } = item;

	return(
		<div className="-mb-px mr-2 list-none">
			<a
				className={`no-underline hover:no-underline ${additionalClasses}`}
				data-item={value}
				data-toggle="tab"
				href={`#nav-${value}`}
				role="tab"
				aria-controls={`#nav-${value}`}
				onClick={ e => onTitleClick(e, value) }
			>
				{title}
			</a>
		</div>
	);
}

export default AttendanceListNavigationItem;
