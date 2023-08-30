/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Venue from '../../components/Venue';
import MapEmbed from '../../components/MapEmbed';

domReady(() => {
	let containers = document.querySelectorAll(`[data-gp_block_name="venue"]`);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
			<Venue
				name={attrs.name ?? ''}
				fullAddress={attrs.fullAddress ?? ''}
				phoneNumber={attrs.phoneNumber ?? ''}
				website={attrs.website ?? ''}
			/>
		);
	}

	containers = document.querySelectorAll(`[data-gp_block_name="map-embed"]`);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
			<MapEmbed
				location={attrs.fullAddress}
				zoom={attrs.mapZoomLevel}
				type={attrs.mapType}
				height={attrs.mapHeight}
			/>
		);
	}
});
