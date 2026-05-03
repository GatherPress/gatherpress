/**
 * Internal dependencies
 */
import EnableOpenRsvp from '../../../components/EnableOpenRsvp';

/**
 * A panel component for managing the Open RSVP enabled setting per event.
 *
 * Renders a section containing the `EnableOpenRsvp` component, allowing
 * editors to control whether visitors without an account can RSVP to
 * this specific event. Only visible when Open RSVP is enabled sitewide.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the EnableOpenRsvpPanel.
 */
const EnableOpenRsvpPanel = () => {
	return (
		<section>
			<EnableOpenRsvp />
		</section>
	);
};

export default EnableOpenRsvpPanel;
