/**
 * WordPress dependencies.
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import UrlRewritePreview from '../components/UrlRewritePreview';

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
export function urlRewritePreview() {
	// Select all elements with the attribute 'data-gatherpress_component_name' set to 'urlrewrite-preview'.
	const urlRewritePreviewContainers = document.querySelectorAll(
		`[data-gatherpress_component_name="urlrewrite-preview"]`,
	);

	// Iterate through each matched element and initialize UrlRewritePreview component.
	for ( const container of urlRewritePreviewContainers ) {
		// Parse attributes from the 'data-gatherpress_component_attrs' attribute.
		const attrs = JSON.parse(
			container.dataset.gatherpress_component_attrs,
		);

		// Create a root element and render the UrlRewritePreview component with the parsed attributes.
		createRoot( container ).render(
			<UrlRewritePreview attrs={ attrs } />,
		);
	}
}
