/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Autocomplete from '../components/Autocomplete';
import DateTimePreview from '../components/DateTimePreview';

/**
 * Autocomplete.
 */
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

/**
 * DateTime Preview
 */
const dateTimePreviewContainers = document.querySelectorAll(
	`[data-gp_component_name="datetime-preview"]`
);

for (let i = 0; i < dateTimePreviewContainers.length; i++) {
	const attrs = JSON.parse(
		dateTimePreviewContainers[i].dataset.gp_component_attrs
	);

	createRoot(dateTimePreviewContainers[i]).render(
		<DateTimePreview attrs={attrs} />
	);
}
