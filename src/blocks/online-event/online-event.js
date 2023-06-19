/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../components/OnlineEvent';
import { getFromGlobal } from '../../helpers/globals';

domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="online-event"]`
	);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
			<OnlineEvent
				eventId={getFromGlobal('post_id')}
				onlineEventLinkDefault={attrs.onlineEventLink ?? ''}
			/>
		);
	}
});
