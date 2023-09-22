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
	let activeIndex = 0;

	const renderedItems = items.map((item, index) => {
		const activeItem = item.value === activeValue;

		if (activeItem) {
			activeIndex = index;
		}

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

	return (
		<div className="gp-rsvp-response__navigation-wrapper">
			<div className="gp-rsvp-response__navigation-active">
				{items[activeIndex].title} ({rsvpCount[activeValue]})
			</div>
			<nav className="gp-rsvp-response__navigation">{renderedItems}</nav>
		</div>
	);
};

export default RsvpResponseNavigation;
