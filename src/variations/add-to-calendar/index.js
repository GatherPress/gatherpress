/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { calendar } from '@wordpress/icons';

const NAME = 'gatherpress-add-to-calendar-details';
const VARIATION_ATTRIBUTES = {
	category: 'gatherpress',
	isActive: ['metadata.bindings.url.args.service'],
	example: {},
};
const BUTTON_ATTRIBUTES = {
	tagName: 'a', // By setting this to 'button', instead of 'a', we can completely prevent the LinkControl getting rendered into the Toolbar.
};
const SERVICES = [
	{
		service: 'google',
		text: __('Google', 'gatherpress'),
		title: __('Add event to your Google calendar.', 'gatherpress'),
	},
	{
		service: 'ical',
		text: __('iCal', 'gatherpress'),
		title: __('Download event as iCal file.', 'gatherpress'),
	},
	{
		service: 'outlook',
		text: __('Outlook', 'gatherpress'),
		title: __('Download event as Outlook file.', 'gatherpress'),
	},
	{
		service: 'yahoo',
		text: __('Yahoo', 'gatherpress'),
		title: __('Add event to your Yahoo calendar.', 'gatherpress'),
	},
];

// Helper to generate button attributes based on service.
function createButtonAttributes(serviceData) {
	const { service, title, text } = serviceData;
	return {
		...BUTTON_ATTRIBUTES,
		title,
		text,
		rel:
			service === 'google' || service === 'yahoo'
				? 'noopener norefferrer'
				: null,
		linkTarget:
			service === 'google' || service === 'yahoo' ? '_blank' : null,
		placeholder: text,
		metadata: {
			bindings: {
				url: {
					source: 'gatherpress/add-to-calendar',
					args: { service },
				},
			},
			name: text,
		},
	};
}

/**
 * Registers multiple block variations of the 'core/button' block, one per each calendar service.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
SERVICES.forEach((serviceData) => {
	const attributes = createButtonAttributes(serviceData);

	registerBlockVariation('core/button', {
		...VARIATION_ATTRIBUTES,
		name: `${NAME}-${serviceData.service}`,
		title: serviceData.text,
		description: serviceData.title,
		attributes,
	});
});

// Generate innerBlocks array dynamically based on the services.
const INNER_BLOCKS = SERVICES.map((serviceData) => [
	'core/button',
	createButtonAttributes(serviceData),
]);

/**
 * A Trap block, that looks like a single button, hohoho.
 *
 * This block-variation is only useful, because a user can pick the block directly from the inserter or the left sidebar.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation('core/buttons', {
	title: __('Add to calendar (BUTTONS)', 'gatherpress'),
	description: __(
		'Allows a user to add an event to their preferred calendar.',
		'gatherpress'
	),
	category: 'gatherpress',
	icon: calendar,
	name: `pseudo-${NAME}`,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	innerBlocks: INNER_BLOCKS,
	example: {
		innerBlocks: SERVICES.map(({text}) => ({
			name: 'core/button',
			attributes: { text },
		})),
	},
});

registerBlockVariation('core/details', {
	title: __('Add to calendar (DETAILS)', 'gatherpress'),
	description: __(
		'Allows a user to add an event to their preferred calendar.',
		'gatherpress'
	),
	category: 'gatherpress',
	icon: calendar,
	name: `pseudo-details-${NAME}`,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	attributes: {
		summary: __('Add to calendar', 'gatherpress'),
	},
	innerBlocks: [['core/buttons', {}, INNER_BLOCKS]],
	example: {
		innerBlocks: [['core/buttons', {}, INNER_BLOCKS]],
	},
});
