/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../components/MapEmbed';

domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="map-embed"]`
	);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		render(
			<MapEmbed
				location={attrs.fullAddress}
				zoom={attrs.mapZoomLevel}
				type={attrs.mapType}
				height={attrs.mapHeight}
			/>,
			containers[i]
		);
	}
});
