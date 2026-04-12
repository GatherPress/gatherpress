import { Fill } from '@wordpress/components';

/**
 * Internal dependencies
 */
import VenueNavigator from '../../components/VenueNavigator';
import { isEventPostType } from '../../helpers/event';

export default function VenueBlockPluginFill() {
	return (
		isEventPostType() && (
			<>
				<Fill name="VenuePluginDocumentSettings">
					<VenueNavigator />
				</Fill>
			</>
		)
	);
}
