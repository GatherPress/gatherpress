/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal } from '../../helpers/globals';

/**
 * Initialize the GatherPress RSVP blocks.
 *
 * This code initializes the RSVP blocks for rendering on the frontend.
 * It targets all elements with the data attribute 'data-gp_block_name="rsvp"'
 * and renders the RSVP component inside them.
 * The type of RSVP block ('past' or 'upcoming') is determined based on the global
 * variable 'has_event_past'.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady(() => {
	const containers = document.querySelectorAll(`[data-gp_block_name="rsvp"]`);

	const type = true === getFromGlobal('has_event_past') ? 'past' : 'upcoming';

	for (let i = 0; i < containers.length; i++) {
		createRoot(containers[i]).render(
			<Rsvp
				eventId={getFromGlobal('post_id')}
				currentUser={getFromGlobal('current_user')}
				type={type}
			/>
		);
	}
});
