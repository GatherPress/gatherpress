/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import { REST_NAMESPACE } from './namespace';

/**
 * In-memory cache for geocoding results (memoization).
 * Maps address strings to their geocoding results.
 *
 * @type {Map<string, Object>}
 */
const geocodeCache = new Map();

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
 * Geocodes an address using the GatherPress REST API proxy.
 *
 * Uses memoization to cache results and avoid duplicate API calls
 * for the same address. The PHP backend proxies requests to Nominatim
 * to avoid CORS issues and comply with Nominatim's usage policy.
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
		const response = await apiFetch( {
			path: `/${ REST_NAMESPACE }/geocode?address=${ encodeURIComponent(
				trimmedAddress
			) }`,
		} );

		// The REST API returns the result directly.
		if ( response.latitude && response.longitude ) {
			const result = {
				latitude: response.latitude,
				longitude: response.longitude,
				error: null,
			};
			// Cache successful results.
			geocodeCache.set( trimmedAddress, result );
			return result;
		}

		// No results found or error from API.
		const noResultsResponse = {
			latitude: '',
			longitude: '',
			error:
				response.error ||
				__(
					'Could not find location. Please check the address and try again.',
					'gatherpress'
				),
		};
		// Cache "not found" results since the address won't suddenly exist.
		if ( ! response.error ) {
			geocodeCache.set( trimmedAddress, noResultsResponse );
		}
		return noResultsResponse;
	} catch ( error ) {
		// Don't cache network errors - allow retry.
		return {
			latitude: '',
			longitude: '',
			error:
				error.message ||
				__( 'Geocoding request failed.', 'gatherpress' ),
		};
	}
}
