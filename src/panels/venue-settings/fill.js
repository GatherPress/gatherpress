/**
 * Create a separate "Venue settings" panel when editing events,
 * so that venue changes can be made from within an event context.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { VenuePluginDocumentSettings } from './slot';
import { isPostTypeSupporting } from '../../helpers/event';
import OnlineEventPanel from './online-event';

export default function VenuePluginFill() {
	const showVenueSection = isPostTypeSupporting( 'gatherpress-event-venue' );
	const showOnlineEventSection = isPostTypeSupporting( 'gatherpress-online-event' );
	const showPanel = showVenueSection || showOnlineEventSection;

	return (
		showPanel && (
			<PluginDocumentSettingPanel
				name="gatherpress-venue-settings-at-events"
				title={ __( 'Venue settings', 'gatherpress' ) }
				className="gatherpress-venue-settings"
			>
				<VStack spacing={ 6 }>
					{ showVenueSection && <VenuePluginDocumentSettings.Slot /> }
					{ showOnlineEventSection && <OnlineEventPanel /> }
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
}
