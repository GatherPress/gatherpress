/**
 * Internal dependencies.
 */
import GuestLimit from '../../../components/GuestLimit';

/**
 * A panel component for managing the maximum number of guests.
 *
 * This component renders a section containing the `GuestLimit` component,
 * allowing users to set and manage the maximum number of additional guests
 * each attendee can bring to an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the GuestLimitPanel.
 */
const GuestLimitPanel = () => {
	return (
		<section>
			<GuestLimit />
		</section>
	);
};

export default GuestLimitPanel;
