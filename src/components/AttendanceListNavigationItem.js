import React from 'react';

const AttendanceListNavigationItem = ({
	item,
	additionalClasses,
	count,
	onTitleClick,
}) => {
	const { title, value } = item;
	const active = 0 === count && 'attending' !== value ? 'hidden' : 'active';

	return (
		<div
			className={`wp-block-gatherpress-attendance-list__navigation-item wp-block-gatherpress-attendance-list__${active} ${additionalClasses}`}
		>
			{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
			<a
				className="wp-block-gatherpress-attendance-list__anchor"
				data-item={value}
				data-toggle="tab"
				href="#"
				role="tab"
				aria-controls={`#gp-attendance-${value}`}
				onClick={(e) => onTitleClick(e, value)}
			>
				{title}
			</a>
			<span className="wp-block-gatherpress-attendance-list__count">({count})</span>
		</div>
	);
};

export default AttendanceListNavigationItem;
