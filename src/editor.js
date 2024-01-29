/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';
import { hasEventPastNotice } from './helpers/event';

/**
 * Ensure Panels are Open for Events
 *
 * This script ensures that specific panels related to Events are open in the WordPress block editor.
 * It uses the `domReady` function to ensure the DOM is ready before execution.
 * If the editor sidebar is not open, it opens the general sidebar, toggles the editor panel for event settings,
 * and displays a notice for past events using the `hasEventPastNotice` function.
 *
 * @since 1.0.0
 */

// Execute the following code when the DOM is ready.
domReady(() => {
	// Retrieve the 'core/edit-post' object from the 'select' function.
	const selectPost = select('core/edit-post');

	// Exit early if 'core/edit-post' is not available.
	if (!selectPost) {
		return;
	}

	// Retrieve the 'core/edit-post' object from the 'dispatch' function.
	const dispatchPost = dispatch('core/edit-post');

	// Check if the editor sidebar is open.
	const isEditorSidebarOpened = selectPost.isEditorSidebarOpened();

	// If the editor sidebar is not open, open the general sidebar and toggle the editor panel for event settings.
	if (!isEditorSidebarOpened) {
		dispatchPost.openGeneralSidebar();
		dispatchPost.toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	} else {
		// If the editor sidebar is open, open the general sidebar for the 'edit-post/document' panel.
		dispatchPost.openGeneralSidebar('edit-post/document');
		dispatchPost.toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	}

	// Display a notice for past events using the 'hasEventPastNotice' function.
	hasEventPastNotice();
});
