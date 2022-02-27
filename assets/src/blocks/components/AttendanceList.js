import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';
import { Listener } from '../helpers/broadcasting';

const items = [
	{
		title: __('Attending', 'gatherpress'),
		value: 'attending'
	},
	{
		title: __('Waiting List', 'gatherpress'),
		value: 'waiting_list'
	},
	{
		title: __('Not Attending', 'gatherpress'),
		value: 'not_attending'
	}
];

const AttendanceList = () => {
	let defaultStatus = 'attending';

	if ( 'object' === typeof GatherPress ) {
		defaultStatus = ( 'undefined' !== typeof GatherPress.current_user.status ) ? GatherPress.current_user.status : defaultStatus;
	}

	const [ attendanceStatus, setAttendanceStatus ] = useState( defaultStatus );

	Listener({ setAttendanceStatus: setAttendanceStatus });

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
