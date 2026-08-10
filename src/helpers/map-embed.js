/**
 * Framework-free map embed helpers.
 *
 * Shared by the editor's GoogleMap component and the venue-map view script
 * module. This file must stay importable from a viewScriptModule: no
 * `@wordpress/i18n`, no `@wordpress/element`, no `@wordpress/data` — script
 * modules cannot resolve those packages (localized labels live in
 * `src/components/GoogleMap.js`, and the frontend receives translated strings
 * through the block's `data-wp-context` payload instead).
 *
 * @since 0.35.0
 */

/**
 * Canonical Google map type slugs for the venue-map block.
 *
 * The slugs double as Maps JavaScript API `mapTypeId` values, so the keyed
 * interactive path passes them through untouched.
 *
 * @see https://developers.google.com/maps/documentation/javascript/maptypes
 */
export const GOOGLE_MAP_TYPE_SLUGS = [
	'roadmap',
	'satellite',
	'hybrid',
	'terrain',
];

/**
 * Legacy keyless embed `t=` parameter letter per map type slug.
 *
 * @see https://developers.google.com/maps/documentation/embed/map-parameters
 */
export const LEGACY_EMBED_LETTER_BY_SLUG = {
	roadmap: 'm',
	satellite: 'k',
	hybrid: 'h',
	terrain: 'p',
};

/**
 * Normalize a block map-type slug to the canonical set.
 *
 * @param {string} type Map type slug from the block.
 *
 * @return {string} A slug from `GOOGLE_MAP_TYPE_SLUGS`; unknown values fall back to roadmap.
 */
export function toGoogleMapType( type ) {
	return type && GOOGLE_MAP_TYPE_SLUGS.includes( type ) ? type : 'roadmap';
}

/**
 * Maps Embed API `view` (and related) modes only allow `roadmap` or `satellite`
 * for `maptype`. Hybrid or terrain in block data (e.g. content authored while
 * an API key was configured) are coerced so embed URLs stay valid.
 *
 * @see https://developers.google.com/maps/documentation/embed/embedding-map#view_mode
 *
 * @param {string} type Map type slug from the block.
 *
 * @return {'roadmap'|'satellite'} Coercion for the embed iframe: hybrid→satellite, terrain→roadmap.
 */
export function toMapsEmbedApiMapType( type ) {
	const normalized = toGoogleMapType( type );
	if ( 'satellite' === normalized || 'hybrid' === normalized ) {
		return 'satellite';
	}
	return 'roadmap';
}

const GOOGLE_EMBED_VIEW_BASE = 'https://www.google.com/maps/embed/v1/view';
const GOOGLE_LEGACY_EMBED_BASE = 'https://maps.google.com/maps';

/**
 * Builds the iframe `src` for a Google map embed.
 *
 * With an API key this is the Maps Embed API `view` URL — only used as the
 * fallback when the Maps JavaScript API fails to load. Without a key it is
 * the keyless legacy embed URL, the primary no-key path.
 *
 * @param {Object} params           Parameters.
 * @param {string} params.latitude  Latitude.
 * @param {string} params.longitude Longitude.
 * @param {number} params.zoom      Zoom level.
 * @param {string} params.type      Map type slug from the block.
 * @param {string} params.apiKey    API key or empty string.
 *
 * @return {string} Iframe URL.
 */
export function getGoogleMapEmbedSrc( {
	latitude,
	longitude,
	zoom,
	type,
	apiKey,
} ) {
	const z = zoom || 10;
	const safeType = toGoogleMapType( type );
	const trimmedKey = ( apiKey || '' ).trim();

	if ( trimmedKey ) {
		const params = new URLSearchParams( {
			key: trimmedKey,
			center: `${ latitude },${ longitude }`,
			zoom: String( z ),
			maptype: toMapsEmbedApiMapType( safeType ),
		} );
		return `${ GOOGLE_EMBED_VIEW_BASE }?${ params.toString() }`;
	}

	// toMapsEmbedApiMapType() only ever returns roadmap or satellite, both
	// of which are always present in the letter table.
	const legacyType = toMapsEmbedApiMapType( safeType );
	const params = new URLSearchParams( {
		q: `${ latitude },${ longitude }`,
		z: String( z ),
		t: LEGACY_EMBED_LETTER_BY_SLUG[ legacyType ],
		output: 'embed',
	} );
	return `${ GOOGLE_LEGACY_EMBED_BASE }?${ params.toString() }`;
}

/**
 * Validate and parse a latitude/longitude pair from block data.
 *
 * Mirrors the validity rule the map components use: present, non-empty, and
 * numeric. Parsing is centralized so the view module and the editor agree on
 * what counts as mappable coordinates.
 *
 * @param {string|number} latitude  Latitude from block data.
 * @param {string|number} longitude Longitude from block data.
 *
 * @return {{valid: boolean, lat: number, lng: number}} Parse result; lat/lng are NaN when invalid.
 */
export function parseCoordinates( latitude, longitude ) {
	const lat = parseFloat( latitude );
	const lng = parseFloat( longitude );

	// A falsy input (empty string, null, undefined) is absent; anything else
	// must parse to a number. Matches the map components' validity rule.
	const validLat = Boolean( latitude ) && ! isNaN( lat );
	const validLng = Boolean( longitude ) && ! isNaN( lng );

	return { valid: validLat && validLng, lat, lng };
}
