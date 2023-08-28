/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpResponse from '../../components/RsvpResponse';

domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="rsvp-response"]`
	);

	for (let i = 0; i < containers.length; i++) {
		createRoot(containers[i]).render(<RsvpResponse />);
	}
});
