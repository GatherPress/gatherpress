import React from 'react';

const AttendanceListNavigationItem = ({ item, additionalClasses, count, onTitleClick }) => {
	const { title, value } = item;
	const active = ( 0 === count && 'attending' !== value ) ? 'hidden' : 'active';

	return (
		<div className={`gp-attendance-list__navigation--item gp-attendance-list__${active} ${additionalClasses}`}>
			<a
				className="gp-attendance-list__anchor"
				data-item={value}
				data-toggle="tab"
				href="wp-content/plugins/gatherpress/assets/src/components/AttendanceListNavigationItem#"
				role="tab"
				aria-controls={`#gp-attendance-${value}`}
				onClick={ e => onTitleClick( e, value ) }
			>
				{title}
			</a>
			<span className="gp-attendance-list__count">
				({count})
			</span>
		</div>
	);
};

export default AttendanceListNavigationItem;
