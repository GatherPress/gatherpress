/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../components/MapEmbed';

/**
 * Render GatherPress Map Embed blocks on the frontend.
 *
 * This code initializes the rendering of Map Embed blocks by identifying
 * containers using the 'data-gatherpress_block_name' attribute, extracting
 * block attributes from the dataset, and rendering the MapEmbed component.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady( () => {
	const containers = document.querySelectorAll(
		`[data-gatherpress_block_name="map-embed"]`
	);

	for ( const container of containers ) {
		const attrs = JSON.parse( container.dataset.gatherpress_block_attrs );

		createRoot( container ).render(
			<MapEmbed
				location={ attrs.fullAddress }
				latitude={ attrs.latitude }
				longitude={ attrs.longitude }
				zoom={ attrs.mapZoomLevel }
				type={ attrs.mapType }
				height={ attrs.mapHeight }
				mapPlatform={ attrs.mapPlatform }
				pluginUrl={ attrs.pluginUrl }
			/>
		);
	}
} );
