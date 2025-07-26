import { Fill } from '@wordpress/components';

/**
 * Internal dependencies
 */
import VenueNavigator from '../../components/VenueNavigator';

export default function VenueBlockPluginFill() {
	return (
		<>
			<Fill name="EventPluginDocumentSettings">
				<VenueNavigator />
			</Fill>
		</>
	);
}
