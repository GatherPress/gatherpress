/**
 * WordPress dependencies.
 */
import {__} from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RsvpResponseNavigation from './RsvpResponseNavigation';
import { useState } from '@wordpress/element';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

const RsvpResponseHeader = ({
	items,
	activeValue,
	onTitleClick,
	rsvpLimit,
	setRsvpLimit,
	defaultLimit,
}) => {
	const updateLimit = (e) => {
		e.preventDefault();

		if (false !== rsvpLimit) {
			setRsvpLimit(false);
		} else {
			setRsvpLimit(defaultLimit);
		}
	};

	let loadListText;

	if (false === rsvpLimit) {
		loadListText = __('See fewer', 'gatherpress');
	} else {
		loadListText = __('See all', 'gatherpress');
	}

	const [rsvpSeeAllLink, setRsvpSeeAllLink] = useState(
		getFromGlobal('responses')[activeValue].count > defaultLimit
	);

	Listener({ setRsvpSeeAllLink }, getFromGlobal('post_id'));

	return (
		<div className="gp-rsvp-response__header">
			<div className="dashicons dashicons-groups"></div>
			<RsvpResponseNavigation
				items={items}
				activeValue={activeValue}
				onTitleClick={onTitleClick}
				rsvpLimit={rsvpLimit}
			/>
			{rsvpSeeAllLink && (
				<div className="gp-rsvp-response__see-all">
					{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
					<a href="#" onClick={(e) => updateLimit(e)}>
						{loadListText}
					</a>
				</div>
			)}
		</div>
	);
};

export default RsvpResponseHeader;
