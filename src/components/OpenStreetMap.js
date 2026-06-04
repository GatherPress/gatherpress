/**
 * External dependencies
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * WordPress dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect, useState, useRef } from '@wordpress/element';
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getFromConfig } from '../helpers/editor-settings';

/**
 * OpenStreetMap component for GatherPress.
 *
 * This component is used to embed an OpenStreetMap with specified location,
 * zoom level, and height using the Leaflet platform.
 *
 * @since 0.30.0
 *
 * @param {Object} props             - Component properties.
 * @param {string} props.location    - The location to be displayed on the map.
 * @param {string} props.latitude    - The latitude of the location to be displayed on the map.
 * @param {string} props.longitude   - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]   - The zoom level of the map.
 * @param {string} [props.className] - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const OpenStreetMap = ( props ) => {
	const {
		zoom = 10,
		className,
		location,
		latitude,
		longitude,
		pluginUrl,
	} = props;
	const [ Leaflet, setLeaflet ] = useState( null );
	const [ tilesFailed, setTilesFailed ] = useState( false );
	const mapId = `map-${ uuidv4() }`;
	const mapRef = useRef( null );
	const mapInstanceRef = useRef( null );
	// Fill the parent — the venue-map wrapper sizes itself via CSS
	// aspect-ratio / inline width+height, and the Leaflet container must
	// match so marker placement stays centered on the visible viewport
	// rather than the inner container's own (larger) center.
	const style = { width: '100%', height: '100%' };
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
		// The PHP `gatherpress_interactive_map_tile_url` /
		// `gatherpress_interactive_map_tile_attribution` filters let sites point at
		// their own provider (self-hosted, MapTiler, etc.).
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

		// Surface a clear "unavailable" state instead of a blank gray map when
		// the tile provider fails (e.g. CartoDB returning 502s for uncached
		// high-zoom tiles). Only flag failure when no tile has loaded — a few
		// stray edge-tile errors on an otherwise-working basemap shouldn't
		// trip the message (#1731).
		setTilesFailed( false );
		let tilesLoaded = 0;
		const tileLayer = Leaflet.tileLayer( tileUrl, {
			attribution: tileAttribution,
		} );
		tileLayer.on( 'tileload', () => {
			tilesLoaded += 1;
			setTilesFailed( false );
		} );
		tileLayer.on( 'tileerror', () => {
			if ( 0 === tilesLoaded ) {
				setTilesFailed( true );
			}
		} );
		tileLayer.addTo( map );

		// Center the marker icon on the coord (both axes) so the pin's
		// visual center matches the map center — mirrors the static map's
		// centered dot. Leaflet's default anchor is the pin tip, which
		// leaves the body floating above center.
		const centeredIcon = new Leaflet.Icon.Default( {
			iconAnchor: [ 12, 20 ],
			popupAnchor: [ 0, -20 ],
			shadowAnchor: [ 12, 20 ],
		} );
		Leaflet.marker( [ latitude, longitude ], { icon: centeredIcon } )
			.addTo( map )
			.bindPopup( location );

		// Leaflet reads the container's size at init time. When the wrapper
		// relies on CSS aspect-ratio (height derived from container width)
		// that size can be stale by the time the map mounts, so the
		// computed center drifts. invalidateSize() tells Leaflet to re-read
		// the container and re-center — once after the next frame for the
		// initial layout, and again whenever the container's box changes.
		requestAnimationFrame( () => {
			if ( mapInstanceRef.current ) {
				mapInstanceRef.current.invalidateSize();
				mapInstanceRef.current.setView( [ latitude, longitude ], zoom );
			}
		} );

		let resizeObserver = null;
		if ( 'undefined' !== typeof ResizeObserver ) {
			resizeObserver = new ResizeObserver( () => {
				if ( mapInstanceRef.current ) {
					mapInstanceRef.current.invalidateSize();
					mapInstanceRef.current.setView(
						[ latitude, longitude ],
						zoom
					);
				}
			} );
			resizeObserver.observe( mapRef.current );
		}

		return () => {
			if ( resizeObserver ) {
				resizeObserver.disconnect();
			}
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

	// Wrap the Leaflet container so the tile-failure overlay can sit on top of
	// it. Add inert in the editor to prevent all interactions and focus.
	const wrapperProps = {
		className,
		// Carry the block's border-radius down so the Leaflet container (and the
		// failure overlay) stay clipped to the same rounded corners — the inner
		// elements inherit from this wrapper.
		style: {
			...style,
			position: 'relative',
			borderRadius: 'inherit',
			overflow: 'hidden',
		},
		...( isPostEditor && { inert: '' } ),
	};

	return (
		<div { ...wrapperProps }>
			<div
				id={ mapId }
				ref={ mapRef }
				style={ { width: '100%', height: '100%', borderRadius: 'inherit' } }
			></div>
			{ tilesFailed && (
				<output
					className="gatherpress-venue-map__tile-error"
					style={ {
						position: 'absolute',
						inset: 0,
						display: 'flex',
						// Pin the message to the top so it clears the centered
						// map marker, which Leaflet paints above the overlay.
						alignItems: 'flex-start',
						justifyContent: 'center',
						padding: '1rem',
						textAlign: 'center',
						backgroundColor: '#e0e0e0',
						color: '#757575',
						borderRadius: 'inherit',
					} }
				>
					{ __(
						'Map could not be loaded. Please try again later.',
						'gatherpress'
					) }
				</output>
			) }
		</div>
	);
};

export default OpenStreetMap;
