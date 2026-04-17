/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies.
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect, useState, useRef } from '@wordpress/element';
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromConfig } from '../helpers/editor-settings';

/**
 * OpenStreetMap component for GatherPress.
 *
 * This component is used to embed an OpenStreetMap with specified location,
 * zoom level, and height using the Leaflet platform.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {string} props.latitude     - The latitude of the location to be displayed on the map.
 * @param {string} props.longitude    - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const OpenStreetMap = ( props ) => {
	const {
		zoom = 10,
		className,
		location,
		height = 300,
		latitude,
		longitude,
		pluginUrl,
	} = props;
	const [ Leaflet, setLeaflet ] = useState( null );
	const mapId = `map-${ uuidv4() }`;
	const mapRef = useRef( null );
	const mapInstanceRef = useRef( null );
	const style = { height };
	const isPostEditor = Boolean( select( 'core/edit-post' ) );

	useEffect( () => {
		// Load Leaflet and its assets dynamically.
		const loadLeaflet = async () => {
			const { default: L } = await import( 'leaflet' );

			// Import CSS files.
			await import( 'leaflet/dist/leaflet.css' );
			// eslint-disable-next-line import/no-extraneous-dependencies
			await import(
				'leaflet-gesture-handling/dist/leaflet-gesture-handling.css'
			);

			// Import marker images.
			await import( 'leaflet/dist/images/marker-icon-2x.png' );
			await import( 'leaflet/dist/images/marker-shadow.png' );

			// Import gesture handling.
			// eslint-disable-next-line import/no-extraneous-dependencies
			await import( 'leaflet-gesture-handling' );

			// Add gesture handling to Leaflet.
			L.Map.addInitHook(
				'addHandler',
				'gestureHandling',
				L.GestureHandling,
			);

			setLeaflet( L );
		};

		loadLeaflet();
	}, [] );

	useEffect( () => {
		// Check for valid latitude and longitude (not empty strings, null, or undefined).
		const validLat = latitude && '' !== latitude && ! isNaN( parseFloat( latitude ) );
		const validLng = longitude && '' !== longitude && ! isNaN( parseFloat( longitude ) );

		if ( ! Leaflet || ! validLat || ! validLng || ! mapRef.current ) {
			return;
		}

		// Clean up previous map instance if it exists.
		if ( mapInstanceRef.current ) {
			mapInstanceRef.current.remove();
			mapInstanceRef.current = null;
		}

		// Create new map instance.
		const map = Leaflet.map( mapRef.current, {
			gestureHandling: true,
			gestureHandlingOptions: {
				duration: 1500,
				text: {
					touch: __( 'Use two fingers to move the map', 'gatherpress' ),
					scroll: __(
						'Use ctrl + scroll to zoom the map',
						'gatherpress',
					),
					scrollMac: __(
						'Use ⌘ + scroll to zoom the map',
						'gatherpress',
					),
				},
			},
		} ).setView( [ latitude, longitude ], zoom );
		mapInstanceRef.current = map;

		Leaflet.Icon.Default.imagePath =
			( pluginUrl || getFromConfig( 'pluginUrl' ) ) +
			'build/images/';

		// Default to CartoDB "Positron" because OSMF's public tile server prohibits
		// third-party plugin distribution and was intermittently blocking requests.
		// The PHP `gatherpress_map_tile_url` / `gatherpress_map_tile_attribution`
		// filters let sites point at their own provider (self-hosted, MapTiler, etc.).
		const tileUrl =
			getFromConfig( 'mapTileUrl' ) ||
			'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png';
		const tileAttribution =
			getFromConfig( 'mapTileAttribution' ) ||
			sprintf(
				/* translators: %1$s: OpenStreetMap credit link; %2$s: CARTO credit link. */
				__( '© %1$s contributors © %2$s', 'gatherpress' ),
				'<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
				'<a href="https://carto.com/attributions">CARTO</a>'
			);

		Leaflet.tileLayer( tileUrl, {
			attribution: tileAttribution,
		} ).addTo( map );

		Leaflet.marker( [ latitude, longitude ] ).addTo( map ).bindPopup( location );

		return () => {
			if ( mapInstanceRef.current ) {
				mapInstanceRef.current.remove();
				mapInstanceRef.current = null;
			}
		};
	}, [ Leaflet, latitude, location, longitude, pluginUrl, zoom ] );

	// Check for valid latitude and longitude before rendering.
	const validLat = latitude && '' !== latitude && ! isNaN( parseFloat( latitude ) );
	const validLng = longitude && '' !== longitude && ! isNaN( parseFloat( longitude ) );

	// Show placeholder when no valid coordinates.
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

	if ( ! Leaflet ) {
		return null;
	}

	// Add inert attribute in editor to prevent all interactions and focus.
	const mapProps = {
		className,
		id: mapId,
		ref: mapRef,
		style,
		...( isPostEditor && { inert: '' } ),
	};

	return <div { ...mapProps }></div>;
};

export default OpenStreetMap;
