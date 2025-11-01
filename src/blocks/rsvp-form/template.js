/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/group',
		{
			formVisibility: 'showOnSuccess',
			style: {
				spacing: {
					margin: {
						bottom: '1rem',
					},
				},
			},
		},
		[
			[
				'core/heading',
				{
					content: __(
						'Thank you for your RSVP!',
						'gatherpress',
					),
					level: 3,
					style: {
						typography: {
							fontWeight: '600',
						},
						color: {
							text: '#16a085',
						},
					},
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Please check your email for a confirmation link to complete your registration.',
						'gatherpress',
					),
				},
			],
		],
	],
	[
		'gatherpress/form-field',
		{
			fieldName: 'author',
			label: __( 'Name', 'gatherpress' ),
			placeholder: __( "Name as you'd like it to appear", 'gatherpress' ),
			required: true,
			autocomplete: 'name',
			formVisibility: 'hideOnSuccess',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'email',
			fieldName: 'email',
			label: __( 'Email', 'gatherpress' ),
			placeholder: __( 'your@email.com', 'gatherpress' ),
			required: true,
			autocomplete: 'email',
			formVisibility: 'hideOnSuccess',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'number',
			fieldName: 'gatherpress_rsvp_guests',
			label: __( 'Number of guests?', 'gatherpress' ),
			placeholder: __( '0', 'gatherpress' ),
			minValue: 0,
			inlineLayout: true,
			fieldWidth: 10,
			inputPadding: 5,
			autocomplete: 'off',
			formVisibility: 'hideOnSuccess',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'checkbox',
			fieldName: 'gatherpress_rsvp_anonymous',
			fieldValue: false,
			label: __( 'List me as anonymous', 'gatherpress' ),
			formVisibility: 'hideOnSuccess',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'checkbox',
			fieldName: 'gatherpress_event_updates_opt_in',
			fieldValue: false,
			label: __( 'Email me updates about this event', 'gatherpress' ),
			formVisibility: 'hideOnSuccess',
		},
	],
	[
		'core/buttons',
		{
			formVisibility: 'hideOnSuccess',
		},
		[
			[
				'core/button',
				{
					className: 'gatherpress-submit-button',
					tagName: 'button',
					text: __( 'Submit', 'gatherpress' ),
				},
			],
		],
	],
];

export default TEMPLATE;
