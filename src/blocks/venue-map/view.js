/**
 * WordPress dependencies
 */
import { store, getContext, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { loadGoogleMapsApi } from '../../helpers/google-maps-api';
import {
	getGoogleMapEmbedSrc,
	parseCoordinates,
	toGoogleMapType,
} from '../../helpers/map-embed';

/**
 * Venue-map frontend mount, Interactivity API edition.
 *
 * Interactive wrappers carry `data-wp-init="callbacks.init"`, whose callback
 * runs whenever the element enters the DOM — on the initial page load *and*
 * when a client-side region swap (Query Loop enhanced pagination) inserts new
 * markup. That is the property the previous `domReady` viewScript lacked:
 * maps on page 2+ of a client-side-navigated loop never mounted (#2009).
 *
 * Static-mode wrappers carry no directives and stay zero-JS; every wrapper
 * keeps its server-rendered static `<img>` as the no-JS baseline.
 *
 * This is a script module: `@wordpress/i18n`, `@wordpress/element`, and
 * `@wordpress/data` must not be imported here. Translated strings arrive
 * server-rendered inside the block's `data-wp-context` payload, and the maps
 * mount with vanilla DOM + Leaflet / Google APIs rather than React.
 */

/**
 * Memoized Leaflet loader.
 *
 * The library and the gesture-handling plugin load once per page no matter
 * how many maps mount — repeated `data-wp-init` runs (several maps in one
 * loop, successive client-side navigations) share the same promise.
 * `addInitHook` mutates Leaflet globally, so running the loader once also
 * keeps the gesture handler from being registered repeatedly.
 *
 * Only JavaScript loads here. Webpack's async CSS chunk loading does not
 * work under ESM module output, so the Leaflet styles and marker images
 * ship as the `leaflet_style` build entry, enqueued server-side by
 * render.php alongside interactive Leaflet maps.
 *
 * @type {Promise<Object>|null}
 */
let leafletPromise = null;

/**
 * Load Leaflet and the gesture-handling plugin.
 *
 * @return {Promise<Object>} Resolves with the Leaflet namespace.
 */
function loadLeaflet() {
	if ( ! leafletPromise ) {
		leafletPromise = ( async () => {
			const { default: L } = await import( 'leaflet' );

			// eslint-disable-next-line import/no-extraneous-dependencies
			const gesture = await import( 'leaflet-gesture-handling' );

			// Add gesture handling to Leaflet. Under ESM module output the
			// plugin's UMD wrapper does not attach itself to the imported
			// Leaflet instance, so read the handler from the module's own
			// exports rather than from `L`.
			L.Map.addInitHook(
				'addHandler',
				'gestureHandling',
				gesture.GestureHandling ||
					gesture.default ||
					L.GestureHandling
			);

			return L;
		} )();
	}

	return leafletPromise;
}

/**
 * Create the fill-parent container the map (or iframe) mounts into,
 * replacing the wrapper's static baseline.
 *
 * @param {HTMLElement} wrapper The venue-map block wrapper.
 *
 * @return {HTMLElement} The mounted container.
 */
function replaceBaseline( wrapper ) {
	const container = wrapper.ownerDocument.createElement( 'div' );

	// Fill the wrapper — it is the single source of size — and carry the
	// block's border-radius down so the map stays clipped to the same
	// rounded corners.
	container.style.width = '100%';
	container.style.height = '100%';
	container.style.position = 'relative';
	container.style.borderRadius = 'inherit';
	container.style.overflow = 'hidden';

	wrapper.replaceChildren( container );

	return container;
}

/**
 * Mount a Google map.
 *
 * With an API key, boots the Maps JavaScript API and falls back to the keyed
 * Maps Embed API iframe when the script fails to load (network error, key
 * without the Maps JavaScript API enabled). Without a key, embeds the keyless
 * legacy iframe directly — the primary no-key path.
 *
 * @param {HTMLElement} wrapper The venue-map block wrapper.
 * @param {Object}      context The block's Interactivity API context.
 * @param {number}      lat     Parsed latitude.
 * @param {number}      lng     Parsed longitude.
 *
 * @return {void}
 */
function mountGoogleMap( wrapper, context, lat, lng ) {
	const zoom = context.mapZoomLevel || 10;
	const type = toGoogleMapType( context.mapType );
	const apiKey = ( context.googleMapsApiKey || '' ).trim();

	const embedFallback = () => {
		const iframe = wrapper.ownerDocument.createElement( 'iframe' );
		iframe.src = getGoogleMapEmbedSrc( {
			latitude: lat,
			longitude: lng,
			zoom,
			type,
			apiKey,
		} );
		iframe.title = context.address || '';
		iframe.style.width = '100%';
		iframe.style.height = '100%';
		iframe.style.border = '0';
		wrapper.replaceChildren( iframe );
	};

	if ( ! apiKey ) {
		embedFallback();
		return;
	}

	loadGoogleMapsApi( apiKey, wrapper.ownerDocument )
		.then( ( maps ) => {
			// The wrapper may have left the DOM mid-load (client-side
			// navigation moved on) — mounting into a detached node is
			// wasted work.
			if ( ! wrapper.isConnected ) {
				return;
			}

			const container = replaceBaseline( wrapper );
			const center = { lat, lng };
			const map = new maps.Map( container, {
				center,
				zoom,
				mapTypeId: type,
			} );

			const marker = new maps.Marker( {
				position: center,
				title: context.address || '',
			} );

			// setMap() rather than passing `map` in the options above: both
			// attach the marker, but this one consumes the instance instead
			// of constructing it purely for its side effect.
			marker.setMap( map );
		} )
		.catch( () => {
			if ( wrapper.isConnected ) {
				embedFallback();
			}
		} );
}

/**
 * Mount a Leaflet (OpenStreetMap) map.
 *
 * Ports the frontend behavior of the OpenStreetMap component: gesture
 * handling, the configurable tile layer with a tiles-unavailable overlay,
 * a marker centered on the coordinate, and size revalidation after layout
 * settles and whenever the wrapper's box changes.
 *
 * @param {HTMLElement} wrapper The venue-map block wrapper.
 * @param {Object}      context The block's Interactivity API context.
 * @param {number}      lat     Parsed latitude.
 * @param {number}      lng     Parsed longitude.
 *
 * @return {void}
 */
function mountLeafletMap( wrapper, context, lat, lng ) {
	const zoom = context.mapZoomLevel || 10;
	const i18n = context.i18n || {};

	loadLeaflet().then( ( L ) => {
		if ( ! wrapper.isConnected ) {
			return;
		}

		const container = replaceBaseline( wrapper );
		const doc = wrapper.ownerDocument;

		const mapEl = doc.createElement( 'div' );
		mapEl.style.width = '100%';
		mapEl.style.height = '100%';
		mapEl.style.borderRadius = 'inherit';
		container.appendChild( mapEl );

		const map = L.map( mapEl, {
			gestureHandling: true,
			gestureHandlingOptions: {
				duration: 1500,
				text: {
					touch: i18n.gestureTouch || '',
					scroll: i18n.gestureScroll || '',
					scrollMac: i18n.gestureScrollMac || '',
				},
			},
		} ).setView( [ lat, lng ], zoom );

		L.Icon.Default.imagePath =
			( context.pluginUrl || '' ) + 'build/images/';

		// Surface a clear "unavailable" state instead of a blank gray map
		// when the tile provider fails. Only flag failure when no tile has
		// loaded — a few stray edge-tile errors on an otherwise-working
		// basemap shouldn't trip the message (#1731).
		const errorEl = doc.createElement( 'output' );
		errorEl.className = 'gatherpress-venue-map__tile-error';
		errorEl.textContent = i18n.tileError || '';
		errorEl.style.cssText =
			'position:absolute;inset:0;display:none;align-items:flex-start;' +
			'justify-content:center;padding:1rem;text-align:center;' +
			'background-color:#e0e0e0;color:#757575;border-radius:inherit;';
		container.appendChild( errorEl );

		let tilesLoaded = 0;
		const tileLayer = L.tileLayer( context.mapTileUrl || '', {
			attribution: context.mapTileAttribution || '',
		} );
		tileLayer.on( 'tileload', () => {
			tilesLoaded += 1;
			errorEl.style.display = 'none';
		} );
		tileLayer.on( 'tileerror', () => {
			if ( 0 === tilesLoaded ) {
				// Pin the message to the top so it clears the centered map
				// marker, which Leaflet paints above the overlay.
				errorEl.style.display = 'flex';
			}
		} );
		tileLayer.addTo( map );

		// Center the marker icon on the coord (both axes) so the pin's
		// visual center matches the map center — mirrors the static map's
		// centered dot. Leaflet's default anchor is the pin tip, which
		// leaves the body floating above center.
		const centeredIcon = new L.Icon.Default( {
			iconAnchor: [ 12, 20 ],
			popupAnchor: [ 0, -20 ],
			shadowAnchor: [ 12, 20 ],
		} );
		L.marker( [ lat, lng ], { icon: centeredIcon } )
			.addTo( map )
			.bindPopup( context.address || '' );

		// Leaflet reads the container's size at init time. When the wrapper
		// relies on CSS aspect-ratio (height derived from container width)
		// that size can be stale by the time the map mounts, so the computed
		// center drifts. invalidateSize() tells Leaflet to re-read the
		// container and re-center — once after the next frame for the
		// initial layout, and again whenever the wrapper's box changes.
		requestAnimationFrame( () => {
			map.invalidateSize();
			map.setView( [ lat, lng ], zoom );
		} );

		if ( 'undefined' !== typeof ResizeObserver ) {
			const resizeObserver = new ResizeObserver( () => {
				if ( ! mapEl.isConnected ) {
					resizeObserver.disconnect();
					return;
				}
				map.invalidateSize();
				map.setView( [ lat, lng ], zoom );
			} );
			resizeObserver.observe( mapEl );
		}
	} ).catch( () => {
		// Leaflet failed to load — the server-rendered static baseline is
		// still in place, so the block degrades to the static map image.
	} );
}

store( 'gatherpress/venue-map', {
	callbacks: {
		init() {
			const { ref } = getElement();

			// A region swap can reuse a wrapper element that already booted;
			// mounting twice would stack a second map over the first.
			if ( ! ref || ref.dataset.gatherpressMapMounted ) {
				return;
			}

			const context = getContext();

			const { valid, lat, lng } = parseCoordinates(
				context.latitude,
				context.longitude
			);

			// No mappable coordinates — leave the server-rendered static
			// baseline in place.
			if ( ! valid ) {
				return;
			}

			ref.dataset.gatherpressMapMounted = 'true';

			if ( 'google' === context.mapPlatform ) {
				mountGoogleMap( ref, context, lat, lng );
				return;
			}

			mountLeafletMap( ref, context, lat, lng );
		},
	},
} );
