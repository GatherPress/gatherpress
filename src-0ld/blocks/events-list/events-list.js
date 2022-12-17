/**
 * External dependencies.
 */
import React from 'react';

/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import EventsList from '../../components/EventsList';

const containers = document.querySelectorAll(
	`[data-gp_block_name="events-list"]`
);

for ( let i = 0; i < containers.length; i++ ) {
	const attrs = JSON.parse( containers[ i ].dataset.gp_block_attrs );

	render(
		<EventsList
			eventOptions={
				attrs.eventOptions ?? {
					descriptionLimit: 55,
					imageSize: 'default',
					showAttendeeList: true,
					showDescription: true,
					showFeaturedImage: true,
					showRsvpButton: true,
				}
			}
			type={ attrs.type ?? 'upcoming' }
			maxNumberOfEvents={ attrs.maxNumberOfEvents ?? 5 }
			topics={ attrs.topics ?? [] }
		/>,
		containers[ i ]
	);
}
