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
 * Autocomplete Initialization
 *
 * This script initializes the autocomplete functionality for all elements
 * with the attribute 'data-gp_component_name' set to 'autocomplete'.
 * It iterates through all matching elements and initializes an Autocomplete component
 * with the attributes provided in the 'data-gp_component_attrs' attribute.
 *
 * @since 1.0.0
 */

// Select all elements with the attribute 'data-gp_component_name' set to 'autocomplete'.
const autocompleteContainers = document.querySelectorAll(
	`[data-gp_component_name="autocomplete"]`
);

// Iterate through each matched element and initialize Autocomplete component.
for (let i = 0; i < autocompleteContainers.length; i++) {
	// Parse attributes from the 'data-gp_component_attrs' attribute.
	const attrs = JSON.parse(
		autocompleteContainers[i].dataset.gp_component_attrs
	);

	// Create a root element and render the Autocomplete component with the parsed attributes.
	createRoot(autocompleteContainers[i]).render(
		<Autocomplete attrs={attrs} />
	);
}

/**
 * DateTime Preview Initialization
 *
 * This script initializes the DateTime Preview functionality for all elements
 * with the attribute 'data-gp_component_name' set to 'datetime-preview'.
 * It iterates through all matching elements and initializes a DateTimePreview component
 * with the attributes provided in the 'data-gp_component_attrs' attribute.
 *
 * @since 1.0.0
 */

// Select all elements with the attribute 'data-gp_component_name' set to 'datetime-preview'.
const dateTimePreviewContainers = document.querySelectorAll(
	`[data-gp_component_name="datetime-preview"]`
);

// Iterate through each matched element and initialize DateTimePreview component.
for (let i = 0; i < dateTimePreviewContainers.length; i++) {
	// Parse attributes from the 'data-gp_component_attrs' attribute.
	const attrs = JSON.parse(
		dateTimePreviewContainers[i].dataset.gp_component_attrs
	);

	// Create a root element and render the DateTimePreview component with the parsed attributes.
	createRoot(dateTimePreviewContainers[i]).render(
		<DateTimePreview attrs={attrs} />
	);
}
