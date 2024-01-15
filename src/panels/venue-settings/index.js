/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { dispatch } from '@wordpress/data';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../helpers/venue';
import VenueInformationPanel from './venue-information';

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
				name="gp-venue-settings"
				title={__('Venue settings', 'gatherpress')}
				initialOpen={true}
				className="gp-venue-settings"
			>
				<VStack spacing={6}>
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
registerPlugin('gp-venue-settings', {
	render: VenueSettings,
});

/**
 * Toggle Venue Settings Panel
 *
 * This function dispatches an action to toggle the visibility of the Venue Settings panel in the Block Editor.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
dispatch('core/edit-post').toggleEditorPanelOpened(
	'gp-venue-settings/gp-venue-settings'
);
