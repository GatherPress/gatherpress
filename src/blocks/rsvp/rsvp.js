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
