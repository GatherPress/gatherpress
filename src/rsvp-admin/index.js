/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Filters from './filters';

/**
 * Take over the RSVP screen's filters.
 *
 * `List_Table::extra_tablenav()` writes the controls, so the row is complete
 * before this runs; this replaces them with the same controls wired up.
 *
 * @since 0.36.0
 *
 * @return {void}
 */
function mountFilters() {
	document
		.querySelectorAll( '.gatherpress-rsvp-filters' )
		.forEach( ( root ) => {
			const {
				postTypes = '',
				postId = '',
				label = '',
				statuses = '[]',
				selected = '',
			} = root.dataset;

			let parsedStatuses = [];

			try {
				parsedStatuses = JSON.parse( statuses );
			} catch {
				// Malformed payload: the response filter has nothing to offer,
				// and the event picker still works.
			}

			createRoot( root ).render(
				<Filters
					postTypes={ postTypes ? postTypes.split( ',' ) : [] }
					initialPostId={ postId ? parseInt( postId, 10 ) : null }
					eventLabel={ label || __( 'Filter by event', 'gatherpress' ) }
					statuses={ parsedStatuses }
					initialResponses={ selected ? selected.split( ',' ) : [] }
				/>
			);
		} );
}

domReady( mountFilters );
