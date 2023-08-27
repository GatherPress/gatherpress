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
import { isEventPostType } from '../../helpers/event';
import DateTimePanel from './datetime';
import VenueSelectorPanel from './venue-selector';
import OnlineEventLinkPanel from './online-link';

const EventSettings = () => {
	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gp-event-settings"
				title={__('Event settings', 'gatherpress')}
				initialOpen={true}
				className="gp-event-settings"
				icon="nametag"
			>
				<VStack spacing={6}>
					<DateTimePanel />
					<VenueSelectorPanel />
					<OnlineEventLinkPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin('gp-event-settings', {
	render: EventSettings,
});

dispatch('core/edit-post').toggleEditorPanelOpened(
	'gp-event-settings/gp-event-settings'
);
