/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

import { Fill } from '@wordpress/components';



/**
 * Internal dependencies
 */
// const DURATION_META = window.Theater.ProductionBlocks.duration.PostMetaKey;
import { VenuePluginDocumentSettings } from './slot.js';


export default function VenuePluginFill() {

	return (
		<>
			<Fill name="EventPluginDocumentSettings">
				<p>THE "VenuePluginDocumentSettings" in EventPluginDocumentSettings</p>
			</Fill>
			<Fill name="EventPluginDocumentSettings">
				<VenuePluginDocumentSettings.Slot />
			</Fill>
		</>
	);
}
