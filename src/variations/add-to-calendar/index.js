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

const BUTTON_ATTRIBUTES_GOOGLE = registerButtonVariation(
	__('Add event to your Google calendar.', 'gatherpress'),
	'Google',
	'google'
);
const BUTTON_ATTRIBUTES_ICAL = registerButtonVariation(
	__('Download event as ical file.', 'gatherpress'),
	'iCal',
	'ical'
);
const BUTTON_ATTRIBUTES_OUTLOOK = registerButtonVariation(
	__('Download event as outlook file.', 'gatherpress'),
	'Outlook',
	'outlook'
);
const BUTTON_ATTRIBUTES_YAHOO = registerButtonVariation(
	__('Add event to your Yahoo calendar.', 'gatherpress'),
	'Yahoo',
	'yahoo'
);

const INNER_BLOCKS = [
	[
		'core/button',
		{
			...BUTTON_ATTRIBUTES_GOOGLE,
		},
	],
	[
		'core/button',
		{
			...BUTTON_ATTRIBUTES_ICAL,
		},
	],
	[
		'core/button',
		{
			...BUTTON_ATTRIBUTES_OUTLOOK,
		},
	],
	[
		'core/button',
		{
			...BUTTON_ATTRIBUTES_YAHOO,
		},
	],
];

function createButtonAttributes(titleText, buttonText, service) {
	return {
		...BUTTON_ATTRIBUTES,
		title: titleText,
		text: buttonText,
		rel:
			service === 'google' || service === 'yahoo'
				? 'noopener norefferrer'
				: null,
		linkTarget:
			service === 'google' || service === 'yahoo' ? '_blank' : null,
		placeholder: buttonText,
		metadata: {
			bindings: {
				url: {
					source: 'gatherpress/add-to-calendar',
					args: {
						service,
					},
				},
			},
			name: buttonText,
		},
	};
}

/**
 * Registers multiple block variations of the 'core/button' block, one per each calendar service.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 *
 * @param {string} titleText  Text of buttons title attribute.
 * @param {string} buttonText Visible buttin text.
 * @param {string} service    Name of calendar service
 * @return {Array}           List of button attributes.
 */
function registerButtonVariation(titleText, buttonText, service) {
	const buttonAttributes = createButtonAttributes(
		titleText,
		buttonText,
		service
	);

	registerBlockVariation('core/button', {
		...VARIATION_ATTRIBUTES,
		name: NAME + '-' + service,
		title: buttonText,
		description: titleText,
		attributes: {
			...buttonAttributes,
		},
	});

	return { ...buttonAttributes };
}

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
	name: 'pseudo-' + NAME,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	innerBlocks: INNER_BLOCKS,
	example: {
		innerBlocks: INNER_BLOCKS,
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
	name: 'pseudo-details-' + NAME,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	attributes: {
		summary: __('Add to calendar', 'gatherpress'),
	},
	innerBlocks: [['core/buttons', {}, [...INNER_BLOCKS]]],
	example: {
		innerBlocks: [['core/buttons', {}, [...INNER_BLOCKS]]],
	},
});
