import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceSelectorItem from './AttendanceSelectorItem';
import attendance from '../apis/attendance';

const AttendanceSelector = () => {
	let defaultStatus = '';

	if (typeof GatherPress === 'object') {
		defaultStatus = GatherPress.current_user_status.status;
	}

	const [ attendanceStatus, setAttendanceStatus ] = useState(defaultStatus);

	const items = [
		{
			text: __( 'Yes, I would like to attend this event.', 'gatherpress' ),
			status: 'attending'
		},
		{
			text: __( 'No, I cannot attend this event.', 'gatherpress' ),
			status: 'not_attending'
		}
	];

	const onAnchorClick = async (e, status) => {
		e.preventDefault();

		const response = await attendance.post('/attendance', {
			status: status,
		});

		if (response.data.success) {
			setAttendanceStatus(response.data.status);

			const dispatchAttendanceStatus = new CustomEvent('setAttendanceStatus', {
				detail: response.data.status
			});

			dispatchEvent(dispatchAttendanceStatus);

			const dispatchAttendanceList = new CustomEvent('setAttendanceList', {
				detail: response.data.attendees
			});

			dispatchEvent(dispatchAttendanceList);

			let count = {
				all: 0,
				attending: 0,
				not_attending: 0,
				waitlist: 0
			};

			for (const [key, value] of Object.entries(response.data.attendees)) {
				count[key] = value.count;
			}

			const dispatchAttendanceCount = new CustomEvent('setAttendanceCount', {
				detail: count
			});

			dispatchEvent(dispatchAttendanceCount);
		}
	}

	const getStatusText = (status) => {
		switch(status) {
			case 'attending':
				return __( 'Attending', 'gatherpress' );
			case 'not_attending':
				return __( 'Not Attending', 'gatherpress' );
			case 'waitlist':
				return __( 'On Waitlist', 'gatherpress' );
		}

		return __( 'Attend', 'gatherpress' );
	}

	const renderedItems = items.map((item, index) => {
		const { text, status } = item;
		return(
			<AttendanceSelectorItem
				key={index}
				text={text}
				status={status}
				onAnchorClick={onAnchorClick}
			/>
		);
	});

	return(
		<div className="gp-attendance-selector wp-block-button group inline-block relative">
			<a
				className="wp-block-button__link"
				href="#"
				onClick={e => e.preventDefault()}
			>
				<span className="mr-1">{ getStatusText(attendanceStatus) }</span>
				<svg className="fill-current h-8 w-8" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
					<path d = "M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
				</svg>
			</a>
			<ul
				className="absolute left-0 z-10 hidden text-gray-700 pt-1 group-hover:block"
				style={{ margin:0, padding:0 }}
			>
				{renderedItems}
			</ul>
		</div>
	);
}

export default AttendanceSelector;
