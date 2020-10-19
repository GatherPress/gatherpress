import React, {Fragment, useState} from 'react';

const AttendeeList = ({ value }) => {
	let defaultList = [];

	if ('object' === typeof GatherPress) {
		defaultList = GatherPress.attendees;
	}

	const [attendanceList, setAttendanceList] = useState(defaultList);

	addEventListener('setAttendanceList', (e) => {
		setAttendanceList(e.detail);
	}, false);

	let renderedItems = '';

	if (
		'object' === typeof attendanceList
		&& 'undefined' !== typeof attendanceList[value]
	) {
		renderedItems = attendanceList[value].attendees.map((attendee, index) => {
			const { profile, name, photo, role } = attendee;
			return(
			<div key={index} className="p-2">
				<a href={profile}>
					<img className="p-1 border" alt={name} title={name} src={photo} />
				</a>
				<h5 className="mt-2 mb-0" style={{margin:0, padding:0 }}>
					<a href={profile}>
						{name}
					</a>
				</h5>
				<h6 style={{ margin:0, padding:0 }}>{role}</h6>
			</div>
			);
		});
	}

	return(
		<Fragment>
			{renderedItems}
		</Fragment>
	);
}

export default AttendeeList;
