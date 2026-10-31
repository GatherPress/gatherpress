/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CheckIn from './check-in';
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
				checkInStates = '[]',
				checkIn = '',
			} = root.dataset;

			let parsedStatuses = [];
			let parsedCheckInStates = [];

			try {
				parsedStatuses = JSON.parse( statuses );
			} catch {
				// Malformed payload: the response filter has nothing to offer,
				// and the event picker still works.
			}

			try {
				parsedCheckInStates = JSON.parse( checkInStates );
			} catch {
				// Malformed payload: the check-in filter offers only "All".
			}

			createRoot( root ).render(
				<Filters
					postTypes={ postTypes ? postTypes.split( ',' ) : [] }
					initialPostId={ postId ? parseInt( postId, 10 ) : null }
					eventLabel={ label || __( 'Filter by event', 'gatherpress' ) }
					statuses={ parsedStatuses }
					initialResponses={ selected ? selected.split( ',' ) : [] }
					checkInStates={ parsedCheckInStates }
					initialCheckIn={ checkIn }
				/>,
			);
		} );
}

/**
 * Turn each approved RSVP's check-in cell into a dropdown.
 *
 * `Check_In_Controls::render_cell()` writes the state's icon and name, which stay
 * in place without script. The states come from the filters, which list the
 * same ones with their icons.
 *
 * @since TBD
 *
 * @return {void}
 */
export function mountCheckIns() {
	let states = [];

	try {
		states = JSON.parse(
			document.querySelector( '.gatherpress-rsvp-filters' )?.dataset
				.checkInStates ?? '[]',
		);
	} catch {
		// Malformed payload: the cells keep their read-only state.
		return;
	}

	if ( ! states.length ) {
		return;
	}

	document
		.querySelectorAll( '.gatherpress-rsvp-check-in[data-rsvp-id]' )
		.forEach( ( cell ) => {
			createRoot( cell ).render(
				<CheckIn
					rsvpId={ parseInt( cell.dataset.rsvpId, 10 ) }
					initialState={ cell.dataset.state }
					states={ states }
				/>,
			);
		} );
}

domReady( () => {
	mountFilters();
	mountCheckIns();
} );
