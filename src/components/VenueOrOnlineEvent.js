/**
 * Internal dependencies.
 */
import OnlineEvent from './OnlineEvent';
import Venue from './Venue';

/**
 * VenueOrOnlineEvent component for GatherPress.
 *
 * This component serves as a conditional renderer based on whether an event is an in-person
 * event or an online event. It takes various parameters such as name, fullAddress, phoneNumber,
 * website for in-person events, and isOnlineEventTerm, onlineEventLink for online events. It
 * conditionally renders the `Venue` component or the `OnlineEvent` component accordingly.
 *
 * @since 1.0.0
 *
 * @param {Object}  props                           - The component props.
 * @param {string}  [props.name='']                 - The name of the venue.
 * @param {string}  props.fullAddress               - The full address of the venue.
 * @param {string}  props.phoneNumber               - The phone number of the venue.
 * @param {string}  props.website                   - The website of the venue.
 * @param {boolean} [props.isOnlineEventTerm=false] - A flag indicating if the event is online.
 * @param {string}  [props.onlineEventLink='']      - The default online event link for online events.
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueOrOnlineEvent = ( {
	name = '',
	fullAddress,
	phoneNumber,
	website,
	isOnlineEventTerm = false,
	onlineEventLink = '',
} ) => {
	return (
		<>
			{ ! isOnlineEventTerm && (
				<Venue
					name={ name }
					fullAddress={ fullAddress }
					phoneNumber={ phoneNumber }
					website={ website }
				/>
			) }

			{ isOnlineEventTerm && (
				<OnlineEvent onlineEventLinkDefault={ onlineEventLink } />
			) }
		</>
	);
};

export default VenueOrOnlineEvent;
