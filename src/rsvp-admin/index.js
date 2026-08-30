/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import EventFilter from './event-filter';

/**
 * Mount the RSVP screen's event filter.
 *
 * The screen is a classic `WP_List_Table` page, so the filter is a React root
 * inside the existing GET form rather than a block-editor control. `render.php`
 * for this screen prints the mount point and the hidden input the form submits.
 *
 * @since 0.36.0
 *
 * @return {void}
 */
function mountEventFilter() {
	const root = document.getElementById( 'gatherpress-rsvp-event-filter' );

	if ( ! root ) {
		return;
	}

	const { postTypes = [], postId = '', label = '' } = root.dataset;

	createRoot( root ).render(
		<EventFilter
			postTypes={ postTypes ? postTypes.split( ',' ) : [] }
			initialPostId={ postId ? parseInt( postId, 10 ) : null }
			label={ label || __( 'Filter by event', 'gatherpress' ) }
		/>
	);
}

domReady( mountEventFilter );
