/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, FormTokenField, PanelBody } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import RsvpResponseHeader from './RsvpResponseHeader';
import RsvpResponseContent from './RsvpResponseContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * Component for displaying and managing RSVP responses.
 *
 * This component renders a user interface for managing RSVP responses to an event.
 * It includes options for attending, being on the waiting list, or not attending,
 * and updates the status based on user interactions. The component also listens for
 * changes in RSVP status and updates the state accordingly.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered RSVP response component.
 */
const RsvpResponse = () => {
	const defaultLimit = 8;
	const defaultStatus = 'attending';
	const hasEventPast = getFromGlobal('has_event_past');
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

	const [rsvpStatus, setRsvpStatus] = useState(defaultStatus);
	const [rsvpLimit, setRsvpLimit] = useState(defaultLimit);
	const [editMode, setEditMode] = useState(false);

	const [rsvpResponse, setRsvpResponse] = useState(
		getFromGlobal('responses')
	);

	const onTitleClick = (e, value) => {
		e.preventDefault();
		setRsvpStatus(value);
	};

	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};

	Listener({ setRsvpStatus }, getFromGlobal('post_id'));

	const eventId = getFromGlobal('post_id');

	const attendees = rsvpResponse.attending.responses;

	const changeAttendees = async (tokens) => {
		// eslint-disable-next-line camelcase
		const user_id = 3;
		const status = 'remove';
		apiFetch({
			path: '/gatherpress/v1/event/rsvp',
			method: 'POST',
			data: {
				post_id: eventId,
				status,
				// eslint-disable-next-line camelcase
				user_id,
				_wpnonce: getFromGlobal('nonce'),
			},
		}).then((res) => {
			console.log(res);
		});
		return tokens;
	};

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<p>{__('Event List type', 'gatherpress')}</p>
					<Button variant="secondary" onClick={onEditClick}>
						Edit Attendees
					</Button>
				</PanelBody>

				<FormTokenField
					key="query-controls-topics-select"
					label={__('Attendees', 'gatherpress')}
					value={
						attendees &&
						attendees.map((item) => ({
							value: item.name,
						}))
					}
					onChange={changeAttendees}
				/>
			</InspectorControls>
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
					editMode={editMode}
				/>
		</div>
		</>
	);
};

export default RsvpResponse;
