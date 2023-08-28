/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import AttendanceListNavigationItem from './AttendanceListNavigationItem';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const AttendanceListNavigation = ({ items, activeValue, onTitleClick }) => {
	const defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0, // eslint-disable-line camelcase
		waiting_list: 0, // eslint-disable-line camelcase
	};

	for (const [key, value] of Object.entries(getFromGlobal('attendees'))) {
		defaultCount[key] = value.count;
	}

	const [attendanceCount, setAttendanceCount] = useState(defaultCount);

	Listener({ setAttendanceCount }, getFromGlobal('post_id'));

	const renderedItems = items.map((item, index) => {
		const additionalClasses =
			item.value === activeValue ? 'gp-attendance-list__current' : '';

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
		<nav className="gp-attendance-list__navigation">{renderedItems}</nav>
	);
};

export default AttendanceListNavigation;
