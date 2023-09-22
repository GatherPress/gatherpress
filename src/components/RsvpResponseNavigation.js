/**
 * WordPress dependencies.
 */
import { useEffect, useState } from '@wordpress/element';

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
	defaultLimit,
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
	const [showNavigationDropdown, setShowNavigationDropdown] = useState(false);
	const [hideNavigationDropdown, setHideNavigationDropdown] = useState(true);
	const Tag = hideNavigationDropdown ? `span` : `a`;

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
				defaultLimit={defaultLimit}
			/>
		);
	});

	useEffect(() => {
		global.document.addEventListener('click', ({ target }) => {
			if (!target.closest('.gp-rsvp-response__navigation-active')) {
				setShowNavigationDropdown(false);
			}
		});

		global.document.addEventListener('keydown', ({ key }) => {
			if ('Escape' === key) {
				setShowNavigationDropdown(false);
			}
		});
	});

	useEffect(() => {
		if (0 === rsvpCount.not_attending && 0 === rsvpCount.waiting_list) {
			setHideNavigationDropdown(true);
		} else {
			setHideNavigationDropdown(false);
		}
	}, [rsvpCount]);

	const toggleNavigation = (e) => {
		e.preventDefault();

		setShowNavigationDropdown(!showNavigationDropdown);
	};

	return (
		<div className="gp-rsvp-response__navigation-wrapper">
			<div>
				{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
				<Tag
					href="#"
					className="gp-rsvp-response__navigation-active"
					onClick={(e) => toggleNavigation(e)}
				>
					{items[activeIndex].title}
				</Tag>
				&nbsp;
				<span>({rsvpCount[activeValue]})</span>
			</div>
			{!hideNavigationDropdown && showNavigationDropdown && (
				<nav className="gp-rsvp-response__navigation">
					{renderedItems}
				</nav>
			)}
		</div>
	);
};

export default RsvpResponseNavigation;
