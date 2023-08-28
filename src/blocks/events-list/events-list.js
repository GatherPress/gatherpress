/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import EventsList from '../../components/EventsList';

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
