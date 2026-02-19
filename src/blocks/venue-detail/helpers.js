/**
 * Helper functions for the venue-detail block.
 *
 * @since 1.0.0
 */

/**
 * Mapping of field types to JSON field names.
 *
 * @type {Object}
 */
export const VENUE_FIELD_MAPPING = {
	address: 'fullAddress',
	phone: 'phoneNumber',
	url: 'website',
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
 * Gets the JSON field name for a given field type.
 *
 * @since 1.0.0
 *
 * @param {string} fieldType The field type (address, phone, url).
 * @return {string} The corresponding JSON field name, or empty string if not found.
 */
export function getJsonFieldName( fieldType ) {
	return VENUE_FIELD_MAPPING[ fieldType ] || '';
}
