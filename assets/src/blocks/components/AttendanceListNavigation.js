import React, { useState } from 'react';
import AttendanceListNavigationItem from './AttendanceListNavigationItem';
import { Listener } from '../helpers/broadcasting';

const AttendanceListNavigation = ({ items, activeValue, onTitleClick }) => {
	let defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0, // eslint-disable-line camelcase
		waiting_list: 0 // eslint-disable-line camelcase
	};

	if ( 'object' === typeof GatherPress ) {
		for ( const [ key, value ] of Object.entries( GatherPress.attendees ) ) {
			defaultCount[key] = value.count;
		}
	}

	const [ attendanceCount, setAttendanceCount ] = useState( defaultCount );

	Listener({ setAttendanceCount: setAttendanceCount });

	const renderedItems = items.map( ( item, index ) => {
		const additionalClasses = ( item.value === activeValue ) ? 'gp-attendance-list__anchor--current' : '';

		return (
			<AttendanceListNavigationItem
				key={index}
				item={item}
				count={attendanceCount[item.value]}
				additionalClasses={additionalClasses}
				onTitleClick={onTitleClick}
			/>
		);
	});

	return (
		<nav className="gp-attendance-list__navigation" role="tab-list">
			{renderedItems}
		</nav>
	);
};

export default AttendanceListNavigation;
