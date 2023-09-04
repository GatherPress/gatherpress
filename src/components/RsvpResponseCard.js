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

const RsvpResponseCard = ({
	eventId,
	value,
	limit,
	responses = [],
	avatarOnly = false,
}) => {
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
				<div key={index} className="gp-rsvp-response__item">
					<figure className="gp-rsvp-response__member-avatar">
						<a href={profile}>
							<img alt={name} title={name} src={photo} />
						</a>
					</figure>
					{false === avatarOnly && (
						<div className="gp-rsvp-response__member-info">
							<div className="gp-rsvp-response__member-name">
								<a href={profile}>{name}</a>
							</div>
							<div className="gp-rsvp-response__member-role">
								{role}
							</div>
							<small className="gp-rsvp-response__guests">
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
					<div className="gp-rsvp-response__no-responses">
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

export default RsvpResponseCard;
