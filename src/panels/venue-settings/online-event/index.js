/**
 * Internal dependencies
 */
import OnlineEvent from '../../../components/OnlineEvent';

/**
 * A panel component for managing the online event link.
 *
 * This component renders a section containing the `OnlineEvent` component,
 * allowing users to set and manage the link for an online event.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The JSX element for the OnlineEventPanel.
 */
const OnlineEventPanel = () => {
	return (
		<section>
			<OnlineEvent />
		</section>
	);
};

export default OnlineEventPanel;
