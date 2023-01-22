import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/misc';

const AttendanceList = () => {
	const defaultLimit = 10;
	let defaultStatus = 'attending';
	const hasEventPast = getFromGlobal('has_event_past');
	const currentUserStatus = getFromGlobal('current_user.status');
	const items = [
		{
			title:
				false === hasEventPast
					? __('Attending', 'gatherpress')
					: __('Went', 'gatherpress'),
			value: 'attending',
		},
		{
			title:
				false === hasEventPast
					? __('Waiting List', 'gatherpress')
					: __('Wait Listed', 'gatherpress'),
			value: 'waiting_list',
		},
		{
			title:
				false === hasEventPast
					? __('Not Attending', 'gatherpress')
					: __("Didn't Go", 'gatherpress'),
			value: 'not_attending',
		},
	];

	// @todo redo this logic and have it come from API and not GatherPress object.
	defaultStatus =
		'undefined' !== typeof currentUserStatus &&
		'attend' !== currentUserStatus
			? currentUserStatus
			: defaultStatus;

	const [attendanceStatus, setAttendanceStatus] = useState(defaultStatus);
	const [attendeeLimit, setAttendeeLimit] = useState(defaultLimit);

	Listener({ setAttendanceStatus }, getFromGlobal('post_id'));

	const onTitleClick = (e, value) => {
		e.preventDefault();

		setAttendanceStatus(value);
	};

	const updateLimit = (e) => {
		e.preventDefault();
		if (false !== attendeeLimit) {
			setAttendeeLimit(false);
		} else {
			setAttendeeLimit(defaultLimit);
		}
	};

	let loadListText;
	if (false === attendeeLimit) {
		loadListText = __('See less', 'gatherpress');
	} else {
		loadListText = __('See more', 'gatherpress');
	}

	return (
		<>
			<div className="gp-attendance-list">
				<AttendanceListNavigation
					items={items}
					activeValue={attendanceStatus}
					onTitleClick={onTitleClick}
				/>
				<AttendanceListContent
					items={items}
					activeValue={attendanceStatus}
					limit={attendeeLimit}
				/>
			</div>
			<div className="has-text-align-right">
				{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
				<a href="#" onClick={(e) => updateLimit(e)}>
					{loadListText}
				</a>
			</div>
		</>
	);
};

export default AttendanceList;
