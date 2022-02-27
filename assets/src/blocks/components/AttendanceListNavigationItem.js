import React from 'react';

const AttendanceListNavigationItem = ({ item, additionalClasses, count, onTitleClick }) => {
	const { title, value } = item;
	const active = ( 0 === count ) ? 'hidden' : 'active';

	return (
		<div className={`gp-attendance-list__item gp-attendance-list__${active}`}>
			<a
				className={`gp-attendance-list__anchor ${additionalClasses}`}
				data-item={value}
				data-toggle="tab"
				href="#"
				role="tab"
				aria-controls={`#gp-attendance-${value}`}
				onClick={ e => onTitleClick( e, value ) }
			>
				{title} ({count})
			</a>
		</div>
	);
};

export default AttendanceListNavigationItem;
