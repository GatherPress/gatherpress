/**
 * Internal dependencies.
 */
import EnableRsvp from '../../../components/EnableRsvp';

/**
 * A panel component for managing the RSVP enabled setting.
 *
 * This component renders a section containing the `EnableRsvp` component,
 * allowing editors to enable or disable RSVP for a specific event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the EnableRsvpPanel.
 */
const EnableRsvpPanel = () => {
	return (
		<section>
			<EnableRsvp />
		</section>
	);
};

export default EnableRsvpPanel;
