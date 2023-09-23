/**
 * Internal dependencies.
 */
import RsvpResponseCard from './RsvpResponseCard';
import { getFromGlobal } from '../helpers/globals';
import { useState } from '@wordpress/element';
import { Listener } from '../helpers/broadcasting';

const RsvpResponseContent = ({ items, activeValue, limit = false }) => {
	const eventId = getFromGlobal('post_id');
	const [rsvpResponse, setRsvpResponse] = useState(
		getFromGlobal('responses')
	);

	Listener({ setRsvpResponse }, eventId);

	const renderedItems = items.map((item, index) => {
		const { value } = item;
		const active = value === activeValue;

		if (active) {
			return (
				<div
					key={index}
					className="gp-rsvp-response__items"
					id={`gp-rsvp-${value}`}
					role="tabpanel"
					aria-labelledby={`gp-rsvp-${value}-tab`}
				>
					<RsvpResponseCard
						value={value}
						limit={limit}
						responses={rsvpResponse}
					/>
				</div>
			);
		}

		return '';
	});

	return <div className="gp-rsvp-response__content">{renderedItems}</div>;
};

export default RsvpResponseContent;
