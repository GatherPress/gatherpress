/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const AttendeeList = ({
	eventId,
	value,
	limit,
	attendees = [],
	avatarOnly = false,
}) => {
	const [attendanceList, setAttendanceList] = useState(attendees);

	Listener({ setAttendanceList }, eventId);

	let renderedItems = '';

	if (
		'object' === typeof attendanceList &&
		'undefined' !== typeof attendanceList[value]
	) {
		attendees = [...attendanceList[value].attendees];

		if (limit) {
			attendees = attendees.splice(0, limit);
		}

		renderedItems = attendees.map((attendee, index) => {
			const { profile, name, photo, role } = attendee;
			let { guests } = attendee;

			if (guests) {
				guests = ' +' + guests + ' guest(s)';
			} else {
				guests = '';
			}

			return (
				<div key={index} className="gp-attendance-list__item">
					<figure className="gp-attendance-list__member-avatar">
						<a href={profile}>
							<img alt={name} title={name} src={photo} />
						</a>
					</figure>
					{false === avatarOnly && (
						<div className="gp-attendance-list__member-info">
							<div className="gp-attendance-list__member-name">
								<a href={profile}>{name}</a>
							</div>
							<div className="gp-attendance-list__member-role">
								{role}
							</div>
							<small className="gp-attendance-list__guests">
								{guests}
							</small>
						</div>
					)}
				</div>
			);
		});
	}

	return (
		<>
			{'attending' === value &&
				0 === renderedItems.length &&
				false === avatarOnly && (
					<div className="gp-attendance-list__no-attendees">
						{false === getFromGlobal('has_event_past')
							? __(
									'No one is attending this event yet.',
									'gatherpress'
							  )
							: __('No one went to this event.', 'gatherpress')}
					</div>
				)}
			{renderedItems}
		</>
	);
};

export default AttendeeList;
