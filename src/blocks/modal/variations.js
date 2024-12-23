/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
const variations = [
	{
		name: 'modal-rsvp',
		title: __('RSVP Modal', 'gatherpress'),
		description: __(
			'A modal specifically designed for updating RSVP status.',
			'gatherpress'
		),
		isActive: (blockAttributes, variationAttributes) => {
			return blockAttributes.variation === variationAttributes.variation;
		},
		attributes: {
			variation: 'rsvp',
		},
	},
	{
		name: 'modal-login',
		title: __('Login Modal', 'gatherpress'),
		description: __(
			'A modal specifically designed for updating RSVP status.',
			'gatherpress'
		),
		isActive: (blockAttributes, variationAttributes) => {
			return blockAttributes.variation === variationAttributes.variation;
		},
		attributes: {
			variation: 'login',
		},
	},
];

export default variations;
