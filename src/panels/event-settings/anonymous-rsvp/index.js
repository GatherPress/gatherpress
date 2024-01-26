/**
 * Internal dependencies.
 */
import AnonymousRsvp from '../../../components/AnonymousRsvp';

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
const AnonymousRsvpPanel = () => {
	return (
		<section>
			<AnonymousRsvp />
		</section>
	);
};

export default AnonymousRsvpPanel;
