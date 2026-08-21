/**
 * Maps the classes the RSVP Response filter seeds onto its dropdown items to
 * the response key each one counts.
 *
 * `rsvp-response/view.js` resolves the `%d` placeholder on the front end by
 * matching these same classes, so the editor hint has to key off them too.
 *
 * @since 0.35.0
 *
 * @type {Object<string, string>}
 */
export const RSVP_FILTER_CLASS_MAP = {
	'gatherpress--is-attending': 'attending',
	'gatherpress--is-waiting-list': 'waiting_list',
	'gatherpress--is-not-attending': 'not_attending',
};

/**
 * Resolves the RSVP response key for a dropdown item's class list.
 *
 * @since 0.35.0
 *
 * @param {string} className The block's class name attribute.
 *
 * @return {string|null} The response key, or null when the item is not an RSVP filter.
 */
export function getRsvpFilterKey( className ) {
	const classes = String( className ?? '' )
		.split( /\s+/ )
		.filter( Boolean );

	const match = classes.find( ( name ) => RSVP_FILTER_CLASS_MAP[ name ] );

	return match ? RSVP_FILTER_CLASS_MAP[ match ] : null;
}

/**
 * Strips the anchor markup a dropdown item stores around its label.
 *
 * @since 0.35.0
 *
 * @param {string} text The block's text attribute.
 *
 * @return {string} The label without markup.
 */
export function getLabelText( text ) {
	return String( text ?? '' )
		.replace( /<[^>]*>/g, '' )
		.trim();
}

/**
 * Builds the editor-only preview of a label once its count is substituted.
 *
 * Returns null when there is nothing useful to preview, so callers can skip
 * rendering the hint entirely rather than showing an empty one.
 *
 * @since 0.35.0
 *
 * @param {string} text  The block's text attribute.
 * @param {number} count The response count for this filter.
 *
 * @return {string|null} The resolved label, or null when it holds no placeholder.
 */
export function getResolvedLabelPreview( text, count ) {
	const label = getLabelText( text );

	if ( ! label || ! label.includes( '%d' ) ) {
		return null;
	}

	return label.replace( '%d', String( count ?? 0 ) );
}
