/**
 * WordPress dependencies
 */
import { select } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { loadGoogleMapsApi } from '../helpers/google-maps-api';
import {
	getGoogleMapEmbedSrc,
	toGoogleMapType,
	toMapsEmbedApiMapType,
} from '../helpers/map-embed';

/**
 * GoogleMap component for GatherPress.
 *
 * Renders the interactive Google map for the venue-map block. With a Google
 * Maps API key configured the map runs on the Maps JavaScript API — the only
 * Google surface that honors all four map types (roadmap, satellite, hybrid,
 * terrain). Without a key the component falls back to the keyless legacy
 * embed iframe, which supports roadmap and satellite only.
 *
 * @since 0.27.0
 *
 * @param {Object} props             - Component properties.
 * @param {string} props.location    - The location to be displayed on the map.
 * @param {number} props.latitude    - The latitude coordinate to be displayed on the map.
 * @param {number} props.longitude   - The longitude coordinate to be displayed on the map.
 * @param {number} [props.zoom=10]   - The zoom level of the map.
 * @param {string} [props.type]      - Map type slug: roadmap, satellite, hybrid, terrain.
 * @param {string} [props.className] - Additional CSS class names for styling.
 * @param {string} [props.apiKey=''] - Google Maps API key; empty keeps the keyless embed URL.
 *
 * @return {JSX.Element} The rendered React component.
 */

/**
 * Canonical Google map types for the venue-map block: localized label, block
 * attribute slug, and legacy keyless embed `t=` parameter letter.
 *
 * The slugs double as Maps JavaScript API `mapTypeId` values, so the keyed
 * interactive path passes them through untouched. The `legacyEmbedLetter`
 * values back the keyless embed fallback.
 *
 * @see https://developers.google.com/maps/documentation/javascript/maptypes
 * @see https://developers.google.com/maps/documentation/embed/map-parameters
 */
export const GOOGLE_MAP_TYPE_DEFINITIONS = [
	{
		slug: 'roadmap',
		label: __( 'Roadmap', 'gatherpress' ),
		legacyEmbedLetter: 'm',
	},
	{
		slug: 'satellite',
		label: __( 'Satellite', 'gatherpress' ),
		legacyEmbedLetter: 'k',
	},
	{
		slug: 'hybrid',
		label: __( 'Hybrid', 'gatherpress' ),
		legacyEmbedLetter: 'h',
	},
	{
		slug: 'terrain',
		label: __( 'Terrain', 'gatherpress' ),
		legacyEmbedLetter: 'p',
	},
];

/**
 * Slugs the keyless legacy embed iframe can't honor — it only renders the
 * roadmap and satellite views, matching the coercion in
 * `toMapsEmbedApiMapType()`. The keyed Maps JavaScript API path supports
 * every slug in `GOOGLE_MAP_TYPE_DEFINITIONS`.
 */
export const GOOGLE_KEYLESS_UNSUPPORTED_MAP_TYPE_SLUGS = [
	'hybrid',
	'terrain',
];

// The type-normalization and embed-URL helpers moved to the framework-free
// `helpers/map-embed.js` so the venue-map view module can share them; they are
// re-exported here so existing imports keep working.
export { getGoogleMapEmbedSrc, toGoogleMapType, toMapsEmbedApiMapType };

/**
 * Interactive Google map backed by the Maps JavaScript API.
 *
 * Mounts a `google.maps.Map` with a marker into a plain div, re-pointing the
 * existing instance when props change rather than tearing it down. If the
 * API script fails to load (network error, key without the Maps JavaScript
 * API enabled), the component falls back to the keyed Maps Embed API iframe
 * so the block still shows a map.
 *
 * @since 0.35.0
 *
 * @param {Object}  props              - Component properties.
 * @param {string}  props.location     - Marker tooltip text.
 * @param {string}  props.latitude     - Latitude.
 * @param {string}  props.longitude    - Longitude.
 * @param {number}  props.zoom         - Zoom level.
 * @param {string}  props.type         - Map type slug.
 * @param {string}  props.className    - Additional CSS class names.
 * @param {Object}  props.style        - Wrapper inline style.
 * @param {string}  props.apiKey       - Google Maps API key (non-empty).
 * @param {boolean} props.isPostEditor - Whether the map renders inside the post editor canvas.
 *
 * @return {JSX.Element} The rendered React component.
 */
