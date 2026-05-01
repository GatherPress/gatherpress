/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * GoogleMap component for GatherPress.
 *
 * This component is used to embed a Google Map with specified location,
 * zoom level, map type, and height.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {number} props.latitude     - The latitude coordinate to be displayed on the map.
 * @param {number} props.longitude    - The longitude coordinate to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {string} [props.type]       - Map type slug: roadmap, satellite, hybrid, terrain.
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 * @param {string} [props.apiKey='']  - Google Maps API key; empty keeps the keyless embed URL.
 *
 * @return {JSX.Element} The rendered React component.
 */

/**
 * Map types supported by the authenticated Maps Embed API (view mode).
 *
 * @see https://developers.google.com/maps/documentation/embed/embedding-map#optional_parameters
 */
const GOOGLE_EMBED_MAP_TYPES = [
	'roadmap',
	'satellite',
	'hybrid',
	'terrain',
];

/**
 * Google Maps legacy keyless embed uses single-letter `t=` query values.
 *
 * @see https://developers.google.com/maps/documentation/embed/map-parameters
 */
const GOOGLE_MAP_TYPE_CODES = {
	roadmap: 'm',
	satellite: 'k',
	hybrid: 'h',
	terrain: 'p',
};

const GOOGLE_EMBED_VIEW_BASE = 'https://www.google.com/maps/embed/v1/view';
const GOOGLE_LEGACY_EMBED_BASE = 'https://maps.google.com/maps';

/**
 * Builds the iframe `src` for a Google map embed.
 *
 * @param {Object} params           Parameters.
 * @param {string} params.latitude  Latitude.
 * @param {string} params.longitude Longitude.
 * @param {number} params.zoom      Zoom level.
 * @param {string} params.type      Map type slug from the block.
 * @param {string} params.apiKey    API key or empty string.
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
	const safeType =
		type && GOOGLE_EMBED_MAP_TYPES.includes( type ) ? type : 'roadmap';
	const trimmedKey = ( apiKey || '' ).trim();

	if ( trimmedKey ) {
		const params = new URLSearchParams( {
			key: trimmedKey,
			center: `${ latitude },${ longitude }`,
			zoom: String( z ),
			maptype: safeType,
		} );
		return `${ GOOGLE_EMBED_VIEW_BASE }?${ params.toString() }`;
	}

	const params = new URLSearchParams( {
		q: `${ latitude },${ longitude }`,
		z: String( z ),
		t: GOOGLE_MAP_TYPE_CODES[ safeType ] || GOOGLE_MAP_TYPE_CODES.roadmap,
		output: 'embed',
	} );
	return `${ GOOGLE_LEGACY_EMBED_BASE }?${ params.toString() }`;
}

const GoogleMap = ( props ) => {
	const {
		zoom,
		type,
		className,
		location,
		latitude,
		longitude,
		height,
		apiKey = '',
	} = props;

	const style = { border: 0, height, width: '100%' };

	const srcURL = getGoogleMapEmbedSrc( {
		latitude,
		longitude,
		zoom,
		type,
		apiKey,
	} );

	// Matches the OpenStreetMap editor fix: the `inert` attribute stops the
	// iframe from capturing clicks/focus so the user can select the block
	// itself instead of interacting with the embedded map inside the canvas.
	const isPostEditor = Boolean( select( 'core/edit-post' ) );

	return (
		<iframe
			src={ srcURL }
			style={ style }
			className={ className }
			title={ location }
			{ ...( isPostEditor && { inert: '' } ) }
		></iframe>
	);
};

export default GoogleMap;
