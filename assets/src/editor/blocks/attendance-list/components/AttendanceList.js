import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';

const items = [
	{
		title: __( 'Attending', 'gatherpress' ),
		value: 'attending'
	},
	{
		title: __( 'Waitlist', 'gatherpress' ),
		value: 'waitlist'
	},
	{
		title: __( 'Not Attending', 'gatherpress' ),
		value: 'not_attending'
	}
];

const AttendanceList = () => {
	let defaultStatus = 'attending';

	if (typeof GatherPress === 'object') {
		defaultStatus = ('undefined' !== typeof GatherPress.current_user_status.status) ? GatherPress.current_user_status.status : defaultStatus;
	}

	const [attendanceStatus, setAttendanceStatus] = useState(defaultStatus);

	addEventListener('setAttendanceStatus', (e) => {
		setAttendanceStatus(e.detail);
	}, false);

	const onTitleClick = (e, value) => {
		e.preventDefault();

		setAttendanceStatus(value);
	};

	return(
		<div className="mt-4">
			<AttendanceListNavigation items={items} activeValue={attendanceStatus} onTitleClick={onTitleClick} />
			<AttendanceListContent items={items} activeValue={attendanceStatus} />
		</div>
	);
}

export default AttendanceList;