const GoogleMapsApiMap = ( {
	location,
	latitude,
	longitude,
	zoom,
	type,
	className,
	style,
	apiKey,
	isPostEditor,
} ) => {
	const containerRef = useRef( null );
	const mapRef = useRef( null );
	const markerRef = useRef( null );
	const [ loadFailed, setLoadFailed ] = useState( false );

	const z = zoom || 10;
	const safeType = toGoogleMapType( type );

	useEffect( () => {
		let cancelled = false;

		// The editor canvas can live in its own iframe — the API script must
		// load into the document that owns the map container or it bootstraps
		// against the wrong window.
		const doc = containerRef.current?.ownerDocument || document;

		loadGoogleMapsApi( apiKey, doc )
			.then( ( maps ) => {
				if ( cancelled || ! containerRef.current ) {
					return;
				}

				const center = {
					lat: parseFloat( latitude ),
					lng: parseFloat( longitude ),
				};

				if ( ! mapRef.current ) {
					mapRef.current = new maps.Map( containerRef.current, {
						center,
						zoom: z,
						mapTypeId: safeType,
					} );
					markerRef.current = new maps.Marker( {
						position: center,
						map: mapRef.current,
						title: location,
					} );
					return;
				}

				// Re-point the live instance instead of remounting — keeps
				// the editor preview smooth while controls change. The
				// marker is created alongside the map, so it's always set
				// whenever the map is.
				mapRef.current.setCenter( center );
				mapRef.current.setZoom( z );
				mapRef.current.setMapTypeId( safeType );
				markerRef.current.setPosition( center );
				markerRef.current.setTitle( location );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadFailed( true );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ apiKey, latitude, longitude, z, safeType, location ] );

	// The Maps JavaScript API didn't come up — fall back to the keyed Maps
	// Embed API iframe (roadmap/satellite only) so the block still renders.
	if ( loadFailed ) {
		return (
			<iframe
				src={ getGoogleMapEmbedSrc( {
					latitude,
					longitude,
					zoom: z,
					type: safeType,
					apiKey,
				} ) }
				style={ { ...style, border: 0 } }
				className={ className }
				title={ location }
				{ ...( isPostEditor && { inert: '' } ) }
			></iframe>
		);
	}

	return (
		<div
			ref={ containerRef }
			className={ className }
			// Carry the block's border-radius down so the map canvas stays
			// clipped to the same rounded corners as the wrapper.
			style={ { ...style, borderRadius: 'inherit', overflow: 'hidden' } }
			{ ...( isPostEditor && { inert: '' } ) }
		></div>
	);
};

const GoogleMap = ( props ) => {
	const {
		zoom,
		type,
		className,
		location,
		latitude,
		longitude,
		apiKey = '',
	} = props;

	// Fill the wrapper — the venue-map wrapper is the single source of
	// size (explicit height or aspect ratio), matching OpenStreetMap.
	const style = { border: 0, height: '100%', width: '100%' };

	// Check for valid latitude and longitude before rendering.
	const validLat =
		latitude && '' !== latitude && ! isNaN( parseFloat( latitude ) );
	const validLng =
		longitude && '' !== longitude && ! isNaN( parseFloat( longitude ) );

	// Show placeholder when no valid coordinates — avoids keyed API
	// requests with an invalid `center` on new events before geocode.
	if ( ! validLat || ! validLng ) {
		return (
			<div
				className={ className }
				style={ {
					...style,
					backgroundColor: '#e0e0e0',
					display: 'flex',
					alignItems: 'center',
					justifyContent: 'center',
					color: '#757575',
				} }
			></div>
		);
	}

	// Matches the OpenStreetMap editor fix: the `inert` attribute stops the
	// map from capturing clicks/focus so the user can select the block
	// itself instead of interacting with the embedded map inside the canvas.
	const isPostEditor = Boolean( select( 'core/edit-post' ) );

	const trimmedKey = ( apiKey || '' ).trim();

	if ( trimmedKey ) {
		return (
			<GoogleMapsApiMap
				location={ location }
				latitude={ latitude }
				longitude={ longitude }
				zoom={ zoom }
				type={ type }
				className={ className }
				style={ style }
				apiKey={ trimmedKey }
				isPostEditor={ isPostEditor }
			/>
		);
	}

	const srcURL = getGoogleMapEmbedSrc( {
		latitude,
		longitude,
		zoom,
		type,
		apiKey: '',
	} );

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
