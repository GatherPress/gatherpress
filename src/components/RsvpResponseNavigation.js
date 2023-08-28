/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpResponseNavigationItem from './RsvpResponseNavigationItem';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponseNavigation = ({ items, activeValue, onTitleClick }) => {
	const defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0, // eslint-disable-line camelcase
		waiting_list: 0, // eslint-disable-line camelcase
	};

	for (const [key, value] of Object.entries(getFromGlobal('attendees'))) {
		defaultCount[key] = value.count;
	}

	const [rsvpCount, setRsvpCount] = useState(defaultCount);

	Listener({ setRsvpCount }, getFromGlobal('post_id'));

	const renderedItems = items.map((item, index) => {
		const additionalClasses =
			item.value === activeValue ? 'gp-rsvp-response__current' : '';

		return (
			<RsvpResponseNavigationItem
				key={index}
				item={item}
				count={rsvpCount[item.value]}
				additionalClasses={additionalClasses}
				onTitleClick={onTitleClick}
			/>
		);
	});

	return <nav className="gp-rsvp-response__navigation">{renderedItems}</nav>;
};

export default RsvpResponseNavigation;
