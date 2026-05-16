/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Autocomplete from '../components/Autocomplete';
import { dateTimePreview } from '../helpers/datetime';
import { urlRewritePreview } from '../helpers/urlrewrite';
import { initShowIfDependencies } from '../helpers/settings-show-if';

/**
 * Autocomplete Initialization
 *
 * This script initializes the autocomplete functionality for all elements
 * with the attribute 'data-gatherpress_component_name' set to 'autocomplete'.
 * It iterates through all matching elements and initializes an Autocomplete component
 * with the attributes provided in the 'data-gatherpress_component_attrs' attribute.
 *
 * @since 1.0.0
 */

// Select all elements with the attribute 'data-gatherpress_component_name' set to 'autocomplete'.
const autocompleteContainers = document.querySelectorAll(
	`[data-gatherpress_component_name="autocomplete"]`,
);

// Iterate through each matched element and initialize Autocomplete component.
for ( const container of autocompleteContainers ) {
	// Parse attributes from the 'data-gatherpress_component_attrs' attribute.
	const attrs = JSON.parse(
		container.dataset.gatherpress_component_attrs,
	);

	// Create a root element and render the Autocomplete component with the parsed attributes.
	createRoot( container ).render(
		<Autocomplete attrs={ attrs } />,
	);
}

/**
 * DateTime Preview Initialization
 *
 * This script initializes the DateTime Preview functionality for all elements
 * with the attribute 'data-gatherpress_component_name' set to 'datetime-preview'.
 * It iterates through all matching elements and initializes a DateTimePreview component
 * with the attributes provided in the 'data-gatherpress_component_attrs' attribute.
 *
 * @since 1.0.0
 */
dateTimePreview();

/**
 * UrlRewrite Preview Initialization
 *
 * This script initializes the UrlRewrite Preview functionality for all elements
 * with the attribute 'data-gatherpress_component_name' set to 'urlrewrite-preview'.
 * It iterates through all matching elements and initializes a UrlRewritePreview component
 * with the attributes provided in the 'data-gatherpress_component_attrs' attribute.
 *
 * @since 1.0.0
 */
urlRewritePreview();

/**
 * Show/hide dependent settings fields based on the value of a controlling
 * field. Driven by the `show_if` key on a field's definition; see
 * `Settings::build_row_class()` / `render_show_if_marker()` for the
 * server-side half of the contract.
 *
 * @since 1.0.0
 */
initShowIfDependencies();
