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
import EventsList from '../../components/EventsList';

/**
 * Initialize GatherPress Event List blocks.
 *
 * This code initializes the GatherPress Event List blocks on the page.
 * It targets blocks with the data attribute `data-gatherpress_block_name="events-list"`
 * and initializes the React component `EventsList` with the specified attributes.
 * If the attributes are not provided, default values are used for customization options.
 *
 * @since 1.0.0
 *
 * @see {@link EventsList} - React component for rendering the event list.
 * @see {@link domReady} - Function to execute code when the DOM is ready.
 *
 * @return {void}
 */
domReady( () => {
	const containers = document.querySelectorAll(
		`[data-gatherpress_block_name="events-list"]`,
	);

	for ( const container of containers ) {
		const attrs = JSON.parse( container.dataset.gatherpress_block_attrs );

		createRoot( container ).render(
			<EventsList
				eventOptions={
					attrs.eventOptions ?? {
						descriptionLimit: 55,
						imageSize: 'default',
						showRsvpResponse: true,
						showDescription: true,
						showFeaturedImage: true,
						showRsvp: true,
						showVenue: true,
					}
				}
				type={ attrs.type ?? 'upcoming' }
				maxNumberOfEvents={ attrs.maxNumberOfEvents ?? 5 }
				datetimeFormat={ attrs.datetimeFormat ?? 'D, M j, Y, g:i a T' }
				topics={ attrs.topics ?? [] }
			/>,
		);
	}
} );
