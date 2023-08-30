/**
 * WordPress dependencies.
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import OnlineEvent from './OnlineEvent';
import Venue from './Venue';

const VenueOrOnlineEvent = ({
	name,
	fullAddress,
	phoneNumber,
	website,
	onlineEvent = false,
}) => {
	const onlineEventLink = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	return (
		<>
			{!onlineEvent && (
				<Venue
					name={name}
					fullAddress={fullAddress}
					phoneNumber={phoneNumber}
					website={website}
				/>
			)}

			{onlineEvent && (
				<OnlineEvent onlineEventLinkDefault={onlineEventLink} />
			)}
		</>
	);
};

export default VenueOrOnlineEvent;
