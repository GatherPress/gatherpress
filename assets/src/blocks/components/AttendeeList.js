import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Listener } from '../helpers/broadcasting';

const AttendeeList = ({ value }) => {
	let defaultList = [];

	if ( 'object' === typeof GatherPress ) {
		defaultList = GatherPress.attendees;
	}

	const [ attendanceList, setAttendanceList ] = useState( defaultList );

	Listener({ setAttendanceList: setAttendanceList });

	let renderedItems = '';

	if (
		'object' === typeof attendanceList &&
		'undefined' !== typeof attendanceList[value]
	) {
		renderedItems = attendanceList[value].attendees.map( ( attendee, index ) => {
			const { profile, name, photo, role } = attendee;
			let { guests } = attendee;

			if (guests) {
				guests = ' +' + guests + ' guest(s)';
			} else {
				guests = '';
			}

			return (
				<div key={index} className="gp-attendance-list__items--item">
					<a className="gp-attendance-list__member-avatar" href={profile}>
						<figure className="wp-block-image is-style-rounded">
							<img alt={name} title={name} src={photo} />
						</figure>
					</a>
					<div className="gp-attendance-list__member-name">
						<a href={profile}>
							{name}
						</a>
					</div>
					<div className="gp-attendance-list__member-role">
						{role}
					</div>
					<small className="gp-attendance-list__guests">
						{guests}
					</small>
				</div>
			);
		});
	}

	return (
		<>
			{'attending' === value && 0 === renderedItems.length &&
				<div className="gp-attendance-list__no-attendees">
					{__( 'No one is attending this event yet.', 'gatherpress')}
				</div>
			}
			{renderedItems}
		</>
	);
};

export default AttendeeList;
