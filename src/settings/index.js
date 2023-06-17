/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Autocomplete from '../components/Autocomplete';

const autocompleteContainers = document.querySelectorAll(
	`[data-gp_component_name="autocomplete"]`
);

for (let i = 0; i < autocompleteContainers.length; i++) {
	const attrs = JSON.parse(
		autocompleteContainers[i].dataset.gp_component_attrs
	);

	createRoot(autocompleteContainers[i]).render(
		<Autocomplete attrs={attrs} />
	);
}
