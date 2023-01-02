/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalDivider as Divider,
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
import VenuePanel from './venue';

const EventSettings = () => {
	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gp-event-settings"
				title={__('Event settings', 'gatherpress')}
				initialOpen={true}
				className="gp-event-settings"
			>
				<VStack spacing={2}>
					<DateTimePanel />
					<Divider />
					<VenuePanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin('gp-event-settings', {
	render: EventSettings,
	icon: '',
});


wp.data.dispatch( 'core/edit-post' ) .toggleEditorPanelOpened(
	'gp-event-settings/gp-event-settings'
);

