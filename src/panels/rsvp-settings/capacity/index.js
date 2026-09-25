/**
 * Internal dependencies
 */
import Capacity from '../../../components/Capacity';

/**
 * A panel component for managing an event's capacity.
 *
 * This component renders a section containing the `Capacity` component,
 * allowing users to set and manage the total number of people allowed at an event.
 *
 * @since 0.34.0
 *
 * @return {JSX.Element} The JSX element for the CapacityPanel.
 */
const CapacityPanel = () => {
	return (
		<section>
			<Capacity />
		</section>
	);
};

export default CapacityPanel;
