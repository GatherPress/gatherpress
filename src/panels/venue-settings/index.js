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

registerPlugin('gp-venue-settings', {
	render: VenueSettings,
});

dispatch('core/edit-post').toggleEditorPanelOpened(
	'gp-venue-settings/gp-venue-settings'
);
