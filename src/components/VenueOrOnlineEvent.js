/**
 * Internal dependencies.
 */
import OnlineEvent from './OnlineEvent';
import Venue from './Venue';

const VenueOrOnlineEvent = ({
	name = '',
	fullAddress,
	phoneNumber,
	website,
	isOnlineEventTerm = false,
	onlineEventLink = '',
}) => {
	return (
		<>
			{!isOnlineEventTerm && (
				<Venue
					name={name}
					fullAddress={fullAddress}
					phoneNumber={phoneNumber}
					website={website}
				/>
			)}

			{isOnlineEventTerm && (
				<OnlineEvent onlineEventLinkDefault={onlineEventLink} />
			)}
		</>
	);
};

export default VenueOrOnlineEvent;
