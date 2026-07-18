/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import MapEmbed from '../../components/MapEmbed';

/**
 * Upgrade venue-map blocks marked `data-render-mode="interactive"` into a live
 * Leaflet map.
 *
 * Static-mode wrappers are left alone — their pre-rendered `<img>` is the
 * final output. For interactive wrappers we read the JSON payload that
 * render.php emitted on the outer `<div>`, replace the wrapper's static
 * children with a React root, and mount `MapEmbed` (Google Maps or Leaflet).
 *
 * @since 0.34.0
 *
 * @return {void}
 */
domReady( () => {
	const containers = document.querySelectorAll(
		'[data-render-mode="interactive"][data-gatherpress_block_name="map-embed"]'
	);

	for ( const container of containers ) {
		let attrs;
		try {
			attrs = JSON.parse( container.dataset.gatherpress_block_attrs );
		} catch {
			// Malformed JSON — leave the static baseline in place.
			continue;
		}

		createRoot( container ).render(
			<MapEmbed
				location={ attrs.address }
				latitude={ attrs.latitude }
				longitude={ attrs.longitude }
				zoom={ attrs.mapZoomLevel }
				type={ attrs.mapType }
				mapPlatform={ attrs.mapPlatform }
				pluginUrl={ attrs.pluginUrl }
				googleMapsApiKey={ attrs.googleMapsApiKey }
			/>
		);
	}
} );
