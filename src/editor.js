/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { dispatch, select } from '@wordpress/data';
import { getBlockType, unregisterBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from './helpers/globals';
import { hasEventPastNotice } from './helpers/event';
import EmailNotificationManager from './components/EmailNotificationManager';
import './commands';
import './stores';
import './supports/post-id-override';
import './supports/block-guard';

/**
 * Ensure Panels are Open for Events
 *
 * This script ensures that the editor sidebar is open in the WordPress block editor.
 * It uses the `domReady` function to ensure the DOM is ready before execution.
 * If the editor sidebar is not open, it opens the general sidebar, and displays a
 * notice for past events using the `hasEventPastNotice` function.
 *
 * @since 1.0.0
 */

// Execute the following code when the DOM is ready.
domReady( () => {
	const selectEditPost = select( 'core/edit-post' );
	const dispatchEditPost = dispatch( 'core/edit-post' );

	if ( ! selectEditPost || ! dispatchEditPost ) {
		return;
	}

	const isEditorSidebarOpened =
		selectEditPost.isEditorSidebarOpened( 'edit-post/document' );

	if ( ! isEditorSidebarOpened ) {
		dispatchEditPost.openGeneralSidebar( 'edit-post/document' );
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

/**
 * Remove Unwanted Blocks
 *
 * This script removes unwanted blocks from the localized array.
 * It utilizes the `domReady` function to ensure the DOM is ready before execution.
 * It iterates through the keys of the 'unregister_blocks' array obtained from the global scope,
 * retrieves the block name, and unregisters the block using the `unregisterBlockType` function.
 *
 * @since 1.0.0
 */

// Execute the following code when the DOM is ready.
domReady( () => {
	// Iterate through keys of the 'unregister_blocks' array in the global scope.
	Object.keys( getFromGlobal( 'misc.unregisterBlocks' ) ).forEach( ( key ) => {
		// Retrieve the block name using the key.
		const blockName = getFromGlobal( 'misc.unregisterBlocks' )[ key ];

		// Check if the block name is defined and unregister the block.
		if ( blockName && 'undefined' !== typeof getBlockType( blockName ) ) {
			unregisterBlockType( blockName );
		}
	} );
} );
