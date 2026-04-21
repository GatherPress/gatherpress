/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../helpers/venue';
import VenueInformationPanel from './venue-information';
import { VenuePluginDocumentSettings } from './slot';
import VenuePluginFill from './fill';

/**
 * VenueSettings Component
 *
 * This component represents a panel in the Block Editor for venue settings.
 * It includes the VenueInformationPanel component to manage and display venue details.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the VenueSettings.
 */
const VenueSettings = () => {
	return (
		isVenuePostType() && (
			<PluginDocumentSettingPanel
				name="gatherpress-venue-settings"
				title={ __( 'Venue settings', 'gatherpress' ) }
				className="gatherpress-venue-settings"
			>
				{ /* Extendable entry point for "Venue Settings" panel. */ }
				<VenuePluginDocumentSettings.Slot />

				<VStack spacing={ 6 }>
					<VenueInformationPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Register Venue Settings Plugin
 *
 * This function registers the VenueSettings component as a plugin to be rendered in the Block Editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerPlugin( 'gatherpress-venue-settings', {
	render: VenueSettings,
} );

registerPlugin( 'gatherpress-venue-settings-at-events', {
	render: VenuePluginFill,
} );
