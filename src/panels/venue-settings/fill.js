/**
 * Create a separate "Venue settings" panel when editing events,
 * so that venue changes can be made from within an event context.
 */

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { VenuePluginDocumentSettings } from './slot';
import { isPostTypeSupporting } from '../../helpers/event';
import { usePostTypeLabel } from '../../helpers/editor';
import { getVenuePostType } from '../../helpers/venue';
import OnlineEventPanel from './online-event';

export default function VenuePluginFill() {
	const showVenueSection = isPostTypeSupporting( 'gatherpress-venue' );
	const showOnlineEventSection = isPostTypeSupporting( 'gatherpress-online-event' );
	const showPanel = showVenueSection || showOnlineEventSection;

	// Get the current post type and its related venue post type.
	// In general, there should (!) be no need to look up the post type dynamically,
	// as this only renders for gatherpress_event|s related to gatherpress_venue|s.
	// But just in case, let's be consistent to stay safe.
	const { venuePostType } = useSelect(
		( select ) => {
			const editorPostType = select( 'core/editor' )?.getCurrentPostType();
			const currentVenuePostType = getVenuePostType( editorPostType );
			return {
				venuePostType: currentVenuePostType,
			};
		},
		[]
	);

	// Read the singular label so the panel title reflects what the post type
	// is actually called — a custom venue post type with
	// `singular_name => 'Location'` shows "Location settings" without any
	// extra wiring (#1612).
	const singularLabel = usePostTypeLabel(
		'singular_name',
		venuePostType,
		__( 'Venue', 'gatherpress' )
	);

	return (
		showPanel && (
			<PluginDocumentSettingPanel
				name="gatherpress-venue-settings-at-events"
				title={ sprintf(
					/* translators: %s: Singular post type label, e.g. "Venue". */
					__( '%s settings', 'gatherpress' ),
					singularLabel
				) }
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
