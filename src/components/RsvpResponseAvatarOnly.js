/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Listener } from '../helpers/broadcasting';

/**
 * RsvpResponseCard component for GatherPress.
 *
 * This component displays avatars of attendees who have responded to an event's RSVP.
 * It receives information about the RSVP responses, including the attendee's name and photo,
 * and renders their avatars accordingly. The component listens for updates to the RSVP responses
 * and dynamically reflects changes.
 *
 * @since 1.0.0
 *
 * @param {Object} props                - Component props.
 * @param {number} props.eventId        - The ID of the event.
 * @param {string} props.value          - The RSVP status value ('attending', 'not_attending', etc.).
 * @param {number} props.limit          - The maximum number of responses to display.
 * @param {Array}  [props.responses=[]] - An array of RSVP responses for the specified status.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseCard = ({ eventId, value, limit, responses = [] }) => {
	const [rsvpResponse, setRsvpResponse] = useState(responses);

	Listener({ setRsvpResponse }, eventId);

	let renderedItems = '';

	if (
		'object' === typeof rsvpResponse &&
		'undefined' !== typeof rsvpResponse[value]
	) {
		responses = [...rsvpResponse[value].responses];

		if (limit) {
			responses = responses.splice(0, limit);
		}

		renderedItems = responses.map((response, index) => {
			const { name, photo } = response;

			return (
				<figure key={index} className="gp-rsvp-response__member-avatar">
					<img alt={name} title={name} src={photo} />
				</figure>
			);
		});
	}

	return <>{renderedItems}</>;
};

export default RsvpResponseCard;
