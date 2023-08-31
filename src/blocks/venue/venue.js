/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../components/MapEmbed';
import VenueOrOnlineEvent from '../../components/VenueOrOnlineEvent';

domReady(() => {
	let containers = document.querySelectorAll(`[data-gp_block_name="venue"]`);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
			<VenueOrOnlineEvent
				name={attrs.name ?? ''}
				fullAddress={attrs.fullAddress ?? ''}
				phoneNumber={attrs.phoneNumber ?? ''}
				website={attrs.website ?? ''}
				isOnlineEventTerm={attrs.isOnlineEventTerm ?? false}
				onlineEventLink={attrs.onlineEventLink ?? ''}
			/>
		);
	}

	containers = document.querySelectorAll(`[data-gp_block_name="map-embed"]`);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		if (attrs.isOnlineEventTerm) {
			continue;
		}

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
