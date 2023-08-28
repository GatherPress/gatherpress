/**
 * Internal dependencies.
 */
import RsvpResponseCard from './RsvpResponseCard';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponseContent = ({ items, activeValue, limit = false }) => {
	const postId = getFromGlobal('post_id');
	const attendees = getFromGlobal('attendees');
	const renderedItems = items.map((item, index) => {
		const { value } = item;
		const active = value === activeValue ? 'active' : 'hidden';

		return (
			<div
				key={index}
				className={`gp-rsvp-response__items gp-rsvp-response__${active}`}
				id={`gp-rsvp-${value}`}
				role="tabpanel"
				aria-labelledby={`gp-rsvp-${value}-tab`}
			>
				<RsvpResponseCard
					eventId={postId}
					value={value}
					limit={limit}
					attendees={attendees}
				/>
			</div>
		);
	});

	return <div className="gp-rsvp-response__content">{renderedItems}</div>;
};

export default RsvpResponseContent;
