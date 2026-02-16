/**
 * WordPress dependencies.
 */
import { store } from '@wordpress/interactivity';

/**
 * GatherPress tooltip Interactivity API store.
 *
 * Provides initialization for tooltip CSS custom properties on the frontend.
 * The actual tooltip display is handled via pure CSS.
 *
 * @since 1.0.0
 */
const { actions } = store( 'gatherpress', {
	actions: {
		/**
		 * Initialize tooltip custom properties from data attributes.
		 *
		 * Called on page load to set CSS custom properties based on
		 * the data attributes on each tooltip element.
		 */
		initTooltips() {
			const tooltips = document.querySelectorAll(
				'.gatherpress-tooltip[data-gatherpress-tooltip]'
			);

			tooltips.forEach( ( tooltip ) => {
				const textColor = tooltip.dataset.gatherpressTooltipTextColor;
				const bgColor = tooltip.dataset.gatherpressTooltipBgColor;

				if ( textColor ) {
					tooltip.style.setProperty(
						'--gatherpress-tooltip-text-color',
						textColor
					);
				}

				if ( bgColor ) {
					tooltip.style.setProperty(
						'--gatherpress-tooltip-bg-color',
						bgColor
					);
				}
			} );
		},
	},
} );

// Initialize tooltips on DOMContentLoaded or immediately if already loaded.
if ( 'loading' !== document.readyState ) {
	actions.initTooltips();
} else {
	document.addEventListener( 'DOMContentLoaded', actions.initTooltips );
}
