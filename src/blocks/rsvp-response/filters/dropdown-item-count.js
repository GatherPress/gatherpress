/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { RSVP_COUNTS_STORE } from '../../../stores/rsvp-counts';

/**
 * Maps the classes this block seeds onto its filter items to the response
 * status each one counts.
 *
 * `rsvp-response/view.js` resolves the placeholder on the front end by
 * matching these same classes, so the editor preview keys off them too.
 *
 * @since 0.36.0
 *
 * @type {Object<string, string>}
 */
export const RSVP_FILTER_CLASS_MAP = {
	'gatherpress--is-attending': 'attending',
	'gatherpress--is-waiting-list': 'waiting_list',
	'gatherpress--is-not-attending': 'not_attending',
};

/**
 * Resolves the response status a dropdown item's class list refers to.
 *
 * @since 0.36.0
 *
 * @param {string} className The item's class name attribute.
 *
 * @return {string|null} The status key, or null when the item is not a filter.
 */
export function getRsvpFilterStatus( className ) {
	const classes = String( className ?? '' )
		.split( /\s+/ )
		.filter( Boolean );

	const match = classes.find( ( name ) => RSVP_FILTER_CLASS_MAP[ name ] );

	return match ? RSVP_FILTER_CLASS_MAP[ match ] : null;
}

/**
 * Substitutes the response count into an RSVP filter item's label.
 *
 * Returns the text untouched whenever this is not an RSVP filter item, holds
 * no placeholder, has no post to count against, or the count has not resolved
 * yet. Leaving the placeholder in place is the right fallback: it is what the
 * attribute actually stores.
 *
 * @since 0.36.0
 *
 * @param {string}   text               The item's stored text.
 * @param {Object}   details            Filter payload from the dropdown item.
 * @param {Object}   details.attributes Block attributes.
 * @param {Object}   details.context    Block context.
 * @param {Function} details.select     The editor's `select` function.
 *
 * @return {string} The text to display in the canvas.
 */
export function resolveRsvpFilterCount( text, { attributes, context, select } ) {
	if ( ! String( text ?? '' ).includes( '%d' ) ) {
		return text;
	}

	const status = getRsvpFilterStatus( attributes?.className );
	const postId = context?.postId ?? null;

	if ( ! status || ! postId ) {
		return text;
	}

	const count = select( RSVP_COUNTS_STORE ).getCount( postId, status );

	return null === count ? text : text.replace( '%d', String( count ) );
}

addFilter(
	'gatherpress.dropdownItemText',
	'gatherpress/rsvp-response-filter-count',
	resolveRsvpFilterCount,
);
