/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { addQueryArgs } from '@wordpress/url';
import { dispatch, select, subscribe } from '@wordpress/data';
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
import './formats/tooltip';

/**
 * Editor Initialization
 *
 * Ensures the editor sidebar is open, initializes the email notification manager,
 * displays a notice for past events, and removes unwanted blocks.
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

	// Remove unwanted blocks from the localized array.
	Object.keys( getFromGlobal( 'misc.unregisterBlocks' ) ).forEach( ( key ) => {
		const blockName = getFromGlobal( 'misc.unregisterBlocks' )[ key ];

		if ( blockName && 'undefined' !== typeof getBlockType( blockName ) ) {
			unregisterBlockType( blockName );
		}
	} );
} );

/**
 * Update Editor Back Button URL
 *
 * The block editor back button links to the post type list without the
 * upcoming event filter. This updates the back button href to include
 * the gatherpress_event_query parameter so it returns to the upcoming
 * events view. Uses wp.data.subscribe to wait for the post type to be
 * available, then a MutationObserver because the React component may
 * mount after the store is ready.
 *
 * @since 1.0.0
 */
const unsubscribeBackButton = subscribe( () => {
	const postType = select( 'core/editor' )?.getCurrentPostType();

	if ( ! postType ) {
		return;
	}

	// Post type is now available; stop listening.
	unsubscribeBackButton();

	if ( 'gatherpress_event' !== postType ) {
		return;
	}

	const backUrl = addQueryArgs( 'edit.php', {
		post_type: postType,
		gatherpress_event_query: 'upcoming',
	} );

	const selector = '.edit-post-fullscreen-mode-close';

	function updateBackButton() {
		const el = document.querySelector( selector );

		if (
			el?.href &&
			! el.href.includes( 'gatherpress_event_query' )
		) {
			el.href = backUrl;
		}
	}

	const observer = new MutationObserver( updateBackButton );
	observer.observe( document.body, {
		childList: true,
		subtree: true,
	} );

	updateBackButton();
} );
