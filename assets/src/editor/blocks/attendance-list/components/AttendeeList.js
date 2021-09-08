import React, {Fragment, useState} from 'react';

const AttendeeList = ({ value }) => {
	let defaultList = [];

	if ( 'object' === typeof GatherPress ) {
		defaultList = GatherPress.attendees;
	}

	const [ attendanceList, setAttendanceList ] = useState( defaultList );

	addEventListener( 'setAttendanceList', ( e ) => {
		setAttendanceList( e.detail );
	}, false );

	let renderedItems = '';

	if (
		'object' === typeof attendanceList &&
		'undefined' !== typeof attendanceList[value]
	) {
		renderedItems = attendanceList[value].attendees.map( ( attendee, index ) => {
			const { profile, name, photo, role } = attendee;
			return (
			<div key={index} className="gp-attendance-list__item">
				<a className="gp-attendance-list__member-avatar" href={profile}>
					<img alt={name} title={name} src={photo} />
				</a>
				<div className="gp-attendance-list__member-name">
					<a href={profile}>
						{name}
					</a>
				</div>
				<div className="gp-attendance-list__member-role">{role}</div>
			</div>
			);
		});
	}

	return (
		<Fragment>
			{renderedItems}
		</Fragment>
	);
};

export default AttendeeList;
