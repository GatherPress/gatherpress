/**
 * Create a separate "Venue settings" panel when editing events,
 * so that venue changes can be made from within an event context.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { VenuePluginDocumentSettings } from './slot';
import { isEventPostType } from '../../helpers/event';

export default function VenuePluginFill() {
	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gatherpress-venue-settings-at-events"
				title={ __( 'Venue settings', 'gatherpress' ) }
				className="gatherpress-venue-settings"
			>
				<VenuePluginDocumentSettings.Slot />
			</PluginDocumentSettingPanel>
		)
	);
}
