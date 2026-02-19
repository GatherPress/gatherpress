/**
 * GatherPress tooltip initialization.
 *
 * Sets CSS custom properties for tooltip colors based on data attributes.
 * The actual tooltip display is handled via pure CSS.
 *
 * @since 1.0.0
 */

/**
 * Initialize tooltip custom properties from data attributes.
 *
 * Sets CSS custom properties on each tooltip element based on
 * its data attributes for custom text and background colors.
 */
function initTooltips() {
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
}

// Initialize tooltips on DOMContentLoaded or immediately if already loaded.
if ( 'loading' !== document.readyState ) {
	initTooltips();
} else {
	document.addEventListener( 'DOMContentLoaded', initTooltips );
}
