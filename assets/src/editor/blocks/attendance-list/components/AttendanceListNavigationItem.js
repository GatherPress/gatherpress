import React from 'react';

const AttendanceListNavigationItem = ({ item, additionalClasses, count, onTitleClick }) => {
	const { title, value } = item;
	const active = ( 0 === count ) ? 'hidden' : 'active';
	let format = GatherPress.settings.language.attendance.menu_structure;
	format = format.replaceAll( '%status%', title );
	format = format.replaceAll( '%count%', count );

	return (
		<div className={`gp-attendance-list__item gp-attendance-list__${active}`}>
			<a
				className={`gp-attendance-list__anchor ${additionalClasses}`}
				data-item={value}
				data-toggle="tab"
				href={`#gp-attendance-${value}`}
				role="tab"
				aria-controls={`#gp-attendance-${value}`}
				onClick={ e => onTitleClick( e, value ) }
			>
				{format}
			</a>
		</div>
	);
};

export default AttendanceListNavigationItem;
