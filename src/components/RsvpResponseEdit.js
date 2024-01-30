/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { FormTokenField } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies.
 */
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal, setToGlobal } from '../helpers/globals';

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
const RsvpResponseEdit = () => {
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
	const [rsvpResponse, setRsvpResponse] = useState( getFromGlobal('responses') );

	const eventId = getFromGlobal('post_id');
	const attendees = rsvpResponse.attending.responses;


	/**
	 * Fetches user records from the core store via getEntityRecords.
	 * Returns userList containing the list of user records.
	 */
	const { userList } = useSelect((select) => {
		const { getEntityRecords } = select(coreStore);

		let users = getEntityRecords("root", "user", {
			per_page: -1,
		});

		return {
			userList: users,
		};
	}, []);

	/**
	 * Reduces the userList to an object mapping usernames to user objects.
	 * This provides convenient lookup from username to full user data.
	 */
	const userSuggestions =
		userList?.reduce(
			(accumulator, user) => ({
				...accumulator,
				[user.username]: user,
			}),
			{},
		) ?? {};

	Listener({ setRsvpStatus }, getFromGlobal('post_id'));

	// Make sure rsvpStatus is a valid status, if not, set to default.
	if (!items.some((item) => item.value === rsvpStatus)) {
		setRsvpStatus(defaultStatus);
	}

	/**
	 * Updates the RSVP status for a user attending the given event.
	 *
	 * @param {number} userId - The ID of the user to update.
	 * @param {number} eventId - The ID of the event to update attendance for.
	 * @param {string} [status='attending'] - The RSVP status to set (attending or remove).
	 */
	const updateUserStatus = (userId, eventId, status = "attending") => {
		apiFetch({
			path: "/gatherpress/v1/event/rsvp",
			method: "POST",
			data: {
				post_id: eventId,
				status,
				user_id: userId,
				_wpnonce: getFromGlobal("nonce"),
			},
		}).then((res) => {
			setRsvpResponse(res.responses);
			setToGlobal("responses", res.responses);
		});
	};

	/**
	 * Updates the attendee list for the RSVP based on the provided tokens.
	 * If new tokens are added, new attendees will be added.
	 * If existing tokens are removed, the associated attendees will be removed.
	 *
	 * @param {Object[]} tokens - Array of token objects representing attendees
	 */
	const changeAttendees = async (tokens) => {
		// Adding some new attendees
		if (tokens.length > attendees.length) {
			tokens.forEach((token) => {
				if (!userSuggestions[token]) {
					return;
				}

				// We have a new user to add to the attendees list.
				updateUserStatus(
					userSuggestions[token].id,
					eventId,
					"attending",
				);
			});
		} else {
			// Removing attendees
			attendees.forEach((attendee) => {
				if (false === tokens.some((item) => item.id === attendee.id)) {
					updateUserStatus(attendee.id, eventId, "remove");
				}
			});
		}
	};

	return (
		<div className="gp-rsvp-response">
			<FormTokenField
				key="query-controls-topics-select"
				label={__('Attendees', 'gatherpress')}
				value={
					attendees &&
					attendees.map((item) => ({
						id: item.id,
						value: item.name,
					}))
				}
				tokenizeOnSpace={ true }
				onChange={changeAttendees}
				suggestions={Object.keys(userSuggestions)}
				maxSuggestions={20}
			/>
		</div>
	);
};

export default RsvpResponseEdit;
