import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import RsvpResponseContent from './RsvpResponseContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponse = () => {
	const defaultLimit = 8;
	let defaultStatus = 'attending';
	const defaultCount = getFromGlobal('responses').attending.count;
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
	const [rsvpCount, setRsvpCount] = useState(defaultCount);

	Listener({ setRsvpStatus, setRsvpCount }, getFromGlobal('post_id'));

	return (
		<>
			<div className="gp-rsvp-response">
				<div className="gp-rsvp-response__header">
					<div className="gp-rsvp-response__title">
						{__('Attending', 'gatherpess')} ({rsvpCount})
					</div>
					<div className="gp-rsvp-response__see-all">
						<a href="#">{__('See all', 'gatherpress')}</a>
					</div>
				</div>
				<RsvpResponseContent
					items={items}
					activeValue={rsvpStatus}
					limit={rsvpLimit}
				/>
			</div>
		</>
	);
};

export default RsvpResponse;
