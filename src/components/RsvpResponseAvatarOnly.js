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
			const { profile, name, photo, role } = response;
			let { guests } = response;

			if (guests) {
				guests = ' +' + guests + ' guest(s)';
			} else {
				guests = '';
			}

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
