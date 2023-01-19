/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';

/**
 * Ensure panels are open for Events.
 */
domReady(() => {
	const isEditorSidebarOpened =
		select('core/edit-post').isEditorSidebarOpened();
	if (!isEditorSidebarOpened) {
		dispatch('core/edit-post').openGeneralSidebar();
		dispatch('core/edit-post').toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	} else {
		dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
		dispatch('core/edit-post').toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	}
});
