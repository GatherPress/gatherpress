import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';

const items = [
	{
		title: GatherPress.settings.language.attendance.attending,
		value: 'attending'
	},
	{
		title: GatherPress.settings.language.attendance.waiting_list,
		value: 'waiting_list'
	},
	{
		title: GatherPress.settings.language.attendance.not_attending,
		value: 'not_attending'
	}
];

const AttendanceList = () => {
	let defaultStatus = 'attending';

	if ( 'object' === typeof GatherPress ) {
		defaultStatus = ( 'undefined' !== typeof GatherPress.current_user_status.status ) ? GatherPress.current_user_status.status : defaultStatus;
	}

	const [ attendanceStatus, setAttendanceStatus ] = useState( defaultStatus );

	addEventListener( 'setAttendanceStatus', ( e ) => {
		setAttendanceStatus( e.detail );
	}, false );

	const onTitleClick = ( e, value ) => {
		e.preventDefault();

		setAttendanceStatus( value );
	};

	return (
		<div className="gp-attendance-list">
			<AttendanceListNavigation items={items} activeValue={attendanceStatus} onTitleClick={onTitleClick} />
			<AttendanceListContent items={items} activeValue={attendanceStatus} />
		</div>
	);
};

export default AttendanceList;
