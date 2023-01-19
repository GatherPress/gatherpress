/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';

/**
 * Remove unwanted blocks from localized array.
 */
domReady(() => {
	const isEditorSidebarOpened = wp.data.select( 'core/edit-post' ).isEditorSidebarOpened();
	if ( ! isEditorSidebarOpened ) {
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar();
		wp.data.dispatch( 'core/edit-post' ).toggleEditorPanelOpened('gp-event-settings/gp-event-settings');
	} else {
		wp.data.dispatch( 'core/edit-post' ).toggleEditorPanelOpened('gp-event-settings/gp-event-settings');
	}
})
