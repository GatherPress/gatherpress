/**
 * Internal dependencies.
 */
import AnonymousRsvp from '../../../components/AnonymousRsvp';

/**
 * A panel component for managing anonymous RSVP settings.
 *
 * This component renders a section containing the `AnonymousRsvp` component,
 * allowing users to enable or disable anonymous RSVPs for an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the AnonymousRsvpPanel.
 */
const AnonymousRsvpPanel = () => {
	return (
		<section>
			<AnonymousRsvp />
		</section>
	);
};

export default AnonymousRsvpPanel;
