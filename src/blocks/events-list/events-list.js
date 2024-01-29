/**
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
 * It targets blocks with the data attribute `data-gp_block_name="events-list"`
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
domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="events-list"]`
	);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
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
				type={attrs.type ?? 'upcoming'}
				maxNumberOfEvents={attrs.maxNumberOfEvents ?? 5}
				topics={attrs.topics ?? []}
			/>
		);
	}
});
