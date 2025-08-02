/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'gatherpress/form-field',
		{
			fieldName: 'author',
			label: __('Name', 'gatherpress'),
			placeholder: __("Name as you'd like it to appear", 'gatherpress'),
			required: true,
			autocomplete: 'name',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'email',
			fieldName: 'email',
			label: __('Email', 'gatherpress'),
			placeholder: __('your@email.com', 'gatherpress'),
			required: true,
			autocomplete: 'email',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'checkbox',
			fieldName: 'gatherpress_event_email_updates',
			fieldValue: false,
			label: __('Send me email updates about this event', 'gatherpress'),
		},
	],
	[
		'core/buttons',
		{},
		[
			[
				'core/button',
				{
					className: 'gatherpress-submit-button',
					tagName: 'button',
					text: __('Submit', 'gatherpress'),
				},
			],
		],
	],
];

export default TEMPLATE;
