/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { dispatch, select, useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { isEventPostType, usePostTypeLabel } from '../../helpers/event';
import DateTimeRangePanel from './datetime-range';
import NotifyMembersPanel from './notify-members';
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
	const currentPostType = useSelect(
		( s ) => s( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	// Read the singular label so the panel title reflects what the post type
	// is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production settings" without
	// having to write a filter callback (#1612).
	const singularLabel = usePostTypeLabel(
		'singular_name',
		currentPostType,
		__( 'Event', 'gatherpress' )
	);

	/**
	 * Title of the editor's "Event settings" sidebar panel.
	 *
	 * Defaults to `<singular_name> settings`, derived from the post type's
	 * registered labels — a `production` post type with
	 * `singular_name => 'Production'` surfaces "Production settings" without
	 * any extra wiring. The filter remains for sites that need finer control
	 * (e.g. localized phrasing that doesn't round-trip cleanly through
	 * sprintf). The panel name (`gatherpress-event-settings`) and its slot
	 * registration are unchanged, so existing `EventPluginDocumentSettings`
	 * fills keep mounting in the same panel.
	 *
	 * @since 1.0.0
	 *
	 * @param {string}      title    Default panel title (`<singular_name> settings`).
	 * @param {string|null} postType Post type currently being edited, or null
	 *                               if the editor has not yet resolved one.
	 * @return {string} Panel title rendered in the sidebar.
	 *
	 * @example
	 *   addFilter(
	 *     'gatherpress.eventSettingsPanelTitle',
	 *     'my-plugin/production-panel-title',
	 *     ( title, postType ) =>
	 *       'production' === postType
	 *         ? __( 'Production settings', 'my-plugin' )
	 *         : title
	 *   );
	 */
	const panelTitle = applyFilters(
		'gatherpress.eventSettingsPanelTitle',
		sprintf(
			/* translators: %s: Singular post type label, e.g. "Event". */
			__( '%s settings', 'gatherpress' ),
			singularLabel
		),
		currentPostType
	);

	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gatherpress-event-settings"
				title={ panelTitle }
				className="gatherpress-event-settings"
			>
				{ /* Extendable entry point for "Event Settings" panel. */ }
				<EventPluginDocumentSettings.Slot />

				<VStack spacing={ 4 }>
					<DateTimeRangePanel />
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
