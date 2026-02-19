/**
 * Internal dependencies.
 */
import OnlineEventLink from '../../../components/OnlineEventLink';

/**
 * A panel component for managing the online event link.
 *
 * This component renders a section containing the `OnlineEventLink` component,
 * allowing users to set and manage the link for an online event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the OnlineEventLinkPanel.
 */
const OnlineEventLinkPanel = () => {
	return (
		<section>
			<OnlineEventLink />
		</section>
	);
};

export default OnlineEventLinkPanel;
