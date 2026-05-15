import { Fill } from '@wordpress/components';

/**
 * Internal dependencies
 */
import VenueNavigator from '../../components/VenueNavigator';
import { isPostTypeSupporting } from '../../helpers/event';

export default function VenueBlockPluginFill() {
	return (
		isPostTypeSupporting( 'gatherpress-venue' ) && (
			<Fill name="VenuePluginDocumentSettings">
				<VenueNavigator />
			</Fill>
		)
	);
}
