/**
 * Helper functions for the venue-detail block.
 *
 * @since 1.0.0
 */

/**
 * Mapping of field types to individual venue meta keys.
 *
 * @type {Object}
 */
export const VENUE_FIELD_MAPPING = {
	address: 'gatherpress_address',
	phone: 'gatherpress_phone',
	url: 'gatherpress_website',
};

/**
 * Cleans a URL for display by removing protocol, www, and trailing slash.
 *
 * @since 1.0.0
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
 * @since 1.0.0
 *
 * @param {string} fieldType The field type (address, phone, url).
 * @return {string} The corresponding meta key, or empty string if not found.
 */
export function getMetaKey( fieldType ) {
	return VENUE_FIELD_MAPPING[ fieldType ] || '';
}
