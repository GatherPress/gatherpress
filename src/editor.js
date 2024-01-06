/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';
import { hasEventPastNotice } from './helpers/event';

/**
 * Ensure panels are open for Events.
 */
domReady(() => {
	const selectPost = select('core/edit-post');

	if (!selectPost) {
		return;
	}

	const dispatchPost = dispatch('core/edit-post');
	const isEditorSidebarOpened = selectPost.isEditorSidebarOpened();

	if (!isEditorSidebarOpened) {
		dispatchPost.openGeneralSidebar();
		dispatchPost.toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	} else {
		dispatchPost.openGeneralSidebar('edit-post/document');
		dispatchPost.toggleEditorPanelOpened(
			'gp-event-settings/gp-event-settings'
		);
	}

	hasEventPastNotice();
});
