/**
 * TODO: Remove from coverage exclusion in .github/coverage-config.json once this file is deleted (planned for v0.34.0).
 *
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../components/OnlineEvent';

/**
 * Initialize the rendering of GatherPress Online Event blocks.
 *
 * This code initializes the rendering of GatherPress Online Event blocks
 * by selecting all elements with the 'online-event' block name and
 * rendering the OnlineEvent component inside them with provided attributes.
 *
 * @since 1.0.0
 *
 * @return {void}
 */
domReady( () => {
	const containers = document.querySelectorAll(
		`[data-gatherpress_block_name="online-event"]`,
	);

	for ( let i = 0; i < containers.length; i++ ) {
		const attrs = JSON.parse( containers[ i ].dataset.gatherpress_block_attrs );

		createRoot( containers[ i ] ).render(
			<OnlineEvent onlineEventLinkDefault={ attrs.onlineEventLink ?? '' } />,
		);
	}
} );
