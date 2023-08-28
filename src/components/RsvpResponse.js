import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RsvpResponseNavigation from './RsvpResponseNavigation';
import RsvpResponseContent from './RsvpResponseContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponse = () => {
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
		'attend' !== currentUserStatus &&
		'' !== currentUserStatus
			? currentUserStatus
			: defaultStatus;

	const [rsvpStatus, setRsvpStatus] = useState(defaultStatus);
	const [rsvpLimit, setRsvpLimit] = useState(defaultLimit);

	Listener({ setRsvpStatus }, getFromGlobal('post_id'));

	const onTitleClick = (e, value) => {
		e.preventDefault();

		setRsvpStatus(value);
	};

	const updateLimit = (e) => {
		e.preventDefault();
		if (false !== rsvpLimit) {
			setRsvpLimit(false);
		} else {
			setRsvpLimit(defaultLimit);
		}
	};

	let loadListText;
	if (false === rsvpLimit) {
		loadListText = __('See less', 'gatherpress');
	} else {
		loadListText = __('See more', 'gatherpress');
	}

	return (
		<>
			<div className="gp-rsvp-response">
				<RsvpResponseNavigation
					items={items}
					activeValue={rsvpStatus}
					onTitleClick={onTitleClick}
				/>
				<RsvpResponseContent
					items={items}
					activeValue={rsvpStatus}
					limit={rsvpLimit}
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

export default RsvpResponse;
