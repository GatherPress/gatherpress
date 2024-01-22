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
import VenueSelectorPanel from './venue-selector';
import OnlineEventLinkPanel from './online-link';
import DateTimeRangePanel from './datetime-range';
import NotifyMembersPanel from './notify-members';

/**
 * A settings panel for event-specific settings in the block editor.
 *
 * This component renders a `PluginDocumentSettingPanel` containing various
 * subpanels for configuring event-related settings, such as date and time,
 * venue selection, online event link, and notifying members.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the EventSettings panel if
 * the current post type is an event; otherwise, returns null.
 */
const EventSettings = () => {
	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gp-event-settings"
				title={__('Event settings', 'gatherpress')}
				initialOpen={true}
				className="gp-event-settings"
			>
				<VStack spacing={6}>
					<DateTimeRangePanel />
					<VenueSelectorPanel />
					<OnlineEventLinkPanel />
					<NotifyMembersPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Registers the 'gp-event-settings' plugin.
 *
 * This function registers a custom plugin named 'gp-event-settings' and
 * associates it with the `EventSettings` component for rendering.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerPlugin('gp-event-settings', {
	render: EventSettings,
});

/**
 * Toggles the visibility of the 'gp-event-settings' panel in the Block Editor.
 *
 * This function uses the `dispatch` function from the `@wordpress/data` package
 * to toggle the visibility of the 'gp-event-settings' panel in the Block Editor.
 * The panel is identified by the string 'gp-event-settings/gp-event-settings'.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
dispatch('core/edit-post').toggleEditorPanelOpened(
	'gp-event-settings/gp-event-settings'
);
