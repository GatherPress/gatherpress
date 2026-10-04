/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal dependencies
 */
import { usePostTypeSupports } from '../../helpers/event';
import ChecklistPanel from './checklist';
import { ChecklistPluginDocumentSettings } from './slot';

/**
 * A settings panel for the event's organizer checklist.
 *
 * The checklist is shared working state for the people running an event: who
 * has been contacted, whose compliance review is done, which invoice is still
 * outstanding, and so on. It is stored as JSON in the `gatherpress_checklist`
 * post meta, so it saves with the event and is only readable by users who can
 * edit that event.
 *
 * The panel is gated on the `gatherpress-event-checklist` support through the
 * reactive hook: supports come out of the post type registry, which is not
 * cached on the editor's first render, so a non-reactive read would leave the
 * panel permanently hidden on every post type.
 *
 * @since TBD
 *
 * @return {JSX.Element | null} The checklist settings panel, or null for post
 *                              types without checklist support.
 */
const EventChecklistSettings = () => {
	const supportsChecklist = usePostTypeSupports(
		'gatherpress-event-checklist',
	);

	return (
		supportsChecklist && (
			<PluginDocumentSettingPanel
				name="gatherpress-event-checklist"
				title={ __( 'Checklist', 'gatherpress' ) }
				className="gatherpress-event-checklist"
			>
				{ /* Extendable entry point for the "Checklist" panel. */ }
				<ChecklistPluginDocumentSettings.Slot />

				<ChecklistPanel />
			</PluginDocumentSettingPanel>
		)
	);
};

/**
 * Registers the 'gatherpress-event-checklist' plugin.
 *
 * @since TBD
 *
 * @return {void}
 */
registerPlugin( 'gatherpress-event-checklist', {
	render: EventChecklistSettings,
} );
