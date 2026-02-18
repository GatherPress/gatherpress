/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Nominatim OpenStreetMap API base URL.
 *
 * @type {string}
 */
export const NOMINATIM_API_URL = 'https://nominatim.openstreetmap.org/search';

/**
 * In-memory cache for geocoding results (memoization).
 * Maps address strings to their geocoding results.
 *
 * @type {Map<string, Object>}
 */
const geocodeCache = new Map();

/**
 * Gets the browser/WordPress language code for Accept-Language header.
 *
 * @return {string} Language code (e.g., 'en', 'de', 'fr').
 */
export function getLanguageCode() {
	// Try WordPress document lang attribute first (set by WP).
	const htmlLang = document.documentElement.lang;
	if ( htmlLang ) {
		// Convert 'en-US' or 'de_DE' to 'en' or 'de'.
		return htmlLang.split( /[-_]/ )[ 0 ];
	}

	// Fall back to browser language.
	return navigator.language?.split( '-' )[ 0 ] || 'en';
}

/**
 * Builds the Nominatim API URL with all parameters.
 *
 * @param {string} address The address to geocode.
 * @return {string} The complete API URL.
 */
export function buildNominatimUrl( address ) {
	const params = new URLSearchParams( {
		q: address,
		format: 'geojson',
		limit: '1',
		'accept-language': getLanguageCode(),
	} );

	return `${ NOMINATIM_API_URL }?${ params.toString() }`;
}

/**
 * Clears the geocoding cache.
 * Useful for testing or when cache needs to be invalidated.
 */
export function clearGeocodeCache() {
	geocodeCache.clear();
}

/**
 * Gets the current cache size.
 * Useful for testing.
 *
 * @return {number} Number of cached entries.
 */
export function getGeocoCacheSize() {
	return geocodeCache.size;
}

/**
 * Geocodes an address using Nominatim OpenStreetMap API.
 *
 * Uses memoization to cache results and avoid duplicate API calls
 * for the same address. Follows Nominatim usage policy by including
 * accept-language and limiting results to 1.
 *
 * @since 1.0.0
 *
 * @param {string} address The full address to geocode.
 * @return {Promise<Object>} Promise resolving to { latitude, longitude, error }.
 *                           On success: { latitude: string, longitude: string, error: null }
 *                           On error: { latitude: '', longitude: '', error: string }
 */
export async function geocodeAddress( address ) {
	if ( ! address || '' === address.trim() ) {
		return { latitude: '', longitude: '', error: null };
	}

	const trimmedAddress = address.trim();

	// Check cache first (memoization).
	if ( geocodeCache.has( trimmedAddress ) ) {
		return geocodeCache.get( trimmedAddress );
	}

	try {
		const response = await fetch( buildNominatimUrl( trimmedAddress ) );

		if ( ! response.ok ) {
			const result = {
				latitude: '',
				longitude: '',
				error: sprintf(
					/* translators: %s: HTTP status text. */
					__( 'Geocoding failed: %s', 'gatherpress' ),
					response.statusText
				),
			};
			// Don't cache errors - allow retry.
			return result;
		}

		const data = await response.json();

		if ( 0 < data.features.length ) {
			const latitude = String(
				data.features[ 0 ].geometry.coordinates[ 1 ]
			);
			const longitude = String(
				data.features[ 0 ].geometry.coordinates[ 0 ]
			);
			const result = { latitude, longitude, error: null };
			// Cache successful results.
			geocodeCache.set( trimmedAddress, result );
			return result;
		}

		// No results found - cache this too since the address won't suddenly exist.
		const noResultsResponse = {
			latitude: '',
			longitude: '',
			error: __(
				'Could not find location. Please check the address and try again.',
				'gatherpress'
			),
		};
		geocodeCache.set( trimmedAddress, noResultsResponse );
		return noResultsResponse;
	} catch ( error ) {
		// Don't cache network errors - allow retry.
		return {
			latitude: '',
			longitude: '',
			error: sprintf(
				/* translators: %s: Error message. */
				__( 'Geocoding error: %s', 'gatherpress' ),
				error.message
			),
		};
	}
}
