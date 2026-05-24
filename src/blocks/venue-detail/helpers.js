/**
 * Helper functions for the venue-detail block.
 *
 * @since 0.34.0
 */

/**
 * Venue fields the venue-detail block knows how to render.
 *
 * Each entry pairs a `fieldType` (the block-attribute value the editor stores)
 * with the `metaKey` it reads/writes. Stored as a list of objects rather than
 * a one-directional map so consumers can resolve in either direction without
 * implying that one side is "primary." Today the block binds by `fieldType`
 * (matching the SelectControl in `edit.js`), so `getMetaKey()` is the only
 * lookup helper exposed; if the model ever flips to bind by `metaKey`, a
 * sibling `getFieldType()` drops in cleanly.
 *
 * @type {Array<{ fieldType: string, metaKey: string }>}
 */
export const VENUE_FIELDS = [
	{ fieldType: 'address', metaKey: 'gatherpress_address' },
	{ fieldType: 'phone', metaKey: 'gatherpress_phone' },
	{ fieldType: 'url', metaKey: 'gatherpress_website' },
];

/**
 * Cleans a URL for display by removing protocol, www, and trailing slash.
 *
 * @since 0.34.0
 *
 * @param {string} url The URL to clean.
 * @return {string} The cleaned URL for display.
 */
export function cleanUrlForDisplay( url ) {
	if ( ! url ) {
		return '';
	}
	return url
		.replace( /^https?:\/\//, '' )
		.replace( /^www\./, '' )
		.replace( /\/$/, '' );
}

/**
 * Gets the venue meta key for a given field type.
 *
 * @since 0.34.0
 *
 * @param {string} fieldType The field type (address, phone, url).
 * @return {string} The corresponding meta key, or empty string if not found.
 */
export function getMetaKey( fieldType ) {
	return (
		VENUE_FIELDS.find( ( field ) => field.fieldType === fieldType )
			?.metaKey || ''
	);
}
