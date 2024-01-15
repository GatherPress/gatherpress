/**
 * Internal dependencies.
 */
import VenueSelector from '../../../components/VenueSelector';

/**
 * A panel component for selecting and managing the venue for an event.
 *
 * This component renders a section containing the `VenueSelector` component,
 * allowing users to choose a venue for the event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueSelectorPanel.
 */
const VenueSelectorPanel = () => {
	return (
		<section>
			<VenueSelector />
		</section>
	);
};

export default VenueSelectorPanel;
