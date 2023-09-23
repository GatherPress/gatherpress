/**
 * WordPress dependencies.
 */
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponseNavigationItem = ({
	item,
	activeItem = false,
	count,
	onTitleClick,
	defaultLimit,
}) => {
	const { title, value } = item;
	const active = !(0 === count && 'attending' !== value);
	const Tag = activeItem ? `span` : `a`;
	const eventId = getFromGlobal('post_id');
	const rsvpSeeAllLink = count > defaultLimit;

	useEffect(() => {
		if (activeItem) {
			Broadcaster({ setRsvpSeeAllLink: rsvpSeeAllLink }, eventId);
		}
	});

	if (active) {
		return (
			<div className="gp-rsvp-response__navigation-item">
				{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
				<Tag
					className="gp-rsvp-response__anchor"
					data-item={value}
					data-toggle="tab"
					href="#"
					role="tab"
					aria-controls={`#gp-rsvp-${value}`}
					onClick={(e) => onTitleClick(e, value)}
				>
					{title}
				</Tag>
				<span className="gp-rsvp-response__count">({count})</span>
			</div>
		);
	}

	return '';
};

export default RsvpResponseNavigationItem;
