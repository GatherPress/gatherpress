/**
 * Internal dependencies.
 */
import VenueInformation from '../../../components/VenueInformation';

/**
 * VenueInformationPanel Component
 *
 * This component represents a panel in the Block Editor containing venue information.
 * It includes the VenueInformation component to manage and display venue details.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueInformationPanel.
 */
const VenueInformationPanel = () => {
	return (
		<section>
			<VenueInformation />
		</section>
	);
};

export default VenueInformationPanel;
