/**
 * Fill the "Venue Settings" slot into the "Event Settings" slot by default,
 * so that venue changes can be made from within an event context.
 */

/**
 * WordPress dependencies
 */
import { Fill } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { VenuePluginDocumentSettings } from './slot';

export default function VenuePluginFill() {
	return (
		<Fill name="EventPluginDocumentSettings">
			<VenuePluginDocumentSettings.Slot />
		</Fill>
	);
}
