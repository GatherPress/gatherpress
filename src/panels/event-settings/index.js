/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies.
 */
import { isEventPostType } from '../../helpers/event';
import AnonymousRsvpPanel from './anonymous-rsvp';
import DateTimeRangePanel from './datetime-range';
import GuestLimitPanel from './guest-limit';
import MaxAttendanceLimitPanel from './max-attendance-limit';
import NotifyMembersPanel from './notify-members';
import OnlineEventLinkPanel from './online-link';
import VenueSelectorPanel from './venue-selector';
import { EventPluginDocumentSettings } from './slot';

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
				name="gatherpress-event-settings"
				title={ __( 'Event settings', 'gatherpress' ) }
				className="gatherpress-event-settings"
			>
				{ /* Extendable entry point for "Event Settings" panel. */ }
				<EventPluginDocumentSettings.Slot />

				<VStack spacing={ 4 }>
					<DateTimeRangePanel />
					<VenueSelectorPanel />
					<OnlineEventLinkPanel />
					<GuestLimitPanel />
					<MaxAttendanceLimitPanel />
					<AnonymousRsvpPanel />
					<NotifyMembersPanel />
				</VStack>
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Registers the 'gatherpress-event-settings' plugin.
 *
 * This function registers a custom plugin named 'gatherpress-event-settings' and
 * associates it with the `EventSettings` component for rendering.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
registerPlugin( 'gatherpress-event-settings', {
	render: EventSettings,
} );

/**
 * Toggles the visibility of the 'gatherpress-event-settings' panel in the Block Editor.
 *
 * This function ensures that the 'gatherpress-event-settings' panel is open in the WordPress
 * block editor. It uses the `domReady` function to ensure the DOM is ready before execution.
 * If the 'gatherpress-event-settings' panel is not open, it opens the panel using the
 * `toggleEditorPanelOpened` function.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady( () => {
	const selectEditPost = select( 'core/edit-post' );
	const dispatchEditor = dispatch( 'core/editor' );

	if ( ! selectEditPost || ! dispatchEditor ) {
		return;
	}

	const isEventSettingsPanelOpen = selectEditPost.isEditorPanelOpened(
		'gatherpress-event-settings/gatherpress-event-settings',
	);

	if ( ! isEventSettingsPanelOpen ) {
		dispatchEditor.toggleEditorPanelOpened(
			'gatherpress-event-settings/gatherpress-event-settings',
		);
	}
} );
