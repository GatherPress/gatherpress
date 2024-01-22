/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';

/**
 * Initialize all GatherPress RSVP Response blocks.
 *
 * This code initializes the GatherPress RSVP Response blocks on the DOM when it's ready.
 * It selects all elements with the data attribute 'data-gp_block_name="rsvp-response"'
 * and renders the RsvpResponse component within those elements.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="rsvp-response"]`
	);

	for (let i = 0; i < containers.length; i++) {
		createRoot(containers[i]).render(<RsvpResponse />);
	}
});
