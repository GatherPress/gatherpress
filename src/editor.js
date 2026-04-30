/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { hasEventPastNotice } from './helpers/event';
import EmailNotificationManager from './components/EmailNotificationManager';
import './stores';
import './supports/post-id-override';
import './supports/post-date-override';
import './supports/post-date-convert';
import './supports/block-guard';
import './formats/tooltip';

/**
 * Editor Initialization
 *
 * Ensures the editor sidebar is open, initializes the email notification manager,
 * and displays a notice for past events.
 *
 * @since 1.0.0
 */
domReady( () => {
	const selectEditPost = select( 'core/edit-post' );
	const dispatchEditPost = dispatch( 'core/edit-post' );

	if ( selectEditPost && dispatchEditPost ) {
		const isEditorSidebarOpened =
			selectEditPost.isEditorSidebarOpened( 'edit-post/document' );

		if ( ! isEditorSidebarOpened ) {
			dispatchEditPost.openGeneralSidebar( 'edit-post/document' );
		}
	}

	// Initialize email notification manager as a React component.
	if ( null === document.getElementById( 'gatherpress-email-notification-manager' ) ) {
		const container = document.createElement( 'div' );
		container.id = 'gatherpress-email-notification-manager';
		container.style.display = 'none';
		document.body.appendChild( container );

		const { createRoot } = wp.element;
		const root = createRoot( container );
		root.render( wp.element.createElement( EmailNotificationManager ) );
	}

	hasEventPastNotice();
} );
