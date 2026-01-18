/**
 * TODO: Remove from coverage exclusion in .github/coverage-config.json once this file is deleted (planned for v0.34.0).
 *
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../components/MapEmbed';
import VenueOrOnlineEvent from '../../components/VenueOrOnlineEvent';

/**
 * Render GatherPress Venue and Map Embed blocks.
 *
 * This code initializes the rendering of GatherPress Venue and Map Embed blocks
 * on the frontend. It identifies the blocks using the 'data-gatherpress_block_name' attribute,
 * extracts the block attributes from the dataset, and renders the corresponding components.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady( () => {
	let containers = document.querySelectorAll(
		`[data-gatherpress_block_name="venue"]`,
	);

	for ( const container of containers ) {
		const attrs = JSON.parse( container.dataset.gatherpress_block_attrs );

		createRoot( container ).render(
			<VenueOrOnlineEvent
				name={ attrs.name ?? '' }
				fullAddress={ attrs.fullAddress ?? '' }
				phoneNumber={ attrs.phoneNumber ?? '' }
				website={ attrs.website ?? '' }
				isOnlineEventTerm={ attrs.isOnlineEventTerm ?? false }
				onlineEventLink={ attrs.onlineEventLink ?? '' }
			/>,
		);
	}

	containers = document.querySelectorAll(
		`[data-gatherpress_block_name="map-embed"]`,
	);

	for ( const container of containers ) {
		const attrs = JSON.parse( container.dataset.gatherpress_block_attrs );

		if ( attrs.isOnlineEventTerm ) {
			continue;
		}

		createRoot( container ).render(
			<MapEmbed
				location={ attrs.fullAddress }
				latitude={ attrs.latitude }
				longitude={ attrs.longitude }
				zoom={ attrs.mapZoomLevel }
				type={ attrs.mapType }
				height={ attrs.mapHeight }
			/>,
		);
	}
} );
