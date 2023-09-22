import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RsvpResponseHeader from './RsvpResponseHeader';
import RsvpResponseContent from './RsvpResponseContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponse = () => {
	const defaultLimit = 8;
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
		'attend' !== currentUserStatus &&
		'' !== currentUserStatus
			? currentUserStatus
			: defaultStatus;

	const [rsvpStatus, setRsvpStatus] = useState(defaultStatus);
	const [rsvpLimit, setRsvpLimit] = useState(defaultLimit);

	const onTitleClick = (e, value) => {
		e.preventDefault();
		setRsvpStatus(value);
	};

	Listener({ setRsvpStatus }, getFromGlobal('post_id'));

	return (
		<div className="gp-rsvp-response">
			<RsvpResponseHeader
				items={items}
				activeValue={rsvpStatus}
				onTitleClick={onTitleClick}
				rsvpLimit={rsvpLimit}
				setRsvpLimit={setRsvpLimit}
				defaultLimit={defaultLimit}
			/>
			<RsvpResponseContent
				items={items}
				activeValue={rsvpStatus}
				limit={rsvpLimit}
			/>
		</div>
	);
};

export default RsvpResponse;
