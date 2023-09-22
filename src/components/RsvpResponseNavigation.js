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

const RsvpResponseNavigation = ({
	items,
	activeValue,
	onTitleClick,
	rsvpLimit,
}) => {
	const defaultCount = {
		all: 0,
		attending: 0,
		not_attending: 0, // eslint-disable-line camelcase
		waiting_list: 0, // eslint-disable-line camelcase
	};

	for (const [key, value] of Object.entries(getFromGlobal('responses'))) {
		defaultCount[key] = value.count;
	}

	const [rsvpCount, setRsvpCount] = useState(defaultCount);

	Listener({ setRsvpCount }, getFromGlobal('post_id'));

	const renderedItems = items.map((item, index) => {
		const activeItem = item.value === activeValue;

		return (
			<RsvpResponseNavigationItem
				key={index}
				item={item}
				count={rsvpCount[item.value]}
				activeItem={activeItem}
				onTitleClick={onTitleClick}
				rsvpLimit={rsvpLimit}
			/>
		);
	});

	return <nav className="gp-rsvp-response__navigation">{renderedItems}</nav>;
};

export default RsvpResponseNavigation;
