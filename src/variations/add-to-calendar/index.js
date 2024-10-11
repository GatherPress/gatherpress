/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import { create } from '@wordpress/icons';


/**
 * Internal dependencies
 */
import { GPQLIcon } from './components/icon';


const GPIB = 'gatherpress-add-to-calendar-details';
// const GPIB_CLASS_NAME   = 'gp-add-to-calendar-details';

const GPIB_VARIATION_ATTRIBUTES = {
	category: 'gatherpress',
	isActive: [ 'metadata.bindings.url.args.service' ], // 'className' can be a string of multiple classes, e.g. when using block styles, so avoid them over here. The 'title' attibute however is unique to our variation and non-editable by the editor.
	scope: [ 'inserter', 'transform', 'block' ], // Defaults to 'block' and 'inserter'.
	example: {}
};

const GPIB_BUTTON_ATTRIBUTES = {
	tagName: 'a', // By setting this to 'button', instead of 'a', we can completely prevent the LinkControl getting rendered into the Toolbar.
	// className: GPIB_CLASS_NAME,
};

function createButtonAttributes(titleText, buttonText, service) {
	return {
		...GPIB_BUTTON_ATTRIBUTES,
		title: titleText,
		text: buttonText,
		rel: ( service === 'google' || service === 'yahoo' ) ? 'noopener norefferrer' : null,
		linkTarget: ( service === 'google' || service === 'yahoo' ) ? '_blank' : null,
		placeholder: buttonText,
		metadata: {
			bindings: {
				url: {
					source: "gatherpress/add-to-calendar",
					args: {
						service: service
					}
				},
			},
			name: buttonText,
		},
	};
}
function registerButtonVariation( titleText, buttonText, service) {
	const buttonAttributes = createButtonAttributes(titleText, buttonText, service);

	/**
	 * 
	 * 
	 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
	 */
	registerBlockVariation( 'core/button', {
		...GPIB_VARIATION_ATTRIBUTES,
		name: GPIB + '-' + service,
		title: buttonText,
		description: titleText,
		attributes: {
			...buttonAttributes
		},
	});

	return { ...buttonAttributes };
}
const GPIB_BUTTON_ATTRIBUTES_GOOGLE = registerButtonVariation(
	__('Add event to your Google calendar.', 'gatherpress'),
	'Google',
	'google'
);
const GPIB_BUTTON_ATTRIBUTES_ICAL = registerButtonVariation(
	__('Download event as ical file.', 'gatherpress'),
	'iCal',
	'ical'
);
const GPIB_BUTTON_ATTRIBUTES_OUTLOOK = registerButtonVariation(
	__('Download event as outlook file.', 'gatherpress'),
	'Outlook',
	'outlook'
);
const GPIB_BUTTON_ATTRIBUTES_YAHOO = registerButtonVariation(
	__('Add event to your Yahoo calendar.', 'gatherpress'),
	'Yahoo',
	'yahoo'
);

const GPIB_INNER_BLOCKS = [
	[
		'core/button',
		{
			...GPIB_BUTTON_ATTRIBUTES_GOOGLE
		},

	],
	[
		'core/button',
		{
			...GPIB_BUTTON_ATTRIBUTES_ICAL
		},

	],
	[
		'core/button',
		{
			...GPIB_BUTTON_ATTRIBUTES_OUTLOOK
		},

	],
	[
		'core/button',
		{
			...GPIB_BUTTON_ATTRIBUTES_YAHOO
		},

	],
];

const newIco = GPQLIcon( { iconName: create } );

/**
 * A Trap block, that looks like a single button, hohoho.
 *  
 * This block-variation is only useful, because a user can pick the block directly from the inserter or the left sidebar.
 * 
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( 'core/buttons', {
	title: __( 'Add to calendar (BUTTONS)', 'gatherpress' ),
	description: __( 'Allows a user to add an event to their preferred calendar.', 'gatherpress' ),
	category: 'gatherpress',
	icon: newIco,
	name: 'pseudo-' + GPIB,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	innerBlocks: GPIB_INNER_BLOCKS,
	example: {
		innerBlocks: GPIB_INNER_BLOCKS,
	}
} );
registerBlockVariation( 'core/details', {
	title: __( 'Add to calendar (DETAILS)', 'gatherpress' ),
	description: __( 'Allows a user to add an event to their preferred calendar.', 'gatherpress' ),
	category: 'gatherpress',
	name: 'pseudo-details-' + GPIB,
	// isActive: [ 'namespace', 'title' ], // This is not used/disabled by purpose.
	attributes: {
		summary: __( 'Add to calendar (DETAILS)', 'gatherpress' ),
	},
	innerBlocks: [
		[
			'core/buttons',
			{},
			[
				...GPIB_INNER_BLOCKS,
			],

		],
	],
	example: {
		innerBlocks: [
			[
				'core/buttons',
				{},
				[
					...GPIB_INNER_BLOCKS,
				],
	
			],
		],
	}
} );
