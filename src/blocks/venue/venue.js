/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Venue from '../../components/Venue';

domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="venue"]`
	);

	for (let i = 0; i < containers.length; i++) {
		const attrs = JSON.parse(containers[i].dataset.gp_block_attrs);

		createRoot(containers[i]).render(
			<Venue
				name={attrs.name ?? ''}
				fullAddress={attrs.fullAddress ?? ''}
				phoneNumber={attrs.phoneNumber ?? ''}
				website={attrs.website ?? ''}
			/>
		);
	}
});
import '../../helpers/map-embed';
