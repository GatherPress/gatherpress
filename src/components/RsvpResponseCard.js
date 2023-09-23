/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

const RsvpResponseCard = ({ value, limit, responses = [] }) => {
	let renderedItems = '';

	if (
		'object' === typeof responses &&
		'undefined' !== typeof responses[value]
	) {
		responses = [...responses[value].responses];

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
				</div>
			);
		});
	}

	return (
		<>
			{'attending' === value && 0 === renderedItems.length && (
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
